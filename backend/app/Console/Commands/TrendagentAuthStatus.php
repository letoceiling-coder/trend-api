<?php

namespace App\Console\Commands;

use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Console\Command;

class TrendagentAuthStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trendagent:auth:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show current TrendAgent SSO session status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /** @var TaSsoSession|null $session */
        $session = TaSsoSession::query()
            ->where('provider', 'trendagent')
            ->orderByDesc('last_login_at')
            ->first();

        if (! $session) {
            $this->warn('No TrendAgent SSO session found.');

            return self::SUCCESS;
        }

        $this->info('TrendAgent SSO session:');
        $this->line('  Provider: ' . $session->provider);
        $this->line('  Phone: ' . $session->phone);
        $this->line('  Last login at: ' . $session->last_login_at);
        $this->line('  Last auth_token at: ' . ($session->last_auth_token_at ?? 'never'));
        $this->line('  Has refresh_token: ' . ($session->refresh_token ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
