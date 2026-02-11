<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentSyncApartmentDetail extends Command
{
    protected $signature = 'trendagent:sync:apartment-detail
                            {apartment_id : Apartment ID to sync}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--no-raw : Do not store raw payloads in cache}';

    protected $description = 'Sync apartment detail (unified, prices_totals, prices_graph) into ta_apartment_details';

    public function handle(TrendAgentSyncService $syncService): int
    {
        $apartmentId = $this->argument('apartment_id');
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $storeRawPayload = ! $this->option('no-raw');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $this->line('=== TrendAgent Apartment Detail Sync ===');
        $this->line('Apartment ID: ' . $apartmentId);
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->line('Store raw: ' . ($storeRawPayload ? 'yes' : 'no'));
        $this->newLine();

        $startTime = microtime(true);

        try {
            $result = $syncService->syncApartmentDetail($apartmentId, $cityId, $lang, $storeRawPayload);
            $run = $result['run'];
            $duration = round((microtime(true) - $startTime) * 1000);

            if ($run->status === 'success') {
                $this->info('✓ Sync completed successfully');
                $this->line('Endpoints ok: ' . $result['endpoints_ok']);
                $this->line('Endpoints failed: ' . $result['endpoints_failed']);
                $this->line('Duration: ' . $duration . 'ms');
                $this->line('Run ID: ' . $run->id);

                return self::SUCCESS;
            }

            $this->error('✗ Sync failed');
            $this->line('Status: ' . $run->status);
            $this->line('Error: ' . $run->error_message);
            $this->line('Duration: ' . $duration . 'ms');
            $this->line('Run ID: ' . $run->id);

            return self::FAILURE;
        } catch (\Throwable $e) {
            $duration = round((microtime(true) - $startTime) * 1000);
            $this->error('✗ Sync exception: ' . $e->getMessage());
            $this->line('Duration: ' . $duration . 'ms');

            return self::FAILURE;
        }
    }
}
