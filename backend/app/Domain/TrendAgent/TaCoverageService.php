<?php

namespace App\Domain\TrendAgent;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Coverage metrics: blocks/apartments total, with fresh detail, without detail.
 * Fresh boundary: config('trendagent.detail_refresh_hours').
 */
class TaCoverageService
{
    /**
     * @return array{
     *   blocks_total: int,
     *   blocks_with_detail_fresh: int,
     *   blocks_without_detail: int,
     *   apartments_total: int,
     *   apartments_with_detail_fresh: int,
     *   apartments_without_detail: int
     * }
     */
    public function getCoverageData(): array
    {
        $refreshHours = (int) Config::get('trendagent.detail_refresh_hours', 24);
        $threshold = now()->subHours($refreshHours);

        $blocksTotal = (int) DB::table('ta_blocks')->count();

        $blocksWithDetailFresh = (int) DB::table('ta_blocks as b')
            ->join('ta_block_details as d', function ($j) {
                $j->on('b.block_id', '=', 'd.block_id')
                    ->on('b.city_id', '=', 'd.city_id')
                    ->on('b.lang', '=', 'd.lang');
            })
            ->where('d.fetched_at', '>=', $threshold)
            ->count();

        $blocksWithoutDetail = (int) DB::table('ta_blocks as b')
            ->leftJoin('ta_block_details as d', function ($j) {
                $j->on('b.block_id', '=', 'd.block_id')
                    ->on('b.city_id', '=', 'd.city_id')
                    ->on('b.lang', '=', 'd.lang');
            })
            ->whereNull('d.id')
            ->count();

        $apartmentsTotal = (int) DB::table('ta_apartments')->count();

        $apartmentsWithDetailFresh = (int) DB::table('ta_apartments as a')
            ->join('ta_apartment_details as d', function ($j) {
                $j->on('a.apartment_id', '=', 'd.apartment_id')
                    ->on('a.city_id', '=', 'd.city_id')
                    ->on('a.lang', '=', 'd.lang');
            })
            ->where('d.fetched_at', '>=', $threshold)
            ->count();

        $apartmentsWithoutDetail = (int) DB::table('ta_apartments as a')
            ->leftJoin('ta_apartment_details as d', function ($j) {
                $j->on('a.apartment_id', '=', 'd.apartment_id')
                    ->on('a.city_id', '=', 'd.city_id')
                    ->on('a.lang', '=', 'd.lang');
            })
            ->whereNull('d.id')
            ->count();

        return [
            'blocks_total' => $blocksTotal,
            'blocks_with_detail_fresh' => $blocksWithDetailFresh,
            'blocks_without_detail' => $blocksWithoutDetail,
            'apartments_total' => $apartmentsTotal,
            'apartments_with_detail_fresh' => $apartmentsWithDetailFresh,
            'apartments_without_detail' => $apartmentsWithoutDetail,
        ];
    }

    /**
     * Parsed entities counts for stats: blocks (by kind), apartments, details, directories.
     *
     * @return array{
     *   blocks_total: int,
     *   blocks_by_kind: array<string, int>,
     *   block_details_total: int,
     *   apartments_total: int,
     *   apartment_details_total: int,
     *   directories_total: int,
     *   directories_by_type: array<string, int>
     * }
     */
    public function getParsedCounts(): array
    {
        $blocksTotal = (int) DB::table('ta_blocks')->count();
        $blocksByKind = [];
        if (Schema::hasTable('ta_blocks') && Schema::hasColumn('ta_blocks', 'kind')) {
            $rows = DB::table('ta_blocks')
                ->selectRaw("COALESCE(kind, '') as k, COUNT(*) as c")
                ->groupBy(DB::raw("COALESCE(kind, '')"))
                ->get();
            foreach ($rows as $row) {
                $k = $row->k === '' ? '(пусто)' : $row->k;
                $blocksByKind[$k] = (int) $row->c;
            }
        }

        $blockDetailsTotal = Schema::hasTable('ta_block_details') ? (int) DB::table('ta_block_details')->count() : 0;
        $apartmentsTotal = Schema::hasTable('ta_apartments') ? (int) DB::table('ta_apartments')->count() : 0;
        $apartmentDetailsTotal = Schema::hasTable('ta_apartment_details') ? (int) DB::table('ta_apartment_details')->count() : 0;

        $directoriesTotal = 0;
        $directoriesByType = [];
        if (Schema::hasTable('ta_directories')) {
            $directoriesTotal = (int) DB::table('ta_directories')->count();
            $dirRows = DB::table('ta_directories')->select('type')->selectRaw('COUNT(*) as c')->groupBy('type')->get();
            foreach ($dirRows as $row) {
                $directoriesByType[$row->type] = (int) $row->c;
            }
        }

        return [
            'blocks_total' => $blocksTotal,
            'blocks_by_kind' => $blocksByKind,
            'block_details_total' => $blockDetailsTotal,
            'apartments_total' => $apartmentsTotal,
            'apartment_details_total' => $apartmentDetailsTotal,
            'directories_total' => $directoriesTotal,
            'directories_by_type' => $directoriesByType,
        ];
    }
}
