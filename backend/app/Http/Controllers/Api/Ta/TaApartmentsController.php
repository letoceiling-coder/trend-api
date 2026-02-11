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
     */
    public function show(string $apartment_id): JsonResponse
    {
        $apartment = TaApartment::query()->where('apartment_id', $apartment_id)->first();

        if (! $apartment) {
            return response()->json(['message' => 'Apartment not found'], 404);
        }

        $data = (new ApartmentResource($apartment))->toArray(request());
        $detail = TaApartmentDetail::query()->where('apartment_id', $apartment_id)->first();
        if ($detail) {
            $data['detail'] = (new ApartmentDetailResource($detail))->toArray(request());
        }

        return response()->json([
            'data' => $data,
            'meta' => (object) [],
        ]);
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
