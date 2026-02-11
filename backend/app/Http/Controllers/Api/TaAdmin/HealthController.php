<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Http\Controllers\Controller;
use App\Models\Domain\TrendAgent\TaContractChange;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;

class HealthController extends Controller
{
    /**
     * GET /api/ta/admin/health
     * sync: last_success_at per scope; contract_changes_last_24h_count; quality_fail_last_24h_count; queue info
     */
    public function index(): JsonResponse
    {
        $scopes = ['blocks', 'apartments', 'block_detail', 'apartment_detail'];
        $sync = [];
        foreach ($scopes as $scope) {
            $last = TaSyncRun::query()
                ->where('provider', 'trendagent')
                ->where('scope', $scope)
                ->where('status', 'success')
                ->orderByDesc('finished_at')
                ->first();
            $sync[$scope] = [
                'last_success_at' => $last?->finished_at?->toIso8601String(),
            ];
        }

        $contractChangesLast24h = TaContractChange::query()
            ->where('detected_at', '>=', now()->subHours(24))
            ->count();

        $qualityFailLast24h = TaDataQualityCheck::query()
            ->where('status', 'fail')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        $queueConnection = Config::get('queue.default', 'sync');
        $queueName = config('trendagent.queue.queue_name', 'default');

        return response()->json([
            'data' => [
                'sync' => $sync,
                'contract_changes_last_24h_count' => $contractChangesLast24h,
                'quality_fail_last_24h_count' => $qualityFailLast24h,
                'queue' => [
                    'connection' => $queueConnection,
                    'queue_name' => $queueName,
                ],
            ],
            'meta' => [],
        ]);
    }
}
