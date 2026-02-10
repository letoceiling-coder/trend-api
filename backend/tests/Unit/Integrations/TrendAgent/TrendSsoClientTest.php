<?php

namespace Tests\Unit\Integrations\TrendAgent;

use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TrendSsoClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('trendagent.sso_base', 'https://sso-api.trend.tech');
        Config::set('trendagent.sso_web_base', 'https://sso.trend.tech');
        Config::set('trendagent.sso_verify', true);
        Config::set('trendagent.app_id_regex', [
            '/app_id["\']?\s*[:=]\s*["\']([a-f0-9]{24})["\']/i',
            '/["\']([a-f0-9]{24})["\'].*app_id/i',
            '/login\?app_id=([a-f0-9]{24})/i',
            '/app_id=([a-f0-9]{24})/i',
        ]);
    }

    public function test_format_phone_keeps_plus_7(): void
    {
        $client = new TrendSsoClient();
        $this->assertSame('+79045393434', $client->formatPhone('+79045393434'));
        $this->assertSame('+79045393434', $client->formatPhone('+7 904 539 34 34'));
    }

    public function test_format_phone_adds_plus_to_7(): void
    {
        $client = new TrendSsoClient();
        $this->assertSame('+79045393434', $client->formatPhone('79045393434'));
    }

    public function test_format_phone_converts_8_to_plus_7(): void
    {
        $client = new TrendSsoClient();
        $this->assertSame('+79045393434', $client->formatPhone('89045393434'));
        $this->assertSame('+79045393434', $client->formatPhone('8 904 539-34-34'));
    }

    public function test_format_phone_strips_non_digits(): void
    {
        $client = new TrendSsoClient();
        $this->assertSame('+79045393434', $client->formatPhone('+7 (904) 539-34-34'));
    }

    public function test_extract_app_id_from_redirect_history_empty(): void
    {
        $client = new TrendSsoClient();
        $this->assertSame('', $client->extractAppIdFromRedirectHistory([]));
    }

    public function test_extract_app_id_from_redirect_history_last_url(): void
    {
        $client = new TrendSsoClient();
        $urls = [
            'https://sso.trend.tech/',
            'https://sso.trend.tech/login?other=1',
            'https://sso.trend.tech/login?app_id=66d84f584c0168b8ccd281c3&lang=ru',
        ];
        $this->assertSame('66d84f584c0168b8ccd281c3', $client->extractAppIdFromRedirectHistory($urls));
    }

    public function test_extract_app_id_from_redirect_history_single(): void
    {
        $client = new TrendSsoClient();
        $this->assertSame('66d84ffc4c0168b8ccd281c7', $client->extractAppIdFromRedirectHistory([
            'https://sso.trend.tech/login?app_id=66d84ffc4c0168b8ccd281c7',
        ]));
    }

    public function test_extract_app_id_from_url(): void
    {
        $client = new TrendSsoClient();

        $this->assertSame('66d84ffc4c0168b8ccd281c7', $client->extractAppIdFromUrl('https://sso.trend.tech/login?app_id=66d84ffc4c0168b8ccd281c7'));
        $this->assertSame('66d84ffc4c0168b8ccd281c7', $client->extractAppIdFromUrl('https://example.com?app_id=66d84ffc4c0168b8ccd281c7&lang=ru'));
        $this->assertSame('', $client->extractAppIdFromUrl('https://sso.trend.tech/login'));
        $this->assertSame('', $client->extractAppIdFromUrl('https://sso.trend.tech/login?app_id=short'));
    }

    public function test_extract_app_id_from_html_examples(): void
    {
        $client = new TrendSsoClient();

        $html1 = '<script>window.APP_ID = "66d84ffc4c0168b8ccd281c7";</script>';
        $this->assertSame('66d84ffc4c0168b8ccd281c7', $client->extractAppIdFromHtml($html1));

        $html2 = '<a href="/login?app_id=58c665588b6aa52311afa01b">Login</a>';
        $this->assertSame('58c665588b6aa52311afa01b', $client->extractAppIdFromHtml($html2));

        $html3 = 'var config = { app_id: "a1b2c3d4e5f60718293a4b5c6" };';
        $this->assertSame('a1b2c3d4e5f60718293a4b5c6', $client->extractAppIdFromHtml($html3));

        $html4 = '<div>No app_id here</div>';
        $this->assertSame('', $client->extractAppIdFromHtml($html4));

        $html5 = 'app_id=123456789012345678901234';
        $this->assertSame('123456789012345678901234', $client->extractAppIdFromHtml($html5));
    }

    public function test_extract_token_from_headers_and_json_set_cookie(): void
    {
        $client = new TrendSsoClient();

        $headers = [
            'session=abc; Path=/',
            'refresh_token=rt_from_cookie_123; Path=/; HttpOnly',
        ];
        $this->assertSame('rt_from_cookie_123', $client->extractTokenFromHeadersAndJson($headers, null));
    }

    public function test_extract_token_from_headers_and_json_body(): void
    {
        $client = new TrendSsoClient();

        $headers = [];
        $body = ['refresh_token' => 'rt_from_json_456'];
        $this->assertSame('rt_from_json_456', $client->extractTokenFromHeadersAndJson($headers, $body));
    }

    public function test_extract_token_from_headers_and_json_data_nested(): void
    {
        $client = new TrendSsoClient();

        $headers = [];
        $body = ['data' => ['refresh_token' => 'rt_nested_789']];
        $this->assertSame('rt_nested_789', $client->extractTokenFromHeadersAndJson($headers, $body));
    }

    public function test_extract_token_from_headers_and_json_prefers_cookie_over_body(): void
    {
        $client = new TrendSsoClient();

        $headers = ['refresh_token=cookie_first; Path=/'];
        $body = ['refresh_token' => 'body_second'];
        $this->assertSame('cookie_first', $client->extractTokenFromHeadersAndJson($headers, $body));
    }

    public function test_extract_token_from_headers_and_json_returns_null_when_empty(): void
    {
        $client = new TrendSsoClient();

        $this->assertNull($client->extractTokenFromHeadersAndJson([], null));
        $this->assertNull($client->extractTokenFromHeadersAndJson([], []));
        $this->assertNull($client->extractTokenFromHeadersAndJson(['Set-Cookie: other=val'], []));
    }

    public function test_extract_token_from_headers_and_json_auth_token_in_cookie(): void
    {
        $client = new TrendSsoClient();
        $headers = [
            'auth_token=eyJhbGciOiJSUzI1NiJ9.xxx; Path=/; HttpOnly',
        ];
        $this->assertSame('eyJhbGciOiJSUzI1NiJ9.xxx', $client->extractTokenFromHeadersAndJson($headers, null));
    }

    public function test_extract_token_from_headers_and_json_auth_token_in_body(): void
    {
        $client = new TrendSsoClient();
        $body = ['auth_token' => 'at_from_json_123'];
        $this->assertSame('at_from_json_123', $client->extractTokenFromHeadersAndJson([], $body));
    }

    /**
     * 403 response but cookie/Set-Cookie contains token => we treat as success (token extracted).
     * This tests the extraction helper used when handling 403.
     */
    public function test_403_with_token_in_set_cookie_extraction(): void
    {
        $client = new TrendSsoClient();
        $setCookieHeaders = [
            'session=abc; Path=/',
            'refresh_token=rt_after_403; Path=/; HttpOnly',
        ];
        $jsonBody = ['error' => 'Forbidden'];
        $token = $client->extractTokenFromHeadersAndJson($setCookieHeaders, $jsonBody);
        $this->assertSame('rt_after_403', $token);
    }

    public function test_decode_jwt_payload_valid(): void
    {
        $client = new TrendSsoClient();
        // Sample JWT: header.payload.signature where payload = base64url({"app_id":"66d84ffc4c0168b8ccd281c7","exp":1234567890})
        $payload = base64_encode(json_encode(['app_id' => '66d84ffc4c0168b8ccd281c7', 'exp' => 1234567890]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $jwt = 'eyJhbGciOiJSUzI1NiJ9.' . $payload . '.signature';

        $result = $client->decodeJwtPayload($jwt);
        $this->assertIsArray($result);
        $this->assertSame('66d84ffc4c0168b8ccd281c7', $result['app_id']);
        $this->assertSame(1234567890, $result['exp']);
    }

    public function test_decode_jwt_payload_invalid(): void
    {
        $client = new TrendSsoClient();
        $this->assertNull($client->decodeJwtPayload(''));
        $this->assertNull($client->decodeJwtPayload('invalid'));
        $this->assertNull($client->decodeJwtPayload('part1.part2'));
    }

    public function test_extract_app_id_from_refresh_token_valid(): void
    {
        $client = new TrendSsoClient();
        $payload = base64_encode(json_encode(['app_id' => '66d84ffc4c0168b8ccd281c7', 'user_id' => 123]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $jwt = 'header.' . $payload . '.signature';

        $appId = $client->extractAppIdFromRefreshToken($jwt);
        $this->assertSame('66d84ffc4c0168b8ccd281c7', $appId);
    }

    public function test_extract_app_id_from_refresh_token_no_app_id(): void
    {
        $client = new TrendSsoClient();
        $payload = base64_encode(json_encode(['user_id' => 123]));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $jwt = 'header.' . $payload . '.signature';

        $this->assertNull($client->extractAppIdFromRefreshToken($jwt));
    }

    public function test_extract_app_id_from_refresh_token_invalid_app_id_format(): void
    {
        $client = new TrendSsoClient();
        $payload = base64_encode(json_encode(['app_id' => 'short']));
        $payload = rtrim(strtr($payload, '+/', '-_'), '=');
        $jwt = 'header.' . $payload . '.signature';

        $this->assertNull($client->extractAppIdFromRefreshToken($jwt));
    }

    public function test_extract_app_id_from_refresh_token_invalid_jwt(): void
    {
        $client = new TrendSsoClient();
        $this->assertNull($client->extractAppIdFromRefreshToken(''));
        $this->assertNull($client->extractAppIdFromRefreshToken('invalid'));
    }
}
