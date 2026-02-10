<?php

namespace Tests\Feature;

use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrendagentAuthLoginCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_command_accepts_manual_refresh_token_when_needed(): void
    {
        $this->mock(TrendSsoClient::class, function ($mock) {
            $mock->shouldReceive('login')
                ->once()
                ->andReturn([
                    'refresh_token'      => null,
                    'raw'                => ['ok' => true],
                    'needs_manual_token' => true,
                ]);
        });

        $this->artisan('trendagent:auth:login', [
            'phone'    => '+79990000000',
            'password' => 'secret',
            '--lang'   => 'ru',
        ])
            ->expectsOutputToContain('Logging into TrendAgent SSO as')
            ->expectsQuestion('Paste the refresh_token here (input will be hidden):', 'MANUAL_REFRESH_TOKEN')
            ->expectsOutput('TrendAgent SSO login successful.')
            ->assertExitCode(0);

        $session = TaSsoSession::where('provider', 'trendagent')
            ->where('phone', '+79990000000')
            ->first();

        $this->assertNotNull($session);
        $this->assertTrue($session->is_active);
        $this->assertNotNull($session->refresh_token);
    }
}

