<?php

namespace App\Integrations\TrendAgent\Auth;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Facades\Config;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class TrendSsoClient
{
    protected string $ssoBase;

    protected string $ssoWebBase;

    protected bool $verify;

    protected int $timeout = 30;

    protected const APP_ID_VALID_REGEX = '/^[a-f0-9]{24}$/i';

    protected const REDIRECT_MAX = 10;

    public function __construct()
    {
        $this->ssoBase = rtrim((string) Config::get('trendagent.sso_base'), '/');
        $this->ssoWebBase = rtrim((string) Config::get('trendagent.sso_web_base'), '/');
        $this->verify = (bool) Config::get('trendagent.sso_verify', true);

        if ($this->ssoBase === '') {
            throw new RuntimeException('TrendAgent SSO base URL is not configured.');
        }
    }

    /**
     * Normalize phone like AL: digits and +, 8... -> +7...
     */
    public function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^\d+]/', '', $phone);
        if (str_starts_with($phone, '+7')) {
            return $phone;
        }
        if (str_starts_with($phone, '7')) {
            return '+' . $phone;
        }
        if (str_starts_with($phone, '8')) {
            return '+7' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Decode JWT payload (middle part, no signature validation). Returns assoc array or null.
     */
    public function decodeJwtPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = $parts[1];
        $payload = str_replace(['-', '_'], ['+', '/'], $payload);
        $mod = strlen($payload) % 4;
        if ($mod > 0) {
            $payload .= str_repeat('=', 4 - $mod);
        }
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return null;
        }
        $data = json_decode($decoded, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Extract app_id from refresh_token JWT payload. No signature check.
     */
    public function extractAppIdFromRefreshToken(string $refreshToken): ?string
    {
        $payload = $this->decodeJwtPayload($refreshToken);
        if ($payload === null) {
            return null;
        }
        $appId = $payload['app_id'] ?? null;
        return is_string($appId) && $this->validateAppId($appId) ? $appId : null;
    }

    /**
     * Extract app_id from redirect history (last URL in chain). For unit tests.
     *
     * @param string[] $redirectUrls
     */
    public function extractAppIdFromRedirectHistory(array $redirectUrls): string
    {
        if ($redirectUrls === []) {
            return '';
        }
        $last = end($redirectUrls);
        return $this->extractAppIdFromUrl(is_string($last) ? $last : (string) $last);
    }

    /**
     * Login to SSO. Strict contract: either success with refresh_token or ok=false / exception.
     *
     * Success: ['ok'=>true, 'refresh_token'=>string, 'app_id'=>string, 'status'=>int]
     * Blocked/manual: ['ok'=>false, 'needs_manual_token'=>true, 'status'=>int, 'reason'=>string]
     * Error: throws RuntimeException (no tokens in message).
     *
     * @return array{ok: bool, refresh_token?: string, app_id?: string, status: int, needs_manual_token?: bool, reason?: string}
     */
    public function login(string $phone, string $password, string $lang = 'ru'): array
    {
        $phoneFormatted = $this->formatPhone($phone);
        $jar = new CookieJar;
        $client = $this->createGuzzleClient($jar);

        $appId = $this->resolveAppId($client);
        if ($appId === '') {
            throw new RuntimeException('TrendAgent app_id could not be resolved (config or login page).');
        }

        $result = $this->doPostLogin($client, $jar, $appId, $phoneFormatted, $password, $lang);
        if ($result !== null) {
            $result['app_id'] = $appId;
            return $result;
        }

        $altAppId = $this->getAlternativeAppId();
        if ($altAppId !== '' && $altAppId !== $appId) {
            $jar2 = new CookieJar;
            $client2 = $this->createGuzzleClient($jar2);
            $this->resolveAppId($client2);
            $result = $this->doPostLogin($client2, $jar2, $altAppId, $phoneFormatted, $password, $lang);
            if ($result !== null) {
                $result['app_id'] = $altAppId;
                return $result;
            }
        }

        return [
            'ok' => false,
            'needs_manual_token' => true,
            'status' => 403,
            'reason' => 'forbidden_no_token',
        ];
    }

    /**
     * POST login. Returns strict result or null (403, no token → caller retries with alternative app_id).
     *
     * @return array{ok: bool, refresh_token?: string, status: int, needs_manual_token?: bool, reason?: string}|null
     */
    protected function doPostLogin(Client $client, CookieJar $jar, string $appId, string $phoneFormatted, string $password, string $lang): ?array
    {
        $loginUrl = $this->ssoBase . '/v1/login?' . http_build_query(['app_id' => $appId, 'lang' => $lang]);
        $referer = $this->ssoWebBase . '/login?app_id=' . urlencode($appId);

        try {
            $response = $client->post($loginUrl, [
                'allow_redirects' => false,
                'form_params' => [
                    'phone' => $phoneFormatted,
                    'password' => $password,
                    'client' => 'web',
                ],
                'headers' => $this->postLoginHeaders($referer),
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('TrendAgent SSO request failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = $this->decodeJson($body);

        $token = $this->extractTokenFromResponse($response, $body, $data, $jar);

        if ($status >= 300 && $status < 400) {
            $location = $response->getHeaderLine('Location');
            if (($token === null || $token === '') && $location !== '' && str_contains($location, 'auth_token=')) {
                if (preg_match('/auth_token=([^&\s]+)/', $location, $m)) {
                    $token = trim($m[1]);
                }
            }
        }

        $hasToken = $token !== null && $token !== '';

        if ($status >= 200 && $status < 300) {
            if (! $hasToken) {
                return [
                    'ok' => false,
                    'needs_manual_token' => true,
                    'status' => $status,
                    'reason' => 'token_not_found',
                ];
            }
            return [
                'ok' => true,
                'refresh_token' => $token,
                'status' => $status,
            ];
        }

        if ($status >= 300 && $status < 400) {
            if (! $hasToken) {
                return [
                    'ok' => false,
                    'needs_manual_token' => true,
                    'status' => $status,
                    'reason' => 'token_not_found',
                ];
            }
            return [
                'ok' => true,
                'refresh_token' => $token,
                'status' => $status,
            ];
        }

        if ($status === 403) {
            if ($hasToken) {
                return [
                    'ok' => true,
                    'refresh_token' => $token,
                    'status' => $status,
                ];
            }
            return null;
        }

        $preview = $this->sanitizePreview($body);
        throw new RuntimeException(
            'TrendAgent SSO login failed (HTTP ' . $status . '). ' . substr($preview, 0, 200)
        );
    }

    /**
     * @return array<string, string>
     */
    protected function getLoginPageHeaders(): array
    {
        $ua = (string) Config::get('trendagent.user_agent');
        return [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'User-Agent' => $ua,
            'Sec-Ch-Ua' => '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-Site' => 'none',
            'Sec-Fetch-User' => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function postLoginHeaders(string $referer): array
    {
        $ua = (string) Config::get('trendagent.user_agent');
        return [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin' => $this->ssoWebBase,
            'Referer' => $referer,
            'User-Agent' => $ua,
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Sec-Ch-Ua' => '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
        ];
    }

    /**
     * Получить краткоживущий auth_token по refresh_token. Guzzle + браузерные заголовки. app_id из JWT payload.
     * 200: обязан auth_token в JSON. 401 session_app_id_doesnt_match: retry с appIdJwt если отличается. 401/403 итого: RuntimeException с code=status.
     *
     * @param  string|null  $appId  Явный app_id (опционально); fallback: из JWT payload, config
     */
    public function getAuthToken(string $refreshToken, string $cityId, string $lang = 'ru', ?string $appId = null): string
    {
        $appIdJwt = $this->extractAppIdFromRefreshToken($refreshToken);
        $chosenAppId = $appId ?? $appIdJwt ?? (string) Config::get('trendagent.app_id', '') ?: (string) Config::get('trendagent.app_id_alternative', '');

        $result = $this->doGetAuthToken($refreshToken, $cityId, $lang, $chosenAppId);

        if (($result['retry'] ?? false) === true && $appIdJwt !== null && $appIdJwt !== $chosenAppId) {
            $result = $this->doGetAuthToken($refreshToken, $cityId, $lang, $appIdJwt);
        }

        if ($result['ok'] === false) {
            throw new RuntimeException($result['error_message'], $result['error_code']);
        }

        return $result['auth_token'];
    }

    /**
     * Внутренний helper для GET /v1/auth_token с app_id в Referer.
     *
     * @return array{ok: bool, auth_token?: string, retry?: bool, error_message?: string, error_code?: int}
     */
    protected function doGetAuthToken(string $refreshToken, string $cityId, string $lang, string $chosenAppId): array
    {
        $url = $this->ssoBase . '/v1/auth_token/?' . http_build_query(['city' => $cityId, 'lang' => $lang]);
        $jar = new CookieJar;
        $client = $this->createGuzzleClient($jar);

        $referer = $this->ssoWebBase . '/login' . ($chosenAppId !== '' ? '?app_id=' . urlencode($chosenAppId) : '');

        $headers = [
            'Authorization' => 'Bearer ' . $refreshToken,
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'User-Agent' => (string) Config::get('trendagent.user_agent'),
            'Origin' => $this->ssoWebBase,
            'Referer' => $referer,
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'cross-site',
            'Sec-Ch-Ua' => '"Chromium";v="142", "Google Chrome";v="142", "Not_A Brand";v="99"',
            'Sec-Ch-Ua-Mobile' => '?0',
            'Sec-Ch-Ua-Platform' => '"Windows"',
        ];

        try {
            $response = $client->get($url, ['headers' => $headers]);
        } catch (GuzzleException $e) {
            $hint = $e->getMessage();
            if (str_contains($hint, 'timed out') || str_contains($hint, 'timeout')) {
                $hint = 'timeout';
            } elseif (str_contains($hint, 'resolve') || str_contains($hint, 'getaddrinfo')) {
                $hint = 'DNS';
            } elseif (str_contains($hint, 'SSL') || str_contains($hint, 'certificate') || str_contains($hint, 'TLS')) {
                $hint = 'TLS';
            } else {
                $hint = 'connection';
            }
            return [
                'ok' => false,
                'error_message' => 'auth_token request failed: ' . $hint . ' — ' . $url,
                'error_code' => 0,
            ];
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = $this->decodeJson($body);

        if ($status === 401 || $status === 403) {
            $preview = $this->sanitizePreview($body);
            $retry = $status === 401 && is_array($data) && str_contains((string) ($data['message'] ?? ''), 'session_app_id_doesnt_match');
            return [
                'ok' => false,
                'retry' => $retry,
                'error_message' => 'refresh token rejected — ' . substr($preview, 0, 500),
                'error_code' => $status,
            ];
        }

        if ($status !== 200) {
            $preview = $this->sanitizePreview($body);
            return [
                'ok' => false,
                'error_message' => 'auth_token request failed: HTTP ' . $status . ' — ' . substr($preview, 0, 300),
                'error_code' => $status,
            ];
        }

        if (! is_array($data) || ! isset($data['auth_token']) || ! is_string($data['auth_token'])) {
            return [
                'ok' => false,
                'error_message' => 'auth_token missing in response',
                'error_code' => 0,
            ];
        }

        return [
            'ok' => true,
            'auth_token' => $data['auth_token'],
        ];
    }

    /**
     * Resolve app_id: GET login with redirects → effective URI → HTML → config. One CookieJar for GET and POST.
     */
    protected function resolveAppId(Client $client): string
    {
        $fromConfig = (string) Config::get('trendagent.app_id', '');
        $candidate = $this->validateAppId($fromConfig) ? $fromConfig : '';

        $effectiveUri = null;
        try {
            $response = $client->get($this->ssoWebBase . '/login', [
                'allow_redirects' => [
                    'max' => self::REDIRECT_MAX,
                    'strict' => false,
                    'referer' => true,
                    'track_redirects' => true,
                ],
                'headers' => $this->getLoginPageHeaders(),
                'on_stats' => function (TransferStats $stats) use (&$effectiveUri) {
                    $effectiveUri = $stats->getEffectiveUri();
                },
            ]);
        } catch (GuzzleException $e) {
            return $candidate;
        }

        if ($effectiveUri !== null) {
            $fromUri = $this->extractAppIdFromUrl((string) $effectiveUri);
            if ($fromUri !== '') {
                return $fromUri;
            }
        }

        $body = (string) $response->getBody();
        $fromHtml = $this->extractAppIdFromHtml($body);
        if ($fromHtml !== '') {
            return $fromHtml;
        }

        return $candidate;
    }

    protected function getAlternativeAppId(): string
    {
        $alt = (string) Config::get('trendagent.app_id_alternative', '');
        return $this->validateAppId($alt) ? $alt : '';
    }

    /**
     * Extract app_id from URL (e.g. .../login?app_id=66d84ffc4c0168b8ccd281c7).
     */
    public function extractAppIdFromUrl(string $url): string
    {
        if (preg_match('/app_id=([a-f0-9]{24})/i', $url, $m)) {
            $id = $m[1];
            return $this->validateAppId($id) ? $id : '';
        }
        return '';
    }

    /**
     * Extract app_id from HTML using config regex list.
     */
    public function extractAppIdFromHtml(string $html): string
    {
        $patterns = (array) Config::get('trendagent.app_id_regex', []);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m) && isset($m[1])) {
                $id = trim($m[1]);
                if ($this->validateAppId($id)) {
                    return $id;
                }
            }
        }
        return '';
    }

    protected function validateAppId(string $id): bool
    {
        return $id !== '' && (bool) preg_match(self::APP_ID_VALID_REGEX, $id);
    }

    protected function createGuzzleClient(CookieJar $jar): Client
    {
        return new Client([
            'cookies' => $jar,
            'verify' => $this->verify,
            'timeout' => $this->timeout,
            'http_errors' => false,
        ]);
    }

    /**
     * Extract token (refresh_token or auth_token) from response: CookieJar, Set-Cookie, JSON, Location.
     *
     * @param array<string,mixed>|null $data
     */
    protected function extractTokenFromResponse(ResponseInterface $response, string $body, ?array $data, CookieJar $jar): ?string
    {
        $names = ['refresh_token', 'auth_token'];
        foreach ($jar as $cookie) {
            $name = strtolower($cookie->getName());
            foreach ($names as $n) {
                if ($name === $n) {
                    $v = $cookie->getValue();
                    return $v !== '' ? $v : null;
                }
            }
        }

        $setCookies = $response->getHeader('Set-Cookie');
        foreach ($setCookies as $header) {
            foreach ($names as $n) {
                if (preg_match('/' . preg_quote($n, '/') . '=([^;]+)/', $header, $m)) {
                    return trim($m[1]);
                }
            }
        }

        if (is_array($data)) {
            foreach ($names as $n) {
                if (isset($data[$n]) && is_string($data[$n]) && $data[$n] !== '') {
                    return $data[$n];
                }
                if (isset($data['data'][$n]) && is_string($data['data'][$n]) && $data['data'][$n] !== '') {
                    return $data['data'][$n];
                }
            }
        }

        $location = $response->getHeaderLine('Location');
        if ($location !== '' && str_contains($location, 'auth_token=')) {
            if (preg_match('/auth_token=([^&\s]+)/', $location, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    /**
     * Extract refresh_token (legacy name): same as extractTokenFromResponse. For backward compatibility.
     *
     * @param array<string,mixed>|null $data
     */
    protected function extractRefreshTokenFromResponse(ResponseInterface $response, string $body, ?array $data, CookieJar $jar): ?string
    {
        return $this->extractTokenFromResponse($response, $body, $data, $jar);
    }

    /**
     * For unit tests: extract from Set-Cookie headers and JSON (refresh_token or auth_token).
     *
     * @param string[] $setCookieHeaders
     * @param array<string,mixed>|null $jsonBody
     */
    public function extractTokenFromHeadersAndJson(array $setCookieHeaders, ?array $jsonBody): ?string
    {
        $names = ['refresh_token', 'auth_token'];
        foreach ($setCookieHeaders as $header) {
            foreach ($names as $n) {
                if (preg_match('/' . preg_quote($n, '/') . '=([^;]+)/', $header, $m)) {
                    return trim($m[1]);
                }
            }
        }
        if (is_array($jsonBody)) {
            foreach ($names as $n) {
                if (isset($jsonBody[$n]) && is_string($jsonBody[$n])) {
                    return $jsonBody[$n];
                }
                if (isset($jsonBody['data'][$n]) && is_string($jsonBody['data'][$n])) {
                    return $jsonBody['data'][$n];
                }
            }
        }
        return null;
    }

    private function decodeJson(string $body): ?array
    {
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Remove token keys from raw for logging/safe return.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private function sanitizeRaw(array $raw): array
    {
        $keys = ['refresh_token', 'auth_token', 'token', 'access_token'];
        foreach ($keys as $key) {
            if (array_key_exists($key, $raw)) {
                $raw[$key] = '***';
            }
        }
        if (isset($raw['data']) && is_array($raw['data'])) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $raw['data'])) {
                    $raw['data'][$key] = '***';
                }
            }
        }
        return $raw;
    }

    private function sanitizePreview(string $body): string
    {
        $s = preg_replace('/"(?:refresh_token|auth_token|token)"\s*:\s*"[^"]*"/i', '"***"', $body);
        return strlen($s) > 400 ? substr($s, 0, 400) . '...' : $s;
    }
}
