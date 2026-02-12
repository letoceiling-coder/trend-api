<?php

namespace App\Domain\TrendAgent\Payload;

/**
 * Canonical JSON and payload hash for stable storage and deduplication.
 * No external packages; PHP only.
 */
final class CanonicalPayload
{
    /**
     * Encode payload to canonical JSON: sorted keys, stable number/string representation.
     *
     * @param  mixed  $data  Array or scalar (will be wrapped)
     */
    public static function canonicalJson(mixed $data): string
    {
        $encoded = self::encode($data);
        return json_encode($encoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * SHA256 hash of canonical JSON (hex).
     */
    public static function payloadHash(mixed $data): string
    {
        return hash('sha256', self::canonicalJson($data));
    }

    /**
     * Recursive canonical structure: sort keys, normalize numbers.
     *
     * @return array<int|string, mixed>|scalar
     */
    private static function encode(mixed $data): mixed
    {
        if ($data === null || is_bool($data)) {
            return $data;
        }

        if (is_int($data) || is_float($data)) {
            return self::normalizeNumber($data);
        }

        if (is_string($data)) {
            return $data;
        }

        if (is_array($data)) {
            $isList = array_is_list($data);
            $out = [];
            $keys = array_keys($data);
            if (! $isList) {
                sort($keys, SORT_STRING);
            }
            foreach ($keys as $k) {
                $out[$k] = self::encode($data[$k]);
            }
            return $out;
        }

        return $data;
    }

    private static function normalizeNumber(int|float $n): int|float
    {
        if (is_float($n) && (float) (int) $n === $n) {
            return (int) $n;
        }
        return $n;
    }
}
