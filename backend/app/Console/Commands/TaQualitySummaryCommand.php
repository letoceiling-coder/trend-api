<?php

namespace App\Console\Commands;

use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use Illuminate\Console\Command;

class TaQualitySummaryCommand extends Command
{
    protected $signature = 'ta:quality:summary
                            {--since=24h : Period (e.g. 24h, 7d)}';

    protected $description = 'Show pass/warn/fail counts per scope for data quality checks';

    public function handle(): int
    {
        $since = $this->parseSince($this->option('since') ?? '24h');
        if ($since === null) {
            $this->error('Invalid --since value. Use e.g. 24h, 7d.');
            return self::FAILURE;
        }

        $query = TaDataQualityCheck::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('scope, status, count(*) as cnt')
            ->groupBy('scope', 'status');

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->info('No quality checks in the given period.');
            return self::SUCCESS;
        }

        $table = [];
        $byScope = [];
        foreach ($rows as $r) {
            $byScope[$r->scope][$r->status] = (int) $r->cnt;
        }
        foreach ($byScope as $scope => $statuses) {
            $table[] = [
                $scope,
                $statuses['pass'] ?? 0,
                $statuses['warn'] ?? 0,
                $statuses['fail'] ?? 0,
            ];
        }
        $this->table(['scope', 'pass', 'warn', 'fail'], $table);
        return self::SUCCESS;
    }

    private function parseSince(string $value): ?\DateTimeInterface
    {
        $value = strtolower(trim($value));
        if (preg_match('/^(\d+)([hdm])$/', $value, $m)) {
            $num = (int) $m[1];
            if ($num <= 0) {
                return null;
            }
            return match ($m[2]) {
                'h' => now()->subHours($num),
                'd' => now()->subDays($num),
                'm' => now()->subMinutes($num),
                default => null,
            };
        }
        return null;
    }
}
