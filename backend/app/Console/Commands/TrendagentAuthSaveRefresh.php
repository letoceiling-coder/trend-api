<?php

namespace App\Console\Commands;

use App\Integrations\TrendAgent\Auth\TrendAuthService;
use Illuminate\Console\Command;

class TrendagentAuthSaveRefresh extends Command
{
    protected $signature = 'trendagent:auth:save-refresh
        {token : refresh_token from browser cookies}
        {--phone= : Phone (optional, falls back to TRENDAGENT_DEFAULT_PHONE)}';

    protected $description = 'Save TrendAgent refresh_token from browser (no interactive prompt)';

    public function handle(TrendAuthService $auth): int
    {
        $token = (string) $this->argument('token');
        if ($token === '') {
            $this->error('Token is required.');
            return self::FAILURE;
        }

        $phone = (string) $this->option('phone') !== ''
            ? (string) $this->option('phone')
            : (string) config('trendagent.default_phone', '');

        if ($phone === '') {
            $this->error('Phone not provided. Use --phone or set TRENDAGENT_DEFAULT_PHONE in .env');
            return self::FAILURE;
        }

        $session = $auth->storeSessionFromRefreshToken($phone, $token);

        $this->info('Refresh token saved successfully.');
        $this->line('Provider: ' . $session->provider);
        $this->line('Phone: ' . $this->maskPhone($session->phone));
        $this->line('Last login at: ' . $session->last_login_at);

        return self::SUCCESS;
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
