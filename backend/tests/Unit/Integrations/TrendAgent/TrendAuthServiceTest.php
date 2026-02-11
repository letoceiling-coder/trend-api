<?php

namespace Tests\Unit\Integrations\TrendAgent;

use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class TrendAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_token_is_cached_for_240_seconds(): void
    {
        Cache::flush();

        $session = TaSsoSession::create([
            'provider'         => 'trendagent',
            'phone'            => '+79990000000',
            'city_id'          => null,
            'refresh_token'    => 'refresh-123',
            'last_login_at'    => now(),
        ]);

        $sso = Mockery::mock(TrendSsoClient::class);
        $sso->shouldReceive('getAuthToken')
            ->once()
            ->with('refresh-123', 'city-1', 'ru')
            ->andReturn('token-123');

        $service = new TrendAuthService($sso);

        $first = $service->getAuthToken('city-1', 'ru');
        $second = $service->getAuthToken('city-1', 'ru');

        $this->assertSame('token-123', $first);
        $this->assertSame($first, $second);
    }

    public function test_session_is_invalidated_when_sso_returns_403(): void
    {
        Cache::flush();

        $session = TaSsoSession::create([
            'provider'         => 'trendagent',
            'phone'            => '+79990000000',
            'city_id'          => null,
            'refresh_token'    => 'refresh-bad',
            'last_login_at'    => now(),
        ]);

        $sso = Mockery::mock(TrendSsoClient::class);
        $sso->shouldReceive('getAuthToken')
            ->once()
            ->andThrow(new \RuntimeException('refresh token rejected', 403));

        $service = new TrendAuthService($sso);

        $this->expectException(TrendAgentNotAuthenticatedException::class);

        try {
            $service->getAuthToken('city-1', 'ru');
        } finally {
            $session->refresh();
            $this->assertNull($session->refresh_token);
        }
    }

    public function test_session_refresh_token_not_cleared_on_timeout(): void
    {
        Cache::flush();

        $session = TaSsoSession::create([
            'provider'         => 'trendagent',
            'phone'            => '+79990000000',
            'city_id'          => null,
            'refresh_token'    => 'refresh-123',
            'last_login_at'    => now(),
        ]);

        $sso = Mockery::mock(TrendSsoClient::class);
        $sso->shouldReceive('getAuthToken')
            ->once()
            ->andThrow(new \RuntimeException('auth_token request failed: timeout', 0));

        $service = new TrendAuthService($sso);

        $this->expectException(TrendAgentNotAuthenticatedException::class);

        try {
            $service->getAuthToken('city-1', 'ru');
        } finally {
            $session->refresh();
            $this->assertSame('refresh-123', $session->refresh_token);
        }
    }

    public function test_get_auth_token_throws_when_city_id_empty(): void
    {
        $sso = Mockery::mock(TrendSsoClient::class);
        $sso->shouldReceive('getAuthToken')->never();

        $service = new TrendAuthService($sso);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('city_id');

        $service->getAuthToken('', 'ru');
    }

    /** Auto-relogin disabled: ensureAuthenticated throws when no session */
    public function test_auto_relogin_disabled_throws(): void
    {
        Cache::flush();
        Config::set('trendagent.auto_relogin', false);

        $sso = Mockery::mock(TrendSsoClient::class);
        $sso->shouldReceive('getAuthToken')->never();
        $sso->shouldReceive('login')->never();

        $service = new TrendAuthService($sso);

        $this->expectException(TrendAgentNotAuthenticatedException::class);
        $this->expectExceptionMessage('SSO session is not available');

        $service->ensureAuthenticated('city-1', 'ru');
    }

    /** Auto-relogin enabled: no session -> login -> getAuthToken succeeds */
    public function test_auto_relogin_enabled_success_when_session_missing(): void
    {
        Cache::flush();
        Config::set('trendagent.auto_relogin', true);
        Config::set('trendagent.default_phone', '+79991234567');
        Config::set('trendagent.default_password', 'secret');
        Config::set('trendagent.default_city_id', 'city-1');

        $sso = Mockery::mock(TrendSsoClient::class);
        $sso->shouldReceive('login')
            ->once()
            ->with('+79991234567', 'secret', 'ru')
            ->andReturn(['ok' => true, 'refresh_token' => 'new-refresh-token']);
        $sso->shouldReceive('extractAppIdFromRefreshToken')
            ->once()
            ->with('new-refresh-token')
            ->andReturn('app123');
        $sso->shouldReceive('getAuthToken')
            ->once()
            ->with(Mockery::any(), 'city-1', 'ru', 'app123')
            ->andReturn('new-auth-token');

        $service = new TrendAuthService($sso);

        $token = $service->ensureAuthenticated('city-1', 'ru');

        $this->assertSame('new-auth-token', $token);
        $this->assertDatabaseHas('ta_sso_sessions', ['provider' => 'trendagent', 'phone' => '+79991234567']);
    }

    /** Auto-relogin enabled but login returns 403/blocked -> ensureAuthenticated throws */
    public function test_auto_relogin_enabled_fails_when_login_blocked(): void
    {
        Cache::flush();
        Config::set('trendagent.auto_relogin', true);
        Config::set('trendagent.default_phone', '+79991234567');
        Config::set('trendagent.default_password', 'secret');

        $sso = Mockery::mock(TrendSsoClient::class);
        $sso->shouldReceive('login')
            ->once()
            ->andReturn(['ok' => false, 'reason' => 'blocked', 'needs_manual_token' => true]);
        $sso->shouldReceive('getAuthToken')->never();

        $service = new TrendAuthService($sso);

        $this->expectException(TrendAgentNotAuthenticatedException::class);
        $this->expectExceptionMessage('SSO login not saved');

        $service->ensureAuthenticated('city-1', 'ru');
    }
}

