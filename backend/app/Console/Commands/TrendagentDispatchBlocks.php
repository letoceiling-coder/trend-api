<?php

namespace App\Console\Commands;

use App\Jobs\TrendAgent\SyncBlocksJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentDispatchBlocks extends Command
{
    protected $signature = 'trendagent:dispatch:blocks
                            {--city= : City ID (defaults to config)}
                            {--lang= : Language (defaults to config)}
                            {--count=20 : Items per page}
                            {--max-pages=50 : Max pages}
                            {--show-type=list : list, map, or plans}
                            {--no-dispatch-details : Do not dispatch block-detail jobs after list sync}';

    protected $description = 'Dispatch SyncBlocksJob to the queue (blocks list sync)';

    public function handle(): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang') ?? Config::get('trendagent.default_lang', 'ru');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');

            return self::FAILURE;
        }

        $job = new SyncBlocksJob(
            cityId: $cityId,
            lang: $lang,
            count: (int) $this->option('count'),
            maxPages: (int) $this->option('max-pages'),
            showType: $this->option('show-type'),
            storeRawPayload: true,
            dispatchDetailsAfter: ! $this->option('no-dispatch-details'),
        );

        dispatch($job);
        $this->info('Dispatched SyncBlocksJob (city=' . $cityId . ', lang=' . $lang . ').');

        return self::SUCCESS;
    }
}
