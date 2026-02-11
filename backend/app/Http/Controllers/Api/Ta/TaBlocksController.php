<?php

namespace App\Http\Controllers\Api\Ta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ta\IndexBlocksRequest;
use App\Http\Resources\Ta\BlockDetailResource;
use App\Http\Resources\Ta\BlockResource;
use App\Models\Domain\TrendAgent\TaBlock;
use App\Models\Domain\TrendAgent\TaBlockDetail;
use Illuminate\Http\JsonResponse;

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

        return response()->json([
            'data' => BlockResource::collection($items),
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
     */
    public function show(string $block_id): JsonResponse
    {
        $block = TaBlock::query()->where('block_id', $block_id)->first();

        if (! $block) {
            return response()->json(['message' => 'Block not found'], 404);
        }

        $data = (new BlockResource($block))->toArray(request());
        $detail = TaBlockDetail::query()->where('block_id', $block_id)->first();
        if ($detail) {
            $data['detail'] = (new BlockDetailResource($detail))->toArray(request());
        }

        return response()->json([
            'data' => $data,
            'meta' => (object) [],
        ]);
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
