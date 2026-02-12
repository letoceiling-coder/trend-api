<?php

namespace App\Console\Commands;

use App\Jobs\TrendAgent\SyncBlocksJob;
use App\Models\Domain\TrendAgent\TaCity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TrendagentDispatchBlocksAll extends Command
{
    protected $signature = 'trendagent:dispatch:blocks-all
                            {--lang=ru : Language}
                            {--count=20 : Items per page}
                            {--max-pages=50 : Max pages per region}
                            {--no-dispatch-details : Do not dispatch block-detail jobs after list sync}';

    protected $description = 'Dispatch SyncBlocksJob for each region (from ta_cities or TRENDAGENT_DEFAULT_CITY_ID)';

    public function handle(): int
    {
        $lang = $this->option('lang');
        $regions = TaCity::getRegionsToSync();

        if ($regions->isEmpty()) {
            $this->warn('No regions to sync: ta_cities is empty and TRENDAGENT_DEFAULT_CITY_ID is not set.');
            Log::warning('TrendAgent dispatch:blocks-all: no regions (ta_cities empty, no default_city_id)');
            return self::FAILURE;
        }

        foreach ($regions as $r) {
            $cityId = $r['city_id'];
            $key = $r['key'];
            try {
                dispatch(new SyncBlocksJob(
                    cityId: $cityId,
                    lang: $lang,
                    count: (int) $this->option('count'),
                    maxPages: (int) $this->option('max-pages'),
                    showType: 'list',
                    storeRawPayload: true,
                    dispatchDetailsAfter: ! $this->option('no-dispatch-details'),
                ));
                $this->line("Dispatched blocks: {$key} ({$cityId})");
                Log::info('TrendAgent dispatch:blocks-all dispatched', ['city_id' => $cityId, 'key' => $key]);
            } catch (\Throwable $e) {
                $this->error("Failed to dispatch blocks for {$key}: " . $e->getMessage());
                Log::error('TrendAgent dispatch:blocks-all dispatch failed', [
                    'city_id' => $cityId,
                    'key' => $key,
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                ]);
            }
        }

        $this->info('Dispatched blocks for ' . $regions->count() . ' region(s).');
        return self::SUCCESS;
    }
}
