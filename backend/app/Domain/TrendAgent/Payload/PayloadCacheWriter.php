<?php

namespace App\Domain\TrendAgent\Payload;

use App\Domain\TrendAgent\Contract\ContractChangeDetector;
use App\Models\Domain\TrendAgent\TaPayloadCache;
use Illuminate\Support\Facades\Log;

/**
 * Write to ta_payload_cache with endpoint, http_status, payload_hash (canonical).
 */
final class PayloadCacheWriter
{
    public static function create(
        string $scope,
        ?string $externalId,
        ?string $endpoint,
        ?int $httpStatus,
        string|array $payload,
        ?string $cityId = null,
        ?string $lang = null,
        ?string $provider = 'trendagent',
    ): TaPayloadCache {
        $payloadString = is_string($payload) ? $payload : json_encode($payload);
        $decoded = json_decode($payloadString, true);
        $payloadHash = $decoded !== null ? CanonicalPayload::payloadHash($decoded) : hash('sha256', $payloadString);

        $cache = TaPayloadCache::create([
            'provider' => $provider,
            'scope' => $scope,
            'external_id' => $externalId,
            'endpoint' => $endpoint,
            'http_status' => $httpStatus,
            'city_id' => $cityId,
            'lang' => $lang,
            'payload' => $payloadString,
            'payload_hash' => $payloadHash,
            'fetched_at' => now(),
        ]);

        if ($decoded !== null && is_array($decoded)) {
            try {
                ContractChangeDetector::detect(
                    $endpoint,
                    $cityId,
                    $lang,
                    $decoded,
                    $payloadHash,
                    $cache->id,
                );
            } catch (\Throwable $e) {
                Log::warning('ContractChangeDetector failed (no payload/secrets in log)', [
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $cache;
    }

    public static function endpointFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        return $path !== null && $path !== '' ? $path : $url;
    }
}
