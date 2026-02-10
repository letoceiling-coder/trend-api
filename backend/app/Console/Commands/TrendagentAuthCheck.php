<?php

namespace App\Console\Commands;

use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use App\Integrations\TrendAgent\Http\TrendHttpClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentAuthCheck extends Command
{
    protected $signature = 'trendagent:auth:check';

    protected $description = 'Verify TrendAgent session is valid (GET a protected API endpoint; no tokens printed)';

    public function handle(TrendHttpClient $http): int
    {
        $cityId = (string) Config::get('trendagent.default_city_id', '');
        $lang = (string) Config::get('trendagent.default_lang', 'ru');

        if ($cityId === '') {
            $this->error('TRENDAGENT_DEFAULT_CITY_ID is not set. Set it in .env to run this check.');
            return self::FAILURE;
        }

        $baseUrl = rtrim((string) Config::get('trendagent.api.core', 'https://api.trendagent.ru'), '/');
        $checkUrl = $baseUrl . '/v4_29/unit_measurements';

        try {
            $response = $http->get($checkUrl, ['city' => $cityId, 'lang' => $lang]);
        } catch (TrendAgentNotAuthenticatedException $e) {
            $this->line('NOT AUTHENTICATED');
            $this->printHint();
            return self::FAILURE;
        }

        $status = $response->status();
        if ($status === 401 || $status === 403) {
            $this->line('AUTH TOKEN INVALID');
            $this->printHint();
            return self::FAILURE;
        }

        if ($status !== 200) {
            $this->error('API returned HTTP ' . $status);
            return self::FAILURE;
        }

        $body = $response->body();
        json_decode($body);
        if ($body === '' || json_last_error() !== JSON_ERROR_NONE) {
            $this->error('API response is not valid JSON');
            return self::FAILURE;
        }

        $this->line('OK');
        return self::SUCCESS;
    }

    private function printHint(): void
    {
        $this->newLine();
        $this->line('  Run: php artisan trendagent:auth:login');
        $this->line('  Or:  php artisan trendagent:auth:save-refresh "<refresh_token_from_browser>"');
    }
}
