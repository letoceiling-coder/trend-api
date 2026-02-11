<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('internal.api_key');

        if ($key === null || $key === '') {
            return response()->json(['message' => 'Internal API key not configured'], 403);
        }

        if ($request->header('X-Internal-Key') !== $key) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
