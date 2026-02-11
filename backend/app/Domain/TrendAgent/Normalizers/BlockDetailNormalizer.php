<?php

namespace App\Domain\TrendAgent\Normalizers;

/**
 * Normalize block detail payloads (unified + optional endpoints) into one structure.
 */
final class BlockDetailNormalizer
{
    /**
     * @param  array<string, mixed>  $unifiedPayload  unified_payload from API
     * @param  array<string, mixed>  $otherPayloads   Optional: advantages, nearby_places, bank, geo_buildings, apartments_min_price
     * @return array<string, mixed>|null
     */
    public static function normalize(array $unifiedPayload, array $otherPayloads = []): ?array
    {
        if (empty($unifiedPayload) && empty($otherPayloads)) {
            return null;
        }

        $out = [
            'unified' => self::normalizeUnified($unifiedPayload),
        ];

        foreach (['advantages', 'nearby_places', 'bank', 'geo_buildings', 'apartments_min_price'] as $key) {
            if (isset($otherPayloads[$key . '_payload']) && is_array($otherPayloads[$key . '_payload'])) {
                $out[$key] = $otherPayloads[$key . '_payload'];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalizeUnified(array $payload): array
    {
        $out = [];
        foreach ($payload as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $out[$k] = $v;
            } elseif (is_array($v)) {
                $out[$k] = self::normalizeUnified($v);
            }
        }
        return $out;
    }
}
