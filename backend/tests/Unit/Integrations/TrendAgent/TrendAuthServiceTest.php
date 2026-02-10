<?php

namespace Tests\Unit\Integrations\TrendAgent;

use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
}

