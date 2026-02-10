<?php

namespace Tests\Feature;

use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use RuntimeException;
use Tests\TestCase;

class TrendagentAuthCheckCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_exit_0_when_get_auth_token_returns_token(): void
    {
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');

        TaSsoSession::create([
            'provider'      => 'trendagent',
            'phone'         => '+79990000000',
            'city_id'       => '58c665588b6aa52311afa01b',
            'refresh_token' => 'rt-123',
            'last_login_at' => now(),
        ]);

        $authToken = 'eyJhbG.eyJleHAiOjE2MDAwMDAwMDAsImlhdCI6MTU5OTk5OTk5OX0.S';
        $this->mock(TrendAuthService::class, function ($mock) use ($authToken) {
            $mock->shouldReceive('getAuthTokenForSession')
                ->once()
                ->andReturn($authToken);
        });

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('AUTH OK')
            ->expectsOutputToContain('token_len:')
            ->assertExitCode(0);
    }

    public function test_check_exit_1_and_prints_status_and_body_preview_on_403(): void
    {
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');

        TaSsoSession::create([
            'provider'      => 'trendagent',
            'phone'         => '+79990000000',
            'city_id'       => '58c665588b6aa52311afa01b',
            'refresh_token' => 'rt-123',
            'last_login_at' => now(),
        ]);

        $prev = new RuntimeException('refresh token rejected — {"error":"Forbidden resource"}', 403);
        $this->mock(TrendAuthService::class, function ($mock) use ($prev) {
            $mock->shouldReceive('getAuthTokenForSession')
                ->once()
                ->andThrow(new TrendAgentNotAuthenticatedException('SSO session invalid', 0, $prev));
        });

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('AUTH FAIL')
            ->expectsOutputToContain('reason:')
            ->expectsOutputToContain('http_status: 403')
            ->expectsOutputToContain('body_preview:')
            ->assertExitCode(1);
    }

    public function test_check_fail_when_no_session(): void
    {
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('AUTH FAIL')
            ->expectsOutputToContain('no session')
            ->assertExitCode(1);
    }

    public function test_check_fail_when_city_id_missing(): void
    {
        Config::set('trendagent.default_city_id', '');
        Config::set('trendagent.default_lang', 'ru');

        TaSsoSession::create([
            'provider'      => 'trendagent',
            'phone'         => '+79990000000',
            'city_id'       => null,
            'refresh_token' => 'rt-123',
            'last_login_at' => now(),
        ]);

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('AUTH FAIL')
            ->expectsOutputToContain('city_id missing')
            ->assertExitCode(1);
    }
}
