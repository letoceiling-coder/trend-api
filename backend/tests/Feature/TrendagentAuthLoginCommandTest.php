<?php

namespace Tests\Feature;

use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendagentAuthLoginCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_when_sso_returns_ok_and_refresh_token(): void
    {
        $this->mock(TrendSsoClient::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->with('+79990000000', 'secret', 'ru')
                ->andReturn([
                    'ok'            => true,
                    'refresh_token' => 'rt-123',
                    'app_id'        => '66d84f584c0168b8ccd281c3',
                    'status'        => 200,
                ]);
        });

        $this->artisan('trendagent:auth:login', [
            '--phone'    => '+79990000000',
            '--password' => 'secret',
            '--lang'     => 'ru',
        ])
            ->expectsOutputToContain('Logging into TrendAgent SSO')
            ->expectsOutputToContain('TrendAgent SSO login successful.')
            ->expectsOutputToContain('Has refresh_token: yes')
            ->assertExitCode(0);

        $session = TaSsoSession::where('provider', 'trendagent')->where('phone', '+79990000000')->first();
        $this->assertNotNull($session);
        $this->assertTrue($session->is_active);
        $this->assertNotNull($session->refresh_token);
        $this->assertSame('rt-123', $session->refresh_token);
    }

    public function test_login_exits_with_instruction_when_needs_manual_token(): void
    {
        $this->mock(TrendSsoClient::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->andReturn([
                    'ok'                  => false,
                    'needs_manual_token'  => true,
                    'status'              => 403,
                    'reason'              => 'forbidden_no_token',
                ]);
        });

        $this->artisan('trendagent:auth:login', [
            '--phone'    => '+79990000000',
            '--password' => 'secret',
        ])
            ->expectsOutputToContain('SSO login NOT saved')
            ->expectsOutputToContain('trendagent:auth:save-refresh')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('ta_sso_sessions', ['phone' => '+79990000000']);
    }

    /** When login returns ok=true but refresh_token empty, do NOT save and exit 1. */
    public function test_login_exit_1_and_no_token_saved_when_ok_true_but_refresh_token_empty(): void
    {
        $this->mock(TrendSsoClient::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->andReturn([
                    'ok'            => true,
                    'refresh_token' => '',
                    'app_id'        => '66d84f584c0168b8ccd281c3',
                    'status'        => 200,
                ]);
        });

        $this->artisan('trendagent:auth:login', [
            '--phone'    => '+79990000000',
            '--password' => 'secret',
        ])
            ->expectsOutputToContain('SSO login NOT saved')
            ->assertExitCode(1);

        $session = TaSsoSession::where('provider', 'trendagent')->where('phone', '+79990000000')->first();
        $this->assertNull($session);
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
