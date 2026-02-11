<?php

namespace App\Domain\TrendAgent\Contract;

use App\Models\Domain\TrendAgent\TaContractChange;
use App\Models\Domain\TrendAgent\TaContractState;

/**
 * Detect API contract changes by comparing payload hashes per endpoint+city_id+lang.
 * Records changes in ta_contract_changes; does not log or expose payload/secrets.
 */
final class ContractChangeDetector
{
    /**
     * @param  array<string, mixed>  $payload  Decoded payload (for keys extraction only)
     */
    public static function detect(
        ?string $endpoint,
        ?string $cityId,
        ?string $lang,
        array $payload,
        string $payloadHash,
        ?int $payloadCacheId = null,
    ): void {
        if ($endpoint === null || $endpoint === '') {
            return;
        }

        $state = TaContractState::firstOrNew([
            'endpoint' => $endpoint,
            'city_id' => $cityId,
            'lang' => $lang,
        ]);

        $oldHash = $state->exists ? $state->last_payload_hash : null;

        if ($oldHash === $payloadHash) {
            if (! $state->exists) {
                $state->last_payload_hash = $payloadHash;
                $state->last_top_keys = self::topLevelKeys($payload);
                $state->last_data_keys = self::dataKeys($payload);
                $state->updated_at = now();
                $state->save();
            }
            return;
        }

        $newTopKeys = self::topLevelKeys($payload);
        $newDataKeys = self::dataKeys($payload);
        $oldTopKeys = $state->exists ? $state->last_top_keys : null;
        $oldDataKeys = $state->exists ? $state->last_data_keys : null;

        if ($state->exists) {
            \Illuminate\Support\Facades\DB::table('ta_contract_changes')->insertOrIgnore([
                'endpoint' => $endpoint,
                'city_id' => $cityId,
                'lang' => $lang,
                'old_payload_hash' => $oldHash,
                'new_payload_hash' => $payloadHash,
                'old_top_keys' => $oldTopKeys !== null ? json_encode($oldTopKeys) : null,
                'new_top_keys' => json_encode($newTopKeys),
                'old_data_keys' => $oldDataKeys !== null ? json_encode($oldDataKeys) : null,
                'new_data_keys' => $newDataKeys !== null ? json_encode($newDataKeys) : null,
                'payload_cache_id' => $payloadCacheId,
                'detected_at' => now(),
            ]);
        }

        $state->last_payload_hash = $payloadHash;
        $state->last_top_keys = $newTopKeys;
        $state->last_data_keys = $newDataKeys;
        $state->updated_at = now();
        $state->save();
    }

    /**
     * Top-level keys of payload (sorted).
     *
     * @return array<int, string>
     */
    private static function topLevelKeys(array $payload): array
    {
        $keys = array_keys($payload);
        sort($keys, SORT_STRING);
        return $keys;
    }

    /**
     * Keys of payload['data'] if present and array (one level).
     *
     * @return array<int, string>|null
     */
    private static function dataKeys(array $payload): ?array
    {
        if (! isset($payload['data']) || ! is_array($payload['data'])) {
            return null;
        }
        $keys = array_keys($payload['data']);
        sort($keys, SORT_STRING);
        return $keys;
    }

}
