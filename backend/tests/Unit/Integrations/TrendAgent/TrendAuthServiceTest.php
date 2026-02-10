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

    public function test_session_is_invalidated_when_refresh_token_is_bad(): void
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
            ->andThrow(new \RuntimeException('invalid refresh token'));

        $service = new TrendAuthService($sso);

        $this->expectException(TrendAgentNotAuthenticatedException::class);

        try {
            $service->getAuthToken('city-1', 'ru');
        } finally {
            $session->refresh();
            $this->assertNull($session->refresh_token);
        }
    }
}

