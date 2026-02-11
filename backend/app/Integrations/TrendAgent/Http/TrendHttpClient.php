<?php

namespace App\Integrations\TrendAgent\Http;

use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class TrendHttpClient
{
    public function __construct(
        protected TrendAuthService $auth,
    ) {
    }

    /**
     * Выполнить GET-запрос к TrendAgent API с автоматическим добавлением
     * city/lang/auth_token и retry при 401/403 (одна повторная попытка).
     *
     * @param  string  $url   Полный URL (включая домен api.trendagent.ru и т.п.)
     * @param  array<string, mixed>  $query
     */
    public function get(string $url, array $query = []): Response
    {
        $cityId = (string) ($query['city'] ?? Config::get('trendagent.default_city_id', ''));
        $lang = (string) ($query['lang'] ?? Config::get('trendagent.default_lang', 'ru'));

        if ($cityId === '') {
            // Явное отсутствие cityId считаем ошибкой конфигурации.
            throw new \InvalidArgumentException('TrendAgent city id is not configured.');
        }

        try {
            $response = $this->performGet($url, $query, $cityId, $lang);

            if ($response->status() === 401 || $response->status() === 403) {
                // Инвалидируем токен и пробуем ещё раз один раз.
                $this->auth->invalidate($cityId, $lang);

                $response = $this->performGet($url, $query, $cityId, $lang);
            }

            return $response;
        } catch (TrendAgentNotAuthenticatedException $e) {
            // Пробрасываем дальше, чтобы вызывающий код мог отреагировать (например, 401 в UI).
            throw $e;
        }
    }

    /**
     * Выполнить POST-запрос с JSON body к TrendAgent API с автоматическим добавлением
     * city/lang/auth_token в query и retry при 401/403.
     *
     * @param  string  $url   Полный URL
     * @param  array<string, mixed>  $query  Query параметры (city/lang добавятся автоматически)
     * @param  array<string, mixed>  $body   JSON body
     */
    public function postJson(string $url, array $query = [], array $body = []): Response
    {
        $cityId = (string) ($query['city'] ?? Config::get('trendagent.default_city_id', ''));
        $lang = (string) ($query['lang'] ?? Config::get('trendagent.default_lang', 'ru'));

        if ($cityId === '') {
            throw new \InvalidArgumentException('TrendAgent city id is not configured.');
        }

        try {
            $response = $this->performPostJson($url, $query, $body, $cityId, $lang);

            if ($response->status() === 401 || $response->status() === 403) {
                $this->auth->invalidate($cityId, $lang);
                $response = $this->performPostJson($url, $query, $body, $cityId, $lang);
            }

            return $response;
        } catch (TrendAgentNotAuthenticatedException $e) {
            throw $e;
        }
    }

    /**
     * Внутренний helper для GET запроса с получением auth_token.
     *
     * @param  array<string, mixed>  $query
     */
    private function performGet(string $url, array $query, string $cityId, string $lang): Response
    {
        $authToken = $this->auth->ensureAuthenticated($cityId, $lang);

        $finalQuery = array_merge($query, [
            'city'       => $cityId,
            'lang'       => $lang,
            'auth_token' => $authToken,
        ]);

        return Http::acceptJson()->get($url, $finalQuery);
    }

    /**
     * Внутренний helper для POST JSON запроса с получением auth_token.
     *
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>  $body
     */
    private function performPostJson(string $url, array $query, array $body, string $cityId, string $lang): Response
    {
        $authToken = $this->auth->ensureAuthenticated($cityId, $lang);

        $finalQuery = array_merge($query, [
            'city'       => $cityId,
            'lang'       => $lang,
            'auth_token' => $authToken,
        ]);

        return Http::acceptJson()->post($url . '?' . http_build_query($finalQuery), $body);
    }
}

