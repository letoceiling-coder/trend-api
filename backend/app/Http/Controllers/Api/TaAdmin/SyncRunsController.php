<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Http\Controllers\Controller;
use App\Models\Domain\TrendAgent\TaSyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncRunsController extends Controller
{
    /**
     * GET /api/ta/admin/sync-runs
     * scope (nullable), status (nullable), since_hours (default 24), limit (default 50, max 200)
     */
    public function index(Request $request): JsonResponse
    {
        $sinceHours = (int) $request->input('since_hours', 24);
        $limit = min(200, max(1, (int) $request->input('limit', 50)));
        $scope = $request->input('scope');
        $status = $request->input('status');

        $query = TaSyncRun::query()
            ->where('provider', 'trendagent')
            ->where('started_at', '>=', now()->subHours($sinceHours));

        if ($scope !== null && $scope !== '') {
            $query->where('scope', $scope);
        }
        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        $query->orderByDesc('id');
        $runs = $query->limit($limit)->get();

        $data = $runs->map(function (TaSyncRun $run) {
            return [
                'id' => $run->id,
                'scope' => $run->scope,
                'status' => $run->status,
                'items_fetched' => $run->items_fetched,
                'items_saved' => $run->items_saved,
                'error_message' => $this->sanitizeMessage($run->error_message),
                'created_at' => $run->started_at?->toIso8601String(),
                'finished_at' => $run->finished_at?->toIso8601String(),
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

    private function sanitizeMessage(?string $msg): ?string
    {
        if ($msg === null || $msg === '') {
            return null;
        }
        if (preg_match('/\b(token|password|secret|key|auth)\b/i', $msg)) {
            return '[redacted]';
        }
        return strlen($msg) > 1024 ? substr($msg, 0, 1021) . '...' : $msg;
    }
}
