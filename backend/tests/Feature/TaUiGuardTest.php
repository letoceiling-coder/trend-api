<?php

namespace Tests\Feature;

use App\Models\Domain\TrendAgent\TaApartment;
use App\Models\Domain\TrendAgent\TaBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TaUiGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('internal.api_key', 'secret-internal');
    }

    public function test_ta_ui_refresh_401_without_key_and_without_allowlist(): void
    {
        Config::set('trendagent.ta_ui_allow_ips', []);
        TaBlock::create([
            'block_id' => 'b1',
            'city_id' => 'c1',
            'lang' => 'ru',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->postJson('/api/ta-ui/blocks/b1/refresh');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_ta_ui_refresh_200_with_key(): void
    {
        Config::set('trendagent.ta_ui_allow_ips', []);
        TaBlock::create([
            'block_id' => 'b1',
            'city_id' => 'c1',
            'lang' => 'ru',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->postJson('/api/ta-ui/blocks/b1/refresh', [], [
            'X-Internal-Key' => 'secret-internal',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.queued', true);
    }

    public function test_ta_ui_refresh_200_with_allowlist_ip(): void
    {
        Config::set('trendagent.ta_ui_allow_ips', ['127.0.0.1', '10.0.0.1']);
        TaBlock::create([
            'block_id' => 'b1',
            'city_id' => 'c1',
            'lang' => 'ru',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->postJson('/api/ta-ui/blocks/b1/refresh', [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.queued', true);
    }

    public function test_ta_ui_refresh_401_when_ip_not_in_allowlist(): void
    {
        Config::set('trendagent.ta_ui_allow_ips', ['10.0.0.1']);
        TaBlock::create([
            'block_id' => 'b1',
            'city_id' => 'c1',
            'lang' => 'ru',
            'fetched_at' => now(),
            'raw' => '{}',
        ]);

        $response = $this->postJson('/api/ta-ui/blocks/b1/refresh', [], [
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_ta_ui_apartments_refresh_200_with_key(): void
    {
        Config::set('trendagent.ta_ui_allow_ips', []);
        TaApartment::create([
            'apartment_id' => 'a1',
            'city_id' => 'c1',
            'lang' => 'ru',
            'fetched_at' => now(),
        ]);

        $response = $this->postJson('/api/ta-ui/apartments/a1/refresh', [], [
            'X-Internal-Key' => 'secret-internal',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.queued', true);
    }
}
