<?php

namespace Tests\Unit\Domain\TrendAgent;

use App\Domain\TrendAgent\TaAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaAlertServiceTest extends TestCase
{
    public function test_send_returns_false_when_token_not_configured(): void
    {
        Config::set('trendagent.alerts.telegram_bot_token', null);
        Config::set('trendagent.alerts.telegram_chat_id', '123');

        $service = new TaAlertService();
        $this->assertFalse($service->send('test'));
    }

    public function test_send_returns_false_when_chat_id_not_configured(): void
    {
        Config::set('trendagent.alerts.telegram_bot_token', 'dummy-token');
        Config::set('trendagent.alerts.telegram_chat_id', '');

        $service = new TaAlertService();
        $this->assertFalse($service->send('test'));
    }

    public function test_send_posts_to_telegram_when_configured(): void
    {
        Config::set('trendagent.alerts.telegram_bot_token', 'test-bot-token');
        Config::set('trendagent.alerts.telegram_chat_id', '-100123');

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $service = new TaAlertService();
        $result = $service->send('[TA] Test message', ['scope' => 'blocks']);

        $this->assertTrue($result);
        Http::assertSent(function ($request) {
            $url = $request->url();
            if (strpos($url, 'api.telegram.org') === false) {
                return false;
            }
            $body = $request->data();
            return isset($body['chat_id']) && $body['chat_id'] === '-100123'
                && isset($body['text']) && str_contains($body['text'], '[TA] Test message');
        });
    }

    public function test_send_does_not_include_token_in_request_body(): void
    {
        Config::set('trendagent.alerts.telegram_bot_token', 'secret-token-xyz');
        Config::set('trendagent.alerts.telegram_chat_id', '456');

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $service = new TaAlertService();
        $service->send('Alert');

        Http::assertSent(function ($request) {
            $body = $request->data();
            return ! isset($body['token']) && strpos($request->url(), 'secret-token-xyz') !== false;
        });
    }

    public function test_sanitize_redacts_secret_like_in_message(): void
    {
        Config::set('trendagent.alerts.telegram_bot_token', 't');
        Config::set('trendagent.alerts.telegram_chat_id', '1');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $service = new TaAlertService();
        $service->send('Error with token: abc123secret');

        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';
            return str_contains($text, '***') && ! str_contains($text, 'abc123secret');
        });
    }
}
