<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Domain\TrendAgent\TaCoverageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /**
     * GET /api/ta/admin/stats
     * Parsed entities counts: blocks (by kind), apartments, details, directories.
     */
    public function index(TaCoverageService $coverage): JsonResponse
    {
        return response()->json([
            'data' => $coverage->getParsedCounts(),
            'meta' => [
                'note' => 'Паркинги, коммерция, подрядчики, участки, проекты домов не в отдельных таблицах — только ta_blocks и ta_apartments; тип блока в kind.',
            ],
        ]);
    }
}
