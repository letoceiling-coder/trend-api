<?php

namespace App\Http\Controllers\Api\TaUi;

use App\Http\Controllers\Controller;
use App\Jobs\TrendAgent\SyncApartmentDetailJob;
use App\Jobs\TrendAgent\SyncBlockDetailJob;
use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaBlock;
use Illuminate\Http\JsonResponse;

/**
 * Proxy for frontend: POST refresh without X-Internal-Key.
 * Dispatches the same jobs as internal /api/ta/.../refresh.
 * Protect with throttle (e.g. throttle:10,1).
 */
class TaUiRefreshController extends Controller
{
    public function refreshBlock(string $block_id): JsonResponse
    {
        $block = TaBlock::query()->where('block_id', $block_id)->first();

        if (! $block) {
            return response()->json(['message' => 'Block not found'], 404);
        }

        $cityId = $block->city_id ?? config('trendagent.default_city_id');
        $lang = $block->lang ?? config('trendagent.default_lang', 'ru');

        dispatch(new SyncBlockDetailJob($block_id, $cityId, $lang, true));

        return response()->json([
            'data' => ['queued' => true],
            'meta' => [
                'job' => 'SyncBlockDetailJob',
                'id' => $block_id,
            ],
        ]);
    }

    public function refreshApartment(string $apartment_id): JsonResponse
    {
        $apartment = TaApartment::query()->where('apartment_id', $apartment_id)->first();

        if (! $apartment) {
            return response()->json(['message' => 'Apartment not found'], 404);
        }

        $cityId = $apartment->city_id ?? config('trendagent.default_city_id');
        $lang = $apartment->lang ?? config('trendagent.default_lang', 'ru');

        dispatch(new SyncApartmentDetailJob($apartment_id, $cityId, $lang, true));

        return response()->json([
            'data' => ['queued' => true],
            'meta' => [
                'job' => 'SyncApartmentDetailJob',
                'id' => $apartment_id,
            ],
        ]);
    }
}
