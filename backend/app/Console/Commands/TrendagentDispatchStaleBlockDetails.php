<?php

namespace App\Console\Commands;

use App\Jobs\TrendAgent\SyncBlockDetailJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrendagentDispatchStaleBlockDetails extends Command
{
    protected $signature = 'trendagent:dispatch:stale-block-details
                            {--city= : City ID (defaults to config; empty = all cities)}
                            {--lang= : Language (defaults to config; empty = all langs)}';

    protected $description = 'Dispatch SyncBlockDetailJob for blocks with missing or stale ta_block_details (batch)';

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

        $query = DB::table('ta_blocks as b')
            ->leftJoin('ta_block_details as d', function ($j) {
                $j->on('b.block_id', '=', 'd.block_id')
                    ->on('b.city_id', '=', 'd.city_id')
                    ->on('b.lang', '=', 'd.lang');
            })
            ->where(function ($q) use ($threshold) {
                $q->whereNull('d.id')
                    ->orWhere('d.fetched_at', '<', $threshold);
            })
            ->select('b.block_id', 'b.city_id', 'b.lang')
            ->limit($batchSize);

        if ($cityId !== null && $cityId !== '') {
            $query->where('b.city_id', $cityId);
        }
        if ($lang !== null && $lang !== '') {
            $query->where('b.lang', $lang);
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
                (new SyncBlockDetailJob($row->block_id, $cid, $lid, true))
                    ->delay(now()->addSeconds($delay))
            );
            $dispatched++;
        }

        $ids = $rows->pluck('block_id')->values()->all();
        if ($dispatched > 0) {
            Log::info('TrendAgent stale block details dispatched', [
                'run_id' => $runId,
                'count' => $dispatched,
                'ids' => $ids,
            ]);
            $this->getOutput()->info('Stale block details dispatched: run_id=' . $runId . ', count=' . $dispatched);
        }

        return self::SUCCESS;
    }
}
