<?php

namespace App\Console\Commands;

use App\Jobs\TrendAgent\SyncApartmentDetailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentDispatchApartmentDetail extends Command
{
    protected $signature = 'trendagent:dispatch:apartment-detail
                            {apartment_id : Apartment ID to sync}
                            {--city= : City ID (defaults to config)}
                            {--lang= : Language (defaults to config)}';

    protected $description = 'Dispatch SyncApartmentDetailJob to the queue for one apartment';

    public function handle(): int
    {
        $apartmentId = $this->argument('apartment_id');
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang') ?? Config::get('trendagent.default_lang', 'ru');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');

            return self::FAILURE;
        }

        dispatch(new SyncApartmentDetailJob($apartmentId, $cityId, $lang, true));
        $this->info('Dispatched SyncApartmentDetailJob for apartment_id=' . $apartmentId . '.');

        return self::SUCCESS;
    }
}
