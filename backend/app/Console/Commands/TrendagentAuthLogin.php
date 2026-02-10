<?php

namespace App\Console\Commands;

use App\Integrations\TrendAgent\Auth\TrendAuthService;
use App\Integrations\TrendAgent\Auth\TrendSsoClient;
use Illuminate\Console\Command;

class TrendagentAuthLogin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trendagent:auth:login {phone} {password} {--lang=ru}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Login to TrendAgent SSO and store refresh_token in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var TrendAuthService $auth */
        $auth = app(TrendAuthService::class);
        /** @var TrendSsoClient $sso */
        $sso = app(TrendSsoClient::class);

        $phone = (string) $this->argument('phone');
        $password = (string) $this->argument('password');
        $lang = (string) $this->option('lang');

        $this->info(sprintf('Logging into TrendAgent SSO as %s...', $phone));

        $refreshToken = null;
        try {
            $result = $sso->login($phone, $password, $lang);
            $refreshToken = $result['refresh_token'] ?? null;
            if (($result['needs_manual_token'] ?? false) === true) {
                $refreshToken = null;
            }
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->warn('You can still save a refresh_token from your browser:');
        }

        if (empty($refreshToken)) {
            $this->line('  - Log in at https://spb.trendagent.ru with this account.');
            $this->line('  - DevTools → Application → Cookies → copy "refresh_token" value.');
            $this->line('  - Or Network → SSO response → Set-Cookie header.');

            $manual = $this->secret('Paste the refresh_token here (input will be hidden):');

            if (! $manual) {
                $this->error('No refresh_token provided. Aborting.');

                return self::FAILURE;
            }

            $refreshToken = $manual;
        }

        $session = $auth->storeSessionFromRefreshToken($phone, $refreshToken);

        $this->info('TrendAgent SSO login successful.');
        $this->line('Provider: ' . $session->provider);
        $this->line('Phone: ' . $session->phone);
        $this->line('Last login at: ' . $session->last_login_at);

        return self::SUCCESS;
    }
}
