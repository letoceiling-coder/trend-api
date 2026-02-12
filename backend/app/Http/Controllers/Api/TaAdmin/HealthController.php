<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Domain\TrendAgent\TaHealthService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /api/ta/admin/health
     * sync: last_success_at per scope; contract_changes_last_24h_count; quality_fail_last_24h_count; queue info
     */
    public function index(TaHealthService $health): JsonResponse
    {
        return response()->json([
            'data' => $health->getHealthData(),
            'meta' => [],
        ]);
    }
}
