<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guard for /api/ta-ui/* refresh endpoints.
 * Allow if: (1) valid X-Internal-Key, or (2) client IP in TA_UI_ALLOW_IPS.
 * Otherwise 401. Do not log tokens.
 */
class TaUiGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('internal.api_key');
        if ($key !== null && $key !== '' && $request->header('X-Internal-Key') === $key) {
            return $next($request);
        }

        $allowIps = config('trendagent.ta_ui_allow_ips', []);
        if (is_array($allowIps) && $allowIps !== []) {
            $clientIp = $request->ip();
            if ($clientIp !== null && in_array($clientIp, $allowIps, true)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
