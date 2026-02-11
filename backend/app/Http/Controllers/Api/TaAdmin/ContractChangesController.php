<?php

namespace App\Http\Controllers\Api\TaAdmin;

use App\Http\Controllers\Controller;
use App\Models\Domain\TrendAgent\TaContractChange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractChangesController extends Controller
{
    /**
     * GET /api/ta/admin/contract-changes
     * endpoint (nullable), since_hours (default 168), limit (default 100)
     */
    public function index(Request $request): JsonResponse
    {
        $sinceHours = (int) $request->input('since_hours', 168);
        $limit = min(500, max(1, (int) $request->input('limit', 100)));
        $endpoint = $request->input('endpoint');

        $query = TaContractChange::query()
            ->where('detected_at', '>=', now()->subHours($sinceHours));

        if ($endpoint !== null && $endpoint !== '') {
            $query->where('endpoint', 'like', '%' . $endpoint . '%');
        }

        $changes = $query->orderByDesc('detected_at')->limit($limit)->get();

        $data = $changes->map(function (TaContractChange $c) {
            return [
                'endpoint' => $c->endpoint,
                'city_id' => $c->city_id,
                'lang' => $c->lang,
                'old_hash' => $c->old_payload_hash,
                'new_hash' => $c->new_payload_hash,
                'old_top_keys' => $c->old_top_keys,
                'new_top_keys' => $c->new_top_keys,
                'detected_at' => $c->detected_at?->toIso8601String(),
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
