<?php

namespace App\Domain\TrendAgent\Normalizers;

/**
 * Normalize apartment detail payloads (unified + prices) into one structure.
 */
final class ApartmentDetailNormalizer
{
    /**
     * @param  array<string, mixed>  $unifiedPayload  unified_payload from API
     * @param  array<string, mixed>  $otherPayloads  Optional: prices_totals_payload, prices_graph_payload
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

        foreach (['prices_totals_payload', 'prices_graph_payload'] as $key) {
            if (isset($otherPayloads[$key]) && is_array($otherPayloads[$key])) {
                $out[str_replace('_payload', '', $key)] = $otherPayloads[$key];
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
