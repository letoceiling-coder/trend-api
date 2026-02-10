<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentSyncBlocks extends Command
{
    protected $signature = 'trendagent:sync:blocks
                            {--show-type=list : Show type: list, map, or plans}
                            {--count=20 : Number of items per page}
                            {--max-pages=50 : Maximum number of pages to fetch}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--no-raw : Do not store raw payload cache}';

    protected $description = 'Sync blocks from TrendAgent blocks/search endpoint';

    public function handle(TrendAgentSyncService $sync): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $storeRawPayload = ! $this->option('no-raw');

        $showType = $this->option('show-type');
        $count = (int) $this->option('count');
        $maxPages = (int) $this->option('max-pages');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $this->line('Syncing blocks...');
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->line('Show type: ' . $showType);
        $this->line('Count per page: ' . $count);
        $this->line('Max pages: ' . $maxPages);

        $startTime = microtime(true);

        $params = [
            'show_type' => $showType,
            'count' => $count,
            'max_pages' => $maxPages,
        ];

        $result = $sync->syncBlocksSearch($params, $cityId, $lang, $storeRawPayload);
        $run = $result['run'];
        $totalPages = $result['total_pages'];

        $duration = round((microtime(true) - $startTime) * 1000);

        if ($run->status === 'success') {
            $this->info('✓ Sync completed successfully');
            $this->line('Items fetched: ' . $run->items_fetched);
            $this->line('Items saved: ' . $run->items_saved);
            $this->line('Pages processed: ' . $totalPages);
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
