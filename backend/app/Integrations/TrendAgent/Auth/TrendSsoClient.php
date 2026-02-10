<?php

namespace App\Integrations\TrendAgent\Auth;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrendSsoClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $base = rtrim((string) Config::get('trendagent.sso_base'), '/');

        if ($base === '') {
            throw new RuntimeException('TrendAgent SSO base URL is not configured.');
        }

        $this->baseUrl = $base;
    }

    /**
     * Выполнить логин в SSO и вернуть refresh_token + raw‑ответ.
     *
     * @return array{
     *     refresh_token: string|null,
     *     raw: array<string,mixed>,
     *     needs_manual_token: bool
     * }
     */
    public function login(string $phone, string $password, string $lang = 'ru'): array
    {
        $appId = (string) Config::get('trendagent.app_id', '');

        if ($appId === '') {
            throw new RuntimeException('TrendAgent app_id is not configured.');
        }

        $response = Http::asForm()
            ->acceptJson()
            ->withOptions([
                'allow_redirects' => false,
            ])
            ->post($this->baseUrl . '/v1/login', [
                'phone'    => $phone,
                'password' => $password,
            ], [
                'query' => [
                    'app_id' => $appId,
                    'lang'   => $lang,
                ],
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('TrendAgent SSO login failed.');
        }

        $data = $response->json() ?? [];
        $refreshToken = $this->extractRefreshToken($response, $data);

        $needsManual = $refreshToken === null || $refreshToken === '';

        return [
            'refresh_token'      => $refreshToken,
            'raw'                => is_array($data) ? $data : [],
            'needs_manual_token' => $needsManual,
        ];
    }

    /**
     * Получить краткоживущий auth_token по refresh_token.
     */
    public function getAuthToken(string $refreshToken, string $cityId, string $lang = 'ru'): string
    {
        $response = Http::acceptJson()
            ->withToken($refreshToken)
            ->get($this->baseUrl . '/v1/auth_token/', [
                'city' => $cityId,
                'lang' => $lang,
            ]);

        if ($response->unauthorized() || $response->forbidden()) {
            throw new RuntimeException('TrendAgent refresh token is invalid or expired.');
        }

        if (! $response->ok()) {
            throw new RuntimeException('Failed to obtain auth_token from TrendAgent SSO.');
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['auth_token']) || ! is_string($data['auth_token'])) {
            throw new RuntimeException('Invalid auth_token response structure.');
        }

        return $data['auth_token'];
    }

    /**
     * Попробовать вытащить refresh_token сначала из Set-Cookie, затем из тела.
     *
     * @param  array<string,mixed>  $data
     */
    protected function extractRefreshToken(Response $response, array $data): ?string
    {
        // 1. Set-Cookie
        $cookies = $response->header('Set-Cookie');

        if ($cookies) {
            $cookieHeaders = is_array($cookies) ? $cookies : [$cookies];

            foreach ($cookieHeaders as $header) {
                if (preg_match('/refresh_token=([^;]+)/', $header, $matches)) {
                    return $matches[1];
                }
            }
        }

        // 2. JSON body
        if (isset($data['refresh_token']) && is_string($data['refresh_token'])) {
            return $data['refresh_token'];
        }

        return null;
    }
}

