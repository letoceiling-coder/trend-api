<?php

namespace App\Http\Controllers\Api\Ta;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ta\IndexUnitMeasurementsRequest;
use App\Http\Resources\Ta\UnitMeasurementResource;
use App\Models\Domain\TrendAgent\TaUnitMeasurement;
use Illuminate\Http\JsonResponse;

class TaUnitMeasurementsController extends Controller
{
    /**
     * List unit measurements (from ta_unit_measurements).
     */
    public function index(IndexUnitMeasurementsRequest $request): JsonResponse
    {
        $count = $request->getCount();
        $offset = $request->getOffset();

        $query = TaUnitMeasurement::query()->orderBy('id');
        $total = $query->count();
        $items = $query->skip($offset)->take($count)->get();

        return response()->json([
            'data' => UnitMeasurementResource::collection($items),
            'meta' => [
                'pagination' => [
                    'total' => $total,
                    'count' => $items->count(),
                    'offset' => $offset,
                ],
            ],
        ]);
    }
}
