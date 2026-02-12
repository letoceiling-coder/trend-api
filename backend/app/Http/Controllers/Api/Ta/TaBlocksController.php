<?php

namespace App\Http\Controllers\Api\Ta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ta\IndexBlocksRequest;
use App\Http\Resources\Ta\BlockDetailResource;
use App\Http\Resources\Ta\BlockResource;
use App\Jobs\TrendAgent\SyncBlockDetailJob;
use App\Models\Domain\TrendAgent\TaBlock;
use App\Models\Domain\TrendAgent\TaBlockDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaBlocksController extends Controller
{
    /**
     * List blocks (from ta_blocks).
     */
    public function index(IndexBlocksRequest $request): JsonResponse
    {
        $cityId = $request->getCityId();
        $lang = $request->getLang();
        $count = $request->getCount();
        $offset = $request->getOffset();
        $sort = $request->getSort() ?? 'min_price';
        $sortOrder = $request->getSortOrder();

        $query = TaBlock::query()
            ->where('city_id', $cityId)
            ->where('lang', $lang);

        $total = $query->count();

        $allowedSort = ['min_price', 'max_price', 'title', 'fetched_at', 'block_id'];
        if (in_array($sort, $allowedSort, true)) {
            $query->orderBy($sort, $sortOrder);
        }

        $items = $query->skip($offset)->take($count)->get();

        $data = $items->map(fn (TaBlock $b) => $b->normalized ?? (new BlockResource($b))->toArray(request()));

        return response()->json([
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => $items->count(),
                    'offset' => $offset,
                ],
            ],
        ]);
    }

    /**
     * Show one block by block_id; include detail from ta_block_details if present.
     * Always returns data.normalized and meta.source. RAW only when debug=1 AND valid X-Internal-Key (no raw without key).
     */
    public function show(Request $request, string $block_id): JsonResponse
    {
        $block = TaBlock::query()->where('block_id', $block_id)->first();

        if (! $block) {
            return response()->json(['message' => 'Block not found'], 404);
        }

        $data = $block->normalized ?? (new BlockResource($block))->toArray($request);
        $detail = TaBlockDetail::query()->where('block_id', $block_id)->first();
        if ($detail) {
            $data['detail'] = $detail->normalized ?? (new BlockDetailResource($detail))->toArray($request);
        }

        $meta = [
            'source' => [
                'fetched_at' => $block->fetched_at?->toIso8601String(),
                'payload_hash' => $block->payload_hash,
            ],
        ];

        if ($this->allowRawInResponse($request)) {
            $data['raw'] = $block->raw_data;
            if ($detail) {
                $data['detail']['raw'] = [
                    'unified_payload' => $detail->unified_payload,
                    'advantages_payload' => $detail->advantages_payload,
                    'nearby_places_payload' => $detail->nearby_places_payload,
                    'bank_payload' => $detail->bank_payload,
                    'geo_buildings_payload' => $detail->geo_buildings_payload,
                    'apartments_min_price_payload' => $detail->apartments_min_price_payload,
                ];
            }
        }

        return response()->json([
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    /** RAW only when debug=1 AND valid X-Internal-Key. Without key, debug is ignored (no raw). */
    private function allowRawInResponse(Request $request): bool
    {
        if ($request->query('debug') !== '1') {
            return false;
        }
        $key = config('internal.api_key');
        return $key !== null && $key !== '' && $request->header('X-Internal-Key') === $key;
    }

    /**
     * POST refresh: dispatch SyncBlockDetailJob for the given block_id.
     */
    public function refresh(string $block_id): JsonResponse
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
}
