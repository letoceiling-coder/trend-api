<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentSyncUnitMeasurements extends Command
{
    protected $signature = 'trendagent:sync:unit-measurements
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--no-raw : Do not store raw payload cache}';

    protected $description = 'Sync unit measurements from TrendAgent core API';

    public function handle(TrendAgentSyncService $sync): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $storeRawPayload = ! $this->option('no-raw');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $this->line('Syncing unit measurements...');
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);

        $startTime = microtime(true);

        $run = $sync->syncUnitMeasurements($cityId, $lang, $storeRawPayload);

        $duration = round((microtime(true) - $startTime) * 1000);

        if ($run->status === 'success') {
            $this->info('✓ Sync completed successfully');
            $this->line('Items fetched: ' . $run->items_fetched);
            $this->line('Items saved: ' . $run->items_saved);
            $this->line('Duration: ' . $duration . 'ms');
            $this->line('Run ID: ' . $run->id);
            return self::SUCCESS;
        } else {
            $this->error('✗ Sync failed');
            $this->line('Error: ' . $run->error_message);
            $this->line('Run ID: ' . $run->id);
            return self::FAILURE;
        }
    }
}
