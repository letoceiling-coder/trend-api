<?php

namespace App\Console\Commands;

use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use Illuminate\Console\Command;

class TrendagentAuthLogin extends Command
{
    protected $signature = 'trendagent:auth:login
        {--phone= : Phone number (optional, falls back to TRENDAGENT_DEFAULT_PHONE)}
        {--password= : Password (optional, falls back to TRENDAGENT_DEFAULT_PASSWORD)}
        {--lang= : Language (default from TRENDAGENT_DEFAULT_LANG or ru)}';

    protected $description = 'Login to TrendAgent SSO and store refresh_token (no interactive prompt)';

    public function handle(TrendAuthService $auth, TrendSsoClient $sso): int
    {
        $phone = $this->option('phone') !== null && (string) $this->option('phone') !== ''
            ? (string) $this->option('phone')
            : (string) config('trendagent.default_phone', '');

        if ($phone === '') {
            $this->error('Phone not provided. Use --phone or set TRENDAGENT_DEFAULT_PHONE in .env');
            return self::FAILURE;
        }

        $password = $this->option('password') !== null && (string) $this->option('password') !== ''
            ? (string) $this->option('password')
            : (string) config('trendagent.default_password', '');

        if ($password === '') {
            $this->error('Password not provided. Use --password or set TRENDAGENT_DEFAULT_PASSWORD in .env');
            return self::FAILURE;
        }

        $langOpt = $this->option('lang');
        $lang = ($langOpt !== null && (string) $langOpt !== '')
            ? (string) $langOpt
            : (string) config('trendagent.default_lang', 'ru');

        $this->info('Logging into TrendAgent SSO...');

        try {
            $result = $sso->login($phone, $password, $lang);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->printSaveRefreshInstruction();
            return self::FAILURE;
        }

        if (($result['needs_manual_token'] ?? false) === true) {
            $this->error('SSO login returned 403 or no token. Save refresh_token manually:');
            $this->printSaveRefreshInstruction();
            return self::FAILURE;
        }

        $refreshToken = $result['refresh_token'] ?? null;
        if (empty($refreshToken)) {
            $this->error('SSO login blocked. Use: php artisan trendagent:auth:save-refresh <token>');
            $this->printSaveRefreshInstruction();
            return self::FAILURE;
        }

        $session = $auth->storeSessionFromRefreshToken($phone, $refreshToken);

        $this->info('TrendAgent SSO login successful.');
        $this->line('Provider: ' . $session->provider);
        $this->line('Phone: ' . $this->maskPhone($session->phone));
        $this->line('Last login at: ' . $session->last_login_at);

        return self::SUCCESS;
    }

    private function printSaveRefreshInstruction(): void
    {
        $this->newLine();
        $this->line('  1. Log in at https://spb.trendagent.ru (or https://sso.trend.tech) in your browser.');
        $this->line('  2. DevTools → Application → Cookies → copy the "refresh_token" value.');
        $this->line('  3. Run: php artisan trendagent:auth:save-refresh "<paste_token>"');
        $this->line('     Optionally: --phone=... if not using TRENDAGENT_DEFAULT_PHONE.');
    }

    private function maskPhone(string $phone): string
    {
        $len = strlen($phone);
        if ($len <= 4) {
            return '***';
        }
        return substr($phone, 0, 2) . str_repeat('*', min($len - 4, 6)) . substr($phone, -2);
    }
}
