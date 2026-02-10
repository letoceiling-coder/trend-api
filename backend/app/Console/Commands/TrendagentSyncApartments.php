<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentSyncApartments extends Command
{
    protected $signature = 'trendagent:sync:apartments
                            {--count=50 : Items per page}
                            {--max-pages=20 : Max pages to fetch}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--sort=price : Sort field}
                            {--sort-order=asc : Sort order}
                            {--no-raw : Do not store raw payload in ta_apartments.raw and skip payload cache}';

    protected $description = 'Sync apartments from apartments/search API into ta_apartments';

    public function handle(TrendAgentSyncService $syncService): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $params = [
            'count' => (int) $this->option('count'),
            'max_pages' => (int) $this->option('max-pages'),
            'sort' => $this->option('sort'),
            'sort_order' => $this->option('sort-order'),
        ];

        $storeRawPayload = ! $this->option('no-raw');

        $this->line('Syncing apartments...');
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->line('Count per page: ' . $params['count']);
        $this->line('Max pages: ' . $params['max_pages']);
        $this->newLine();

        $startTime = microtime(true);

        try {
            $result = $syncService->syncApartmentsSearch($params, $cityId, $lang, $storeRawPayload);
            $run = $result['run'];
            $duration = round((microtime(true) - $startTime) * 1000);

            if ($run->status === 'success') {
                $this->info('✓ Sync completed successfully');
                $this->line('Items fetched: ' . $run->items_fetched);
                $this->line('Items saved: ' . $run->items_saved);
                $this->line('Pages processed: ' . $result['total_pages']);
                $this->line('Duration: ' . $duration . 'ms');
                $this->line('Run ID: ' . $run->id);

                return self::SUCCESS;
            }

            $this->error('✗ Sync failed');
            $this->line('Status: ' . $run->status);
            $this->line('Error: ' . $run->error_message);
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
