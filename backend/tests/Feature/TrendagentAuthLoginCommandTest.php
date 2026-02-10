<?php

namespace Tests\Feature;

use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendagentAuthLoginCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_when_sso_returns_refresh_token(): void
    {
        $this->mock(TrendSsoClient::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->with('+79990000000', 'secret', 'ru')
                ->andReturn([
                    'refresh_token'      => 'rt-123',
                    'raw'                => [],
                    'needs_manual_token' => false,
                ]);
        });

        $this->artisan('trendagent:auth:login', [
            '--phone'    => '+79990000000',
            '--password' => 'secret',
            '--lang'     => 'ru',
        ])
            ->expectsOutputToContain('Logging into TrendAgent SSO')
            ->expectsOutputToContain('TrendAgent SSO login successful.')
            ->assertExitCode(0);

        $session = TaSsoSession::where('provider', 'trendagent')->where('phone', '+79990000000')->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_active);
        $this->assertNotNull($session->refresh_token);
    }

    public function test_login_exits_with_instruction_when_needs_manual_token(): void
    {
        $this->mock(TrendSsoClient::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->andReturn([
                    'refresh_token'      => null,
                    'raw'                => [],
                    'needs_manual_token' => true,
                ]);
        });

        $this->artisan('trendagent:auth:login', [
            '--phone'    => '+79990000000',
            '--password' => 'secret',
        ])
            ->expectsOutputToContain('SSO login returned 403')
            ->expectsOutputToContain('trendagent:auth:save-refresh')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('ta_sso_sessions', ['phone' => '+79990000000']);
    }

    public function test_login_fails_when_phone_not_provided(): void
    {
        $this->artisan('trendagent:auth:login', ['--password' => 'secret'])
            ->expectsOutputToContain('Phone not provided')
            ->assertExitCode(1);
    }

    public function test_login_fails_when_password_not_provided(): void
    {
        $this->artisan('trendagent:auth:login', ['--phone' => '+79990000000'])
            ->expectsOutputToContain('Password not provided')
            ->assertExitCode(1);
    }
}
