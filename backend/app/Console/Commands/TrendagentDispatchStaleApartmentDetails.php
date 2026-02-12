<?php

namespace App\Console\Commands;

use App\Jobs\TrendAgent\SyncApartmentDetailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrendagentDispatchStaleApartmentDetails extends Command
{
    protected $signature = 'trendagent:dispatch:stale-apartment-details
                            {--city= : City ID (defaults to config; empty = all cities)}
                            {--lang= : Language (defaults to config; empty = all langs)}';

    protected $description = 'Dispatch SyncApartmentDetailJob for apartments with missing or stale ta_apartment_details (batch)';

    public function handle(): int
    {
        $cityOpt = $this->option('city');
        $langOpt = $this->option('lang');
        $cityId = $cityOpt === '' ? null : ($cityOpt ?? Config::get('trendagent.default_city_id'));
        $lang = $langOpt === '' ? null : ($langOpt ?? Config::get('trendagent.default_lang', 'ru'));
        $refreshHours = (int) Config::get('trendagent.detail_refresh_hours', 24);
        $batchSize = (int) Config::get('trendagent.detail_batch_size', 50);
        $delaySeconds = (int) Config::get('trendagent.queue.detail_job_delay_seconds', 2);

        $threshold = now()->subHours($refreshHours);

        $query = DB::table('ta_apartments as a')
            ->leftJoin('ta_apartment_details as d', function ($j) {
                $j->on('a.apartment_id', '=', 'd.apartment_id')
                    ->on('a.city_id', '=', 'd.city_id')
                    ->on('a.lang', '=', 'd.lang');
            })
            ->where(function ($q) use ($threshold) {
                $q->whereNull('d.id')
                    ->orWhere('d.fetched_at', '<', $threshold);
            })
            ->select('a.apartment_id', 'a.city_id', 'a.lang')
            ->limit($batchSize);

        if ($cityId !== null && $cityId !== '') {
            $query->where('a.city_id', $cityId);
        }
        if ($lang !== null && $lang !== '') {
            $query->where('a.lang', $lang);
        }

        $rows = $query->get();
        $runId = Str::uuid()->toString();

        $dispatched = 0;
        foreach ($rows as $i => $row) {
            $cid = $row->city_id ?? $cityId;
            $lid = $row->lang ?? $lang;
            if (! $cid) {
                continue;
            }
            $delay = $i * $delaySeconds;
            dispatch(
                (new SyncApartmentDetailJob($row->apartment_id, $cid, $lid, true))
                    ->delay(now()->addSeconds($delay))
            );
            $dispatched++;
        }

        $ids = $rows->pluck('apartment_id')->values()->all();
        if ($dispatched > 0) {
            Log::info('TrendAgent stale apartment details dispatched', [
                'run_id' => $runId,
                'count' => $dispatched,
                'ids' => $ids,
            ]);
            $this->getOutput()->info('Stale apartment details dispatched: run_id=' . $runId . ', count=' . $dispatched);
        }

        return self::SUCCESS;
    }
}
