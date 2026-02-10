<?php

namespace Tests\Unit\Integrations\TrendAgent;

use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class TrendHttpClientTest extends TestCase
{
    public function test_retries_after_invalidate_on_401(): void
    {
        Config::set('trendagent.default_city_id', 'city-1');
        Config::set('trendagent.default_lang', 'ru');

        $auth = Mockery::mock(TrendAuthService::class);

        $auth->shouldReceive('getAuthToken')
            ->twice()
            ->andReturn('token-old', 'token-new');

        $auth->shouldReceive('invalidate')
            ->once()
            ->with('city-1', 'ru');

        Http::fakeSequence()
            ->push(['error' => 'unauthorized'], 401)
            ->push(['ok' => true], 200);

        $client = new TrendHttpClient($auth);

        $response = $client->get('https://api.trendagent.ru/v4_29/apartments/search/', [
            'some' => 'param',
        ]);

        $this->assertTrue($response->ok());
        $this->assertSame(['ok' => true], $response->json());

        Http::assertSentCount(2);
    }

    public function test_throws_not_authenticated_exception_when_no_session(): void
    {
        Config::set('trendagent.default_city_id', 'city-1');
        Config::set('trendagent.default_lang', 'ru');

        $auth = Mockery::mock(TrendAuthService::class);

        $auth->shouldReceive('getAuthToken')
            ->once()
            ->andThrow(new TrendAgentNotAuthenticatedException('no session'));

        $client = new TrendHttpClient($auth);

        $this->expectException(TrendAgentNotAuthenticatedException::class);

        $client->get('https://api.trendagent.ru/v4_29/apartments/search/', [
            'some' => 'param',
        ]);
    }
}

