<?php

namespace App\Console\Commands;

use App\Jobs\TrendAgent\SyncBlockDetailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentDispatchBlockDetail extends Command
{
    protected $signature = 'trendagent:dispatch:block-detail
                            {block_id : Block ID to sync}
                            {--city= : City ID (defaults to config)}
                            {--lang= : Language (defaults to config)}';

    protected $description = 'Dispatch SyncBlockDetailJob to the queue for one block';

    public function handle(): int
    {
        $blockId = $this->argument('block_id');
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang') ?? Config::get('trendagent.default_lang', 'ru');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');

            return self::FAILURE;
        }

        dispatch(new SyncBlockDetailJob($blockId, $cityId, $lang, true));
        $this->info('Dispatched SyncBlockDetailJob for block_id=' . $blockId . '.');

        return self::SUCCESS;
    }
}
