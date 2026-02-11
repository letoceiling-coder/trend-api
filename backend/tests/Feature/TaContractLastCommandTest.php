<?php

namespace Tests\Feature;

use App\Models\Domain\TrendAgent\TaContractChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaContractLastCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ta_contract_last_outputs_table(): void
    {
        TaContractChange::create([
            'endpoint' => '/v4_29/blocks/search',
            'city_id' => '58c665588b6aa52311afa01b',
            'lang' => 'ru',
            'old_payload_hash' => 'aaa111',
            'new_payload_hash' => 'bbb222',
            'old_top_keys' => ['items'],
            'new_top_keys' => ['data', 'items'],
            'old_data_keys' => null,
            'new_data_keys' => null,
            'payload_cache_id' => 1,
            'detected_at' => now(),
        ]);

        $this->artisan('ta:contract:last')
            ->assertExitCode(0);

        $this->artisan('ta:contract:last', ['--limit' => 5])
            ->assertExitCode(0);
    }

    public function test_ta_contract_last_no_changes(): void
    {
        $this->artisan('ta:contract:last')
            ->expectsOutput('No contract changes recorded yet.')
            ->assertExitCode(0);
    }
}
