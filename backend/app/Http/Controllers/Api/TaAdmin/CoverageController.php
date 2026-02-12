<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Domain\TrendAgent\TaCoverageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class CoverageController extends Controller
{
    /**
     * GET /api/ta/admin/coverage
     * Coverage metrics: blocks/apartments total, with_detail_fresh, without_detail.
     */
    public function index(TaCoverageService $coverage): JsonResponse
    {
        return response()->json([
            'data' => $coverage->getCoverageData(),
            'meta' => [],
        ]);
    }
}
