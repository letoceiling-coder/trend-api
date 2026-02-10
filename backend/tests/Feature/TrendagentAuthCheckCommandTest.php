<?php

namespace Tests\Feature;

use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrendagentAuthCheckCommandTest extends TestCase
{
    public function test_check_ok_when_api_returns_200_json(): void
    {
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');
        Config::set('trendagent.api.core', 'https://api.trendagent.ru');

        $this->mock(TrendHttpClient::class, function ($mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response('[]', 200));
        });

        $this->artisan('trendagent:auth:check')
            ->expectsOutput('OK')
            ->assertExitCode(0);
    }

    public function test_check_not_authenticated_when_auth_service_throws(): void
    {
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');

        $this->mock(TrendHttpClient::class, function ($mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andThrow(new TrendAgentNotAuthenticatedException('No session'));
        });

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('NOT AUTHENTICATED')
            ->expectsOutputToContain('trendagent:auth:login')
            ->expectsOutputToContain('trendagent:auth:save-refresh')
            ->assertExitCode(1);
    }

    public function test_check_auth_token_invalid_when_api_returns_401(): void
    {
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');
        Config::set('trendagent.default_lang', 'ru');

        $this->mock(TrendHttpClient::class, function ($mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response('{"error":"Unauthorized"}', 401));
        });

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('AUTH TOKEN INVALID')
            ->expectsOutputToContain('trendagent:auth:login')
            ->assertExitCode(1);
    }

    public function test_check_auth_token_invalid_when_api_returns_403(): void
    {
        Config::set('trendagent.default_city_id', '58c665588b6aa52311afa01b');

        $this->mock(TrendHttpClient::class, function ($mock) {
            $mock->shouldReceive('get')
                ->once()
                ->andReturn(Http::response('Forbidden', 403));
        });

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('AUTH TOKEN INVALID')
            ->assertExitCode(1);
    }

    public function test_check_fails_when_default_city_id_not_set(): void
    {
        Config::set('trendagent.default_city_id', '');
        Config::set('trendagent.default_lang', 'ru');

        $this->artisan('trendagent:auth:check')
            ->expectsOutputToContain('TRENDAGENT_DEFAULT_CITY_ID is not set')
            ->assertExitCode(1);
    }
}
