<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\TaAlertService;
use App\Domain\TrendAgent\TaHealthService;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TaAlertsCheckCommand extends Command
{
    protected $signature = 'ta:alerts:check
                            {--since=15m : Period for failed sync check and "no success" threshold (e.g. 15m, 1h)}';

    protected $description = 'Check sync/quality conditions and send Telegram alerts if configured';

    private const CACHE_KEY_QUALITY_LAST = 'ta_alerts_last_quality_fail_count';
    private const DEDUPE_TTL_SECONDS = 1800; // 30 min
    private const DEDUPE_PREFIX = 'ta:alert:dedupe:';
    private const QUIET_SUPPRESSED_KEY = 'ta:alert:quiet_suppressed';
    private const QUIET_SUPPRESSED_TTL_SECONDS = 43200; // 12 h

    private const REASON_FAILED_RUNS = 'failed_runs';
    private const REASON_NO_SUCCESS = 'no_success';
    private const REASON_QUALITY_GROWTH = 'quality_growth';

    public function handle(TaAlertService $alerts): int
    {
        $since = $this->parseSince($this->option('since'));
        if ($since === null) {
            $this->error('Invalid --since. Use e.g. 15m, 1h.');
            return self::FAILURE;
        }

        $sent = 0;

        if ($this->sendQuietHoursSummaryIfNeeded($alerts, $sent)) {
            // summary was sent
        }

        $inQuietHours = $this->isQuietHours();

        $this->checkFailedSyncRuns($alerts, $since, $inQuietHours, $sent);
        $this->checkQualityFailIncrease($alerts, $inQuietHours, $sent);
        $this->checkNoRecentSuccess($alerts, $since, $inQuietHours, $sent);

        if ($sent > 0) {
            $this->info("Sent {$sent} alert(s).");
        }

        return self::SUCCESS;
    }

    private function sendQuietHoursSummaryIfNeeded(TaAlertService $alerts, int &$sent): bool
    {
        if ($this->isQuietHours()) {
            return false;
        }
        $data = Cache::get(self::QUIET_SUPPRESSED_KEY);
        if (! is_array($data) || empty($data['count'])) {
            return false;
        }
        $count = (int) $data['count'];
        $reasons = $data['reasons'] ?? [];
        arsort($reasons);
        $top = array_slice(array_keys($reasons), 0, 5);
        $lines = array_map(fn ($r) => $r . ': ' . ($reasons[$r] ?? 0), $top);
        $message = "[TA] During quiet hours we suppressed {$count} alert(s).\nTop reasons: " . implode(', ', $lines);
        if ($alerts->send($message, ['quiet_summary' => true])) {
            $sent++;
        }
        Cache::forget(self::QUIET_SUPPRESSED_KEY);
        return true;
    }

    private function isQuietHours(): bool
    {
        $spec = config('trendagent.alerts.quiet_hours', '');
        if ($spec === '' || $spec === null) {
            return false;
        }
        if (! preg_match('/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/', trim($spec), $m)) {
            return false;
        }
        $startH = (int) $m[1];
        $startM = (int) $m[2];
        $endH = (int) $m[3];
        $endM = (int) $m[4];
        $tz = config('trendagent.alerts.quiet_hours_timezone', 'Europe/Kiev');
        $now = Carbon::now($tz);
        $start = $now->copy()->setTime($startH, $startM, 0);
        $end = $now->copy()->setTime($endH, $endM, 0);
        if ($startH > $endH || ($startH === $endH && $startM >= $endM)) {
            return $now->gte($start) || $now->lt($end);
        }
        return $now->gte($start) && $now->lt($end);
    }

    private function recordSuppressed(string $reason): void
    {
        $data = Cache::get(self::QUIET_SUPPRESSED_KEY);
        if (! is_array($data)) {
            $data = ['count' => 0, 'reasons' => []];
        }
        $data['count'] = ($data['count'] ?? 0) + 1;
        $data['reasons'][$reason] = ($data['reasons'][$reason] ?? 0) + 1;
        Cache::put(self::QUIET_SUPPRESSED_KEY, $data, self::QUIET_SUPPRESSED_TTL_SECONDS);
    }

    private function sendIfNotDeduplicated(TaAlertService $alerts, string $alertType, string $fingerprint, string $message, array $context, int &$sent): void
    {
        $key = self::DEDUPE_PREFIX . $alertType;
        $cached = Cache::get($key);
        if ($cached === $fingerprint) {
            return;
        }
        if ($alerts->send($message, $context)) {
            Cache::put($key, $fingerprint, self::DEDUPE_TTL_SECONDS);
            $sent++;
        }
    }

    private function checkFailedSyncRuns(TaAlertService $alerts, \DateTimeInterface $since, bool $inQuietHours, int &$sent): void
    {
        $failed = TaSyncRun::query()
            ->where('provider', 'trendagent')
            ->where('status', 'failed')
            ->where('started_at', '>=', $since)
            ->get();

        if ($failed->isEmpty()) {
            return;
        }

        $byScope = $failed->groupBy('scope')->map->count()->sortDesc()->take(5);
        $scopeList = $byScope->map(fn ($c, $s) => "{$s}: {$c}")->implode(', ');
        $sinceHours = max(1, (int) ceil((time() - $since->getTimestamp()) / 3600));
        $curlHint = "GET /api/ta/admin/sync-runs?status=failed&since_hours={$sinceHours} (add X-Internal-Key header)";
        $message = "TA Alert: Failed sync runs\n"
            . "Count: " . $failed->count() . "\n"
            . "Top scopes: " . $scopeList . "\n"
            . "Details: " . $curlHint;

        $scopePayload = $byScope->sortKeys()->toArray();
        ksort($scopePayload);
        $fingerprint = hash('sha256', 'failed_runs:' . json_encode($scopePayload));
        if ($inQuietHours) {
            $this->recordSuppressed(self::REASON_FAILED_RUNS);
            return;
        }
        $this->sendIfNotDeduplicated($alerts, self::REASON_FAILED_RUNS, $fingerprint, $message, ['failed_count' => $failed->count()], $sent);
    }

    private function checkQualityFailIncrease(TaAlertService $alerts, bool $inQuietHours, int &$sent): void
    {
        $current = TaDataQualityCheck::query()
            ->where('status', 'fail')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $previous = Cache::get(self::CACHE_KEY_QUALITY_LAST);
        Cache::put(self::CACHE_KEY_QUALITY_LAST, $current, now()->addHours(48));

        if ($previous === null || $current <= $previous) {
            return;
        }

        $message = "TA Alert: Quality fail count increased (24h)\n"
            . "Previous: {$previous} → Current: {$current}\n"
            . "Details: GET /api/ta/admin/quality-checks?status=fail&since_hours=24 (add X-Internal-Key header)";

        $fingerprint = hash('sha256', 'quality_growth:' . $previous . ':' . $current);
        if ($inQuietHours) {
            $this->recordSuppressed(self::REASON_QUALITY_GROWTH);
            return;
        }
        $this->sendIfNotDeduplicated($alerts, self::REASON_QUALITY_GROWTH, $fingerprint, $message, ['previous' => $previous, 'current' => $current], $sent);
    }

    private function checkNoRecentSuccess(TaAlertService $alerts, \DateTimeInterface $since, bool $inQuietHours, int &$sent): void
    {
        $scopes = ['blocks', 'apartments'];
        $missing = [];

        foreach ($scopes as $scope) {
            $last = TaSyncRun::query()
                ->where('provider', 'trendagent')
                ->where('scope', $scope)
                ->where('status', 'success')
                ->orderByDesc('finished_at')
                ->first();

            if ($last === null || $last->finished_at === null || $last->finished_at < $since) {
                $missing[] = $scope;
            }
        }

        if ($missing === []) {
            return;
        }

        $sinceHours = max(1, (int) ceil((time() - $since->getTimestamp()) / 3600));
        $curlHint = "GET /api/ta/admin/sync-runs?since_hours={$sinceHours} (add X-Internal-Key header)";
        $message = "TA Alert: No successful sync in period\n"
            . "Scopes: " . implode(', ', $missing) . "\n"
            . "Details: " . $curlHint;

        $sorted = $missing;
        sort($sorted);
        $fingerprint = hash('sha256', 'no_success:' . implode(',', $sorted));
        if ($inQuietHours) {
            $this->recordSuppressed(self::REASON_NO_SUCCESS);
            return;
        }
        $this->sendIfNotDeduplicated($alerts, self::REASON_NO_SUCCESS, $fingerprint, $message, ['scopes' => $missing], $sent);
    }

    private function checkAuthReloginFailing(TaAlertService $alerts, TaHealthService $health, bool $inQuietHours, int &$sent): void
    {
        $data = $health->getHealthData();
        $failed = (int) ($data['relogin_failed_last_24h'] ?? 0);
        if ($failed <= 0) {
            return;
        }

        $message = "TA Alert: Auth relogin failing\n"
            . "Failed relogin attempts in last 24h: {$failed}\n"
            . "Check logs and ta_relogin_events table.";

        $fingerprint = hash('sha256', self::REASON_AUTH_RELOGIN . ':' . $failed);
        if ($inQuietHours) {
            $this->recordSuppressed(self::REASON_AUTH_RELOGIN);
            return;
        }
        $this->sendIfNotDeduplicated($alerts, self::REASON_AUTH_RELOGIN, $fingerprint, $message, ['relogin_failed_last_24h' => $failed], $sent);
    }

    private function parseSince(string $value): ?\DateTimeInterface
    {
        $value = strtolower(trim($value));
        if (preg_match('/^(\d+)([mh])$/', $value, $m)) {
            $num = (int) $m[1];
            if ($num <= 0) {
                return null;
            }
            return match ($m[2]) {
                'm' => now()->subMinutes($num),
                'h' => now()->subHours($num),
                default => null,
            };
        }
        return null;
    }
}
