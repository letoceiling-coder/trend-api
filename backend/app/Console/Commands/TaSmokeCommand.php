<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\TaHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;

class TaSmokeCommand extends Command
{
    protected $signature = 'ta:smoke
                            {--dry-run : Only show pipeline params, do not run anything}';

    protected $description = 'Smoke check: health, queue connection, optional pipeline dry-run';

    public function handle(TaHealthService $health): int
    {
        $this->info('=== TA Smoke ===');

        $ok = true;

        try {
            $data = $health->getHealthData();
        } catch (\Throwable $e) {
            $this->error('Health: FAIL - ' . $e->getMessage());
            $ok = false;
        }

        if (isset($data)) {
            $this->info('Health: OK');
            foreach ($data['sync'] ?? [] as $scope => $info) {
                $at = $info['last_success_at'] ?? '—';
                $this->line("  sync.{$scope}.last_success_at: {$at}");
            }
            $this->line('  contract_changes_last_24h: ' . ($data['contract_changes_last_24h_count'] ?? 0));
            $this->line('  quality_fail_last_24h: ' . ($data['quality_fail_last_24h_count'] ?? 0));
            $this->line('  pipeline_last_24h: ' . ($data['pipeline_last_24h_count'] ?? 0) . ', pipeline_failed_last_24h: ' . ($data['pipeline_failed_last_24h_count'] ?? 0));
            $this->line('  queue: ' . ($data['queue']['connection'] ?? '?') . ' / ' . ($data['queue']['queue_name'] ?? '?'));
            $runtime = $data['runtime'] ?? null;
            if ($runtime) {
                $this->line('  runtime: schedule_ok=' . ($runtime['schedule_ok'] ? 'true' : 'false')
                    . ', queue_ok=' . ($runtime['queue_ok'] ? 'true' : 'false')
                    . ', redis_ok=' . ($runtime['redis_ok'] ? 'true' : 'false'));
                $this->line('    last_schedule_run_at: ' . ($runtime['last_schedule_run_at'] ?? '—'));
                $this->line('    last_queue_heartbeat_at: ' . ($runtime['last_queue_heartbeat_at'] ?? '—'));
            }
        }

        $connection = Config::get('queue.default', 'sync');
        if ($connection === 'redis') {
            try {
                Redis::connection()->ping();
                $this->info('Queue (Redis): connection OK');
            } catch (\Throwable $e) {
                $this->error('Queue (Redis): FAIL - ' . $e->getMessage());
                $ok = false;
            }
            $this->line('  Worker: run systemctl status trend-api-queue to verify worker process.');
        } else {
            $this->line("Queue: connection={$connection} (no ping check).");
        }

        if ($this->option('dry-run')) {
            $cityId = Config::get('trendagent.default_city_id') ?: '(from config)';
            $lang = Config::get('trendagent.default_lang', 'ru');
            $this->info('Pipeline dry-run: would run SyncBlocksJob + SyncApartmentsJob');
            $this->line("  city_id: {$cityId}, lang: {$lang}");
            $this->line('  blocks_count: 50, blocks_pages: 1, apartments_pages: 1');
            $this->line('  dispatch_details: true, detail_limit: 50');
            $this->line('  (No external requests; no jobs dispatched.)');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
