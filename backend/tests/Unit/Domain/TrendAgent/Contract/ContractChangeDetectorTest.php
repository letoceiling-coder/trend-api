<?php

namespace Tests\Unit\Domain\TrendAgent\Contract;

use App\Domain\TrendAgent\Contract\ContractChangeDetector;
use App\Models\Domain\TrendAgent\TaContractChange;
use App\Models\Domain\TrendAgent\TaContractState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractChangeDetectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_hash_creates_contract_change_record(): void
    {
        TaContractState::create([
            'endpoint' => '/v4_29/blocks/search',
            'city_id' => 'city1',
            'lang' => 'ru',
            'last_payload_hash' => 'oldhash123',
            'last_top_keys' => ['a', 'b'],
            'last_data_keys' => ['items'],
            'updated_at' => now(),
        ]);

        ContractChangeDetector::detect(
            '/v4_29/blocks/search',
            'city1',
            'ru',
            ['data' => ['results' => []], 'meta' => []],
            'newhash456',
            99,
        );

        $this->assertDatabaseHas('ta_contract_changes', [
            'endpoint' => '/v4_29/blocks/search',
            'city_id' => 'city1',
            'lang' => 'ru',
            'old_payload_hash' => 'oldhash123',
            'new_payload_hash' => 'newhash456',
            'payload_cache_id' => 99,
        ]);
        $change = TaContractChange::first();
        $this->assertSame(['a', 'b'], $change->old_top_keys);
        $this->assertSame(['data', 'meta'], $change->new_top_keys);
        $this->assertSame(['results'], $change->new_data_keys);
    }

    public function test_same_hash_does_not_create_change(): void
    {
        TaContractState::create([
            'endpoint' => '/v4_29/unit_measurements',
            'city_id' => null,
            'lang' => 'ru',
            'last_payload_hash' => 'samehash',
            'updated_at' => now(),
        ]);

        ContractChangeDetector::detect(
            '/v4_29/unit_measurements',
            null,
            'ru',
            ['_id' => 'u1', 'name' => 'Unit'],
            'samehash',
            null,
        );

        $this->assertDatabaseCount('ta_contract_changes', 0);
    }

    public function test_keys_saved_correctly(): void
    {
        TaContractState::create([
            'endpoint' => '/api/test',
            'city_id' => 'c1',
            'lang' => 'en',
            'last_payload_hash' => 'previous',
            'last_top_keys' => ['old_key'],
            'last_data_keys' => ['list'],
            'updated_at' => now(),
        ]);

        ContractChangeDetector::detect(
            '/api/test',
            'c1',
            'en',
            ['data' => ['list' => [], 'total' => 0], 'meta' => []],
            'new_hash_here',
            1,
        );

        $c = TaContractChange::first();
        $this->assertNotNull($c);
        $this->assertSame(['old_key'], $c->old_top_keys);
        $this->assertSame(['data', 'meta'], $c->new_top_keys);
        $this->assertSame(['list'], $c->old_data_keys);
        $this->assertSame(['list', 'total'], $c->new_data_keys);
    }

    public function test_null_endpoint_skips_detection(): void
    {
        ContractChangeDetector::detect(null, 'c1', 'ru', ['x' => 1], 'hash1', null);
        $this->assertDatabaseCount('ta_contract_state', 0);
        $this->assertDatabaseCount('ta_contract_changes', 0);
    }
}
