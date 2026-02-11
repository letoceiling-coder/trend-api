<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Http\Controllers\Controller;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QualityChecksController extends Controller
{
    /**
     * GET /api/ta/admin/quality-checks
     * scope (nullable), status (nullable pass|warn|fail), since_hours (default 24), limit (default 200)
     */
    public function index(Request $request): JsonResponse
    {
        $sinceHours = (int) $request->input('since_hours', 24);
        $limit = min(500, max(1, (int) $request->input('limit', 200)));
        $scope = $request->input('scope');
        $status = $request->input('status');

        $query = TaDataQualityCheck::query()
            ->where('created_at', '>=', now()->subHours($sinceHours));

        if ($scope !== null && $scope !== '') {
            $query->where('scope', $scope);
        }
        if (in_array($status, [TaDataQualityCheck::STATUS_PASS, TaDataQualityCheck::STATUS_WARN, TaDataQualityCheck::STATUS_FAIL], true)) {
            $query->where('status', $status);
        }

        $checks = $query->orderByDesc('created_at')->limit($limit)->get();

        $data = $checks->map(function (TaDataQualityCheck $c) {
            return [
                'scope' => $c->scope,
                'entity_id' => $c->entity_id,
                'check_name' => $c->check_name,
                'status' => $c->status,
                'message' => $c->message,
                'created_at' => $c->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'count' => $data->count(),
                'since_hours' => $sinceHours,
            ],
        ]);
    }
}
