<?php

namespace App\Domain\TrendAgent;

use App\Console\Kernel as ConsoleKernel;
use App\Jobs\TaQueueHeartbeatJob;
use App\Models\Domain\TrendAgent\TaContractChange;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use App\Models\Domain\TrendAgent\TaPipelineRun;
use App\Models\Domain\TrendAgent\TaReloginEvent;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

/**
 * Aggregates TA health data (sync last success, contract/quality counts, queue info, coverage, runtime).
 * Used by HealthController and ta:smoke.
 */
class TaHealthService
{
    private const RUNTIME_FRESH_MINUTES = 2;

    public function __construct(
        private readonly TaCoverageService $coverage
    ) {
    }

    /**
     * @return array{sync: array<string, array{last_success_at: string|null}>, contract_changes_last_24h_count: int, quality_fail_last_24h_count: int, queue: array{connection: string, queue_name: string}, coverage: array{blocks_total: int, blocks_with_detail_fresh: int, blocks_without_detail: int, apartments_total: int, apartments_with_detail_fresh: int, apartments_without_detail: int}, runtime: array{schedule_ok: bool, queue_ok: bool, redis_ok: bool, last_schedule_run_at: string|null, last_queue_heartbeat_at: string|null}}
     */
    public function getHealthData(): array
    {
        $scopes = ['blocks', 'apartments', 'block_detail', 'apartment_detail'];
        $sync = [];
        foreach ($scopes as $scope) {
            $last = TaSyncRun::query()
                ->where('provider', 'trendagent')
                ->where('scope', $scope)
                ->where('status', 'success')
                ->orderByDesc('finished_at')
                ->first();
            $sync[$scope] = [
                'last_success_at' => $last?->finished_at?->toIso8601String(),
            ];
        }

        $contractChangesLast24h = TaContractChange::query()
            ->where('detected_at', '>=', now()->subHours(24))
            ->count();

        $qualityFailLast24h = TaDataQualityCheck::query()
            ->where('status', 'fail')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $queueConnection = Config::get('queue.default', 'sync');
        $queueName = config('trendagent.queue.queue_name', 'default');

        $pipelineLast24h = TaPipelineRun::query()
            ->where('started_at', '>=', now()->subHours(24))
            ->count();
        $pipelineFailedLast24h = TaPipelineRun::query()
            ->where('status', 'failed')
            ->where('started_at', '>=', now()->subHours(24))
            ->count();

        $reloginAttemptsLast24h = TaReloginEvent::query()
            ->where('attempted_at', '>=', now()->subHours(24))
            ->count();
        $reloginFailedLast24h = TaReloginEvent::query()
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subHours(24))
            ->count();

        return [
            'sync' => $sync,
            'contract_changes_last_24h_count' => $contractChangesLast24h,
            'quality_fail_last_24h_count' => $qualityFailLast24h,
            'pipeline_last_24h_count' => $pipelineLast24h,
            'pipeline_failed_last_24h_count' => $pipelineFailedLast24h,
            'relogin_attempts_last_24h' => $reloginAttemptsLast24h,
            'relogin_failed_last_24h' => $reloginFailedLast24h,
            'queue' => [
                'connection' => $queueConnection,
                'queue_name' => $queueName,
            ],
            'coverage' => $this->coverage->getCoverageData(),
            'runtime' => $this->getRuntimeData($queueConnection),
        ];
    }

    /**
     * Runtime verification: schedule last run, queue worker heartbeat, redis ping.
     *
     * @return array{schedule_ok: bool, queue_ok: bool, redis_ok: bool, last_schedule_run_at: string|null, last_queue_heartbeat_at: string|null}
     */
    public function getRuntimeData(string $queueConnection): array
    {
        $threshold = now()->subMinutes(self::RUNTIME_FRESH_MINUTES);

        $lastScheduleRun = $this->getLastScheduleRunAt();
        $scheduleOk = $lastScheduleRun !== null && Carbon::parse($lastScheduleRun)->gt($threshold);

        $lastQueueHeartbeat = $this->getLastQueueHeartbeatAt();
        $queueOk = $queueConnection !== 'redis'
            || ($lastQueueHeartbeat !== null && Carbon::parse($lastQueueHeartbeat)->gt($threshold));

        $redisOk = $queueConnection !== 'redis' || $this->pingRedis();

        return [
            'schedule_ok' => $scheduleOk,
            'queue_ok' => $queueOk,
            'redis_ok' => $redisOk,
            'last_schedule_run_at' => $lastScheduleRun,
            'last_queue_heartbeat_at' => $lastQueueHeartbeat,
        ];
    }

    public function getLastScheduleRunAt(): ?string
    {
        $value = Cache::get(ConsoleKernel::SCHEDULE_LAST_RUN_CACHE_KEY);
        if ($value === null) {
            return null;
        }
        return $value instanceof \DateTimeInterface
            ? $value->format(\DateTimeInterface::ATOM)
            : (string) $value;
    }

    public function getLastQueueHeartbeatAt(): ?string
    {
        $value = Cache::get(TaQueueHeartbeatJob::CACHE_KEY);
        if ($value === null) {
            return null;
        }
        return $value instanceof \DateTimeInterface
            ? $value->format(\DateTimeInterface::ATOM)
            : (string) $value;
    }

    private function pingRedis(): bool
    {
        try {
            Redis::connection()->ping();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
