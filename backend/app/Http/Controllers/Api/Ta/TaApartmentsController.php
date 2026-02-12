<?php

namespace App\Http\Controllers\Api\Ta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ta\IndexApartmentsRequest;
use App\Http\Resources\Ta\ApartmentDetailResource;
use App\Http\Resources\Ta\ApartmentResource;
use App\Jobs\TrendAgent\SyncApartmentDetailJob;
use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaApartmentDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaApartmentsController extends Controller
{
    /**
     * List apartments (from ta_apartments).
     */
    public function index(IndexApartmentsRequest $request): JsonResponse
    {
        $cityId = $request->getCityId();
        $lang = $request->getLang();
        $count = $request->getCount();
        $offset = $request->getOffset();
        $sort = $request->getSort() ?? 'price';
        $sortOrder = $request->getSortOrder();
        $blockId = $request->getBlockId();

        $query = TaApartment::query()
            ->where('city_id', $cityId)
            ->where('lang', $lang);

        if ($blockId !== null) {
            $query->where('block_id', $blockId);
        }

        $total = $query->count();

        $allowedSort = ['price', 'rooms', 'area_total', 'floor', 'title', 'fetched_at', 'apartment_id'];
        if (in_array($sort, $allowedSort, true)) {
            $query->orderBy($sort, $sortOrder);
        }

        $items = $query->skip($offset)->take($count)->get();

        return response()->json([
            'data' => ApartmentResource::collection($items),
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
     * Show one apartment by apartment_id; include detail from ta_apartment_details if present.
     * Always returns data.normalized and meta.source. RAW only when debug=1 and (INTERNAL_API_KEY or local).
     */
    public function show(Request $request, string $apartment_id): JsonResponse
    {
        $apartment = TaApartment::query()->where('apartment_id', $apartment_id)->first();

        if (! $apartment) {
            return response()->json(['message' => 'Apartment not found'], 404);
        }

        $data = $apartment->normalized ?? (new ApartmentResource($apartment))->toArray($request);
        $detail = TaApartmentDetail::query()->where('apartment_id', $apartment_id)->first();
        if ($detail) {
            $data['detail'] = $detail->normalized ?? (new ApartmentDetailResource($detail))->toArray($request);
        }

        $meta = [
            'source' => [
                'fetched_at' => $apartment->fetched_at?->toIso8601String(),
                'payload_hash' => $apartment->payload_hash,
            ],
        ];

        if ($this->allowRawInResponse($request)) {
            $data['raw'] = $apartment->raw;
            if ($detail) {
                $data['detail']['raw'] = [
                    'unified_payload' => $detail->unified_payload,
                    'prices_totals_payload' => $detail->prices_totals_payload,
                    'prices_graph_payload' => $detail->prices_graph_payload,
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
     * POST refresh: dispatch SyncApartmentDetailJob for the given apartment_id.
     */
    public function refresh(string $apartment_id): JsonResponse
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
