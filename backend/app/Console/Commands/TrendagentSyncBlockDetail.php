<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentSyncBlockDetail extends Command
{
    protected $signature = 'trendagent:sync:block-detail
                            {block_id : Block ID to sync}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--no-raw : Do not store raw payloads}';

    protected $description = 'Sync block detail information (unified, advantages, nearby_places, bank, geo_buildings, apartments_min_price)';

    public function handle(TrendAgentSyncService $syncService): int
    {
        $blockId = $this->argument('block_id');
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $storeRawPayload = ! $this->option('no-raw');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $this->line('=== TrendAgent Block Detail Sync ===');
        $this->line('Block ID: ' . $blockId);
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->line('Store raw: ' . ($storeRawPayload ? 'yes' : 'no'));
        $this->newLine();

        $startTime = microtime(true);

        try {
            $run = $syncService->syncBlockDetail($blockId, $cityId, $lang, $storeRawPayload);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($run->status === 'success') {
                $this->info('✓ Sync completed successfully');
                $this->line('Endpoints fetched: ' . $run->items_fetched);
                $this->line('Records saved: ' . $run->items_saved);
                $this->line('Duration: ' . $duration . 'ms');
                $this->line('Run ID: ' . $run->id);

                return self::SUCCESS;
            } else {
                $this->error('✗ Sync failed');
                $this->line('Status: ' . $run->status);
                $this->line('Error: ' . $run->error_message);
                $this->line('Duration: ' . $duration . 'ms');
                $this->line('Run ID: ' . $run->id);

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->error('✗ Sync exception: ' . $e->getMessage());
            $this->line('Duration: ' . $duration . 'ms');

            return self::FAILURE;
        }
    }
}
