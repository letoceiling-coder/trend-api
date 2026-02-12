<?php

namespace App\Domain\TrendAgent;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Send alerts to Telegram. No tokens or secrets in logs or messages.
 */
class TaAlertService
{
    private const TELEGRAM_API = 'https://api.telegram.org';

    /**
     * Send a single message to the configured Telegram chat.
     * $context is for logging only (sanitized); it is not sent in the message.
     * Returns true if sent, false if skipped (no config) or failed.
     */
    public function send(string $message, array $context = []): bool
    {
        $token = config('trendagent.alerts.telegram_bot_token');
        $chatId = config('trendagent.alerts.telegram_chat_id');

        if (empty($token) || $chatId === null || $chatId === '') {
            return false;
        }

        $text = $this->sanitizeMessage($message);
        if ($text === '') {
            return false;
        }

        $url = self::TELEGRAM_API . '/bot' . $token . '/sendMessage';

        try {
            $response = Http::timeout(10)
                ->post($url, [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'disable_web_page_preview' => true,
                ]);

            if (! $response->successful()) {
                Log::warning('TrendAgent Telegram alert failed', [
                    'status' => $response->status(),
                    'reason' => $response->reason(),
                ] + $this->sanitizeContext($context));
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::warning('TrendAgent Telegram alert exception', [
                'error' => $e->getMessage(),
            ] + $this->sanitizeContext($context));
            return false;
        }
    }

    /**
     * Remove or redact secret-like content from message. Do not log tokens.
     */
    private function sanitizeMessage(string $message): string
    {
        $message = preg_replace('/\b(token|password|secret|auth_token|api_key)\s*[:=]\s*\S+/i', '$1=***', $message);
        $message = preg_replace('/\s+/', ' ', trim($message));
        return strlen($message) > 4000 ? substr($message, 0, 3997) . '...' : $message;
    }

    private function sanitizeContext(array $context): array
    {
        $out = [];
        foreach ($context as $k => $v) {
            if (is_string($k) && preg_match('/^(token|password|secret|key|auth)/i', $k)) {
                continue;
            }
            $out[$k] = is_scalar($v) ? $v : json_encode($v);
        }
        return $out;
    }
}
