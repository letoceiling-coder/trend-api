<?php

namespace App\Console\Commands;

use App\Integrations\TrendAgent\Auth\TrendAgentNotAuthenticatedException;
use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class TrendagentAuthCheck extends Command
{
    protected $signature = 'trendagent:auth:check';

    protected $description = 'Verify TrendAgent session: get auth_token from SSO (real reason on fail). Exit 0 only if token obtained.';

    public function handle(TrendAuthService $auth): int
    {
        $verbose = $this->output->getVerbosity() >= OutputInterface::VERBOSITY_DEBUG;

        /** @var TaSsoSession|null $session */
        $session = TaSsoSession::query()
            ->where('provider', 'trendagent')
            ->orderByDesc('last_login_at')
            ->first();

        if (! $session) {
            $this->line('AUTH FAIL');
            $this->line('reason: no session (ta_sso_sessions empty or no provider=trendagent)');
            $this->printHint();
            return self::FAILURE;
        }

        $refreshOk = $session->refresh_token !== null && $session->refresh_token !== '';
        if (! $refreshOk) {
            $this->line('AUTH FAIL');
            $this->line('reason: refresh_token missing in DB');
            $this->printHint();
            return self::FAILURE;
        }

        $cityId = $session->city_id ?? Config::get('trendagent.default_city_id');
        $cityId = trim((string) $cityId);
        $lang = (string) Config::get('trendagent.default_lang', 'ru');

        if ($cityId === '') {
            $this->line('AUTH FAIL');
            $this->line('reason: city_id missing (session has none, TRENDAGENT_DEFAULT_CITY_ID not set)');
            $this->printHint();
            return self::FAILURE;
        }

        if ($verbose) {
            $ssoBase = rtrim((string) Config::get('trendagent.sso_base'), '/');
            $verify = Config::get('trendagent.sso_verify');
            $url = $ssoBase . '/v1/auth_token/?city=' . urlencode($cityId) . '&lang=' . urlencode($lang);
            $this->line('cityId: ' . $cityId);
            $this->line('lang: ' . $lang);
            $this->line('sso_base: ' . $ssoBase);
            $this->line('verify: ' . ($verify ? 'true' : 'false'));
            $this->line('refresh_token from db: ok');
            $this->line('request: GET ' . $url . ' (Authorization: Bearer ***)');
        }

        try {
            $authToken = $auth->getAuthTokenForSession($session);
        } catch (InvalidArgumentException $e) {
            $this->line('AUTH FAIL');
            $this->line('reason: ' . $e->getMessage());
            $this->printHint();
            return self::FAILURE;
        } catch (TrendAgentNotAuthenticatedException $e) {
            $this->line('AUTH FAIL');
            $this->line('reason: ' . $e->getMessage());
            $prev = $e->getPrevious();
            if ($prev instanceof RuntimeException && $prev->getCode() > 0) {
                $this->line('http_status: ' . $prev->getCode());
                if (str_contains($prev->getMessage(), ' — ')) {
                    $parts = explode(' — ', $prev->getMessage(), 2);
                    if (isset($parts[1])) {
                        $this->line('body_preview: ' . $this->maskTokens(substr($parts[1], 0, 500)));
                    }
                }
            }
            $this->printHint();
            return self::FAILURE;
        } catch (RuntimeException $e) {
            $this->line('AUTH FAIL');
            $this->line('reason: ' . $e->getMessage());
            if ($e->getCode() >= 400) {
                $this->line('http_status: ' . $e->getCode());
                if (str_contains($e->getMessage(), ' — ')) {
                    $parts = explode(' — ', $e->getMessage(), 2);
                    if (isset($parts[1])) {
                        $this->line('body_preview: ' . $this->maskTokens(substr($parts[1], 0, 500)));
                    }
                }
            }
            $this->printHint();
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->line('AUTH FAIL');
            $this->line('reason: ' . $e->getMessage());
            $this->printHint();
            return self::FAILURE;
        }

        $this->line('AUTH OK');
        $this->line('token_len: ' . strlen($authToken));

        $payload = $this->decodeJwtPayload($authToken);
        if ($payload !== null) {
            if (isset($payload['exp'])) {
                $this->line('exp: ' . (is_numeric($payload['exp']) ? date('c', (int) $payload['exp']) : $payload['exp']));
            }
            if (isset($payload['iat'])) {
                $this->line('iat: ' . (is_numeric($payload['iat']) ? date('c', (int) $payload['iat']) : $payload['iat']));
            }
        }

        return self::SUCCESS;
    }

    private function maskTokens(string $s): string
    {
        return preg_replace('/"(?:auth_token|refresh_token|token|access_token)"\s*:\s*"[^"]*"/i', '"***":"***"', $s);
    }

    /**
     * Decode JWT payload (middle part). Returns assoc array or null. No token output.
     */
    private function decodeJwtPayload(string $jwt): ?array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return null;
        }
        $payload = $parts[1];
        $payload = str_replace(['-', '_'], ['+', '/'], $payload);
        $payload = base64_decode($payload, true);
        if ($payload === false) {
            return null;
        }
        $data = json_decode($payload, true);
        return is_array($data) ? $data : null;
    }

    private function printHint(): void
    {
        $this->newLine();
        $this->line('  Run: php artisan trendagent:auth:login');
        $this->line('  Or:  php artisan trendagent:auth:save-refresh "<refresh_token_from_browser>"');
    }
}
