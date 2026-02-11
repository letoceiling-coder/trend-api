<?php

namespace App\Console\Commands;

use App\Jobs\TrendAgent\SyncApartmentsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentDispatchApartments extends Command
{
    protected $signature = 'trendagent:dispatch:apartments
                            {--city= : City ID (defaults to config)}
                            {--lang= : Language (defaults to config)}
                            {--count=50 : Items per page}
                            {--max-pages=20 : Max pages}
                            {--sort=price : Sort field}
                            {--sort-order=asc : Sort order}
                            {--no-dispatch-details : Do not dispatch apartment-detail jobs after list sync}';

    protected $description = 'Dispatch SyncApartmentsJob to the queue (apartments list sync)';

    public function handle(): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang') ?? Config::get('trendagent.default_lang', 'ru');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');

            return self::FAILURE;
        }

        $job = new SyncApartmentsJob(
            cityId: $cityId,
            lang: $lang,
            count: (int) $this->option('count'),
            maxPages: (int) $this->option('max-pages'),
            sort: $this->option('sort'),
            sortOrder: $this->option('sort-order'),
            storeRawPayload: true,
            dispatchDetailsAfter: ! $this->option('no-dispatch-details'),
        );

        dispatch($job);
        $this->info('Dispatched SyncApartmentsJob (city=' . $cityId . ', lang=' . $lang . ').');

        return self::SUCCESS;
    }
}
