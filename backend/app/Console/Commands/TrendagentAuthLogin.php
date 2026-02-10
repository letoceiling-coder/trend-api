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

        $result = $sso->login($phone, $password, $lang);

        $refreshToken = $result['refresh_token'] ?? null;

        if (($result['needs_manual_token'] ?? false) === true || empty($refreshToken)) {
            $this->warn('SSO login completed, but refresh_token is not available automatically.');
            $this->line('To continue, please obtain the refresh_token manually from your browser session:');
            $this->line('  - Open TrendAgent in your browser and log in with the same account.');
            $this->line('  - Open DevTools → Application → Cookies → find "refresh_token".');
            $this->line('  - Or take it from Network → SSO response Set-Cookie header.');

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
