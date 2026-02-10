<?php

namespace App\Console\Commands;

use App\Models\Domain\TrendAgent\TaSsoSession;
use Illuminate\Console\Command;

class TrendagentAuthDebug extends Command
{
    protected $signature = 'trendagent:auth:debug';

    protected $description = 'Diagnostics for ta_sso_sessions (no tokens printed)';

    public function handle(): int
    {
        $count = TaSsoSession::query()->where('provider', 'trendagent')->count();
        $this->line('ta_sso_sessions (provider=trendagent): ' . $count);

        /** @var TaSsoSession|null $last */
        $last = TaSsoSession::query()
            ->where('provider', 'trendagent')
            ->orderByDesc('last_login_at')
            ->first();

        if (! $last) {
            $this->line('Last record: none');
            return self::SUCCESS;
        }

        $this->line('Last record id: ' . $last->id);

        $rawToken = $last->getRawOriginal('refresh_token');
        $this->line('refresh_token raw is null? ' . ($rawToken === null ? 'yes' : 'no'));

        $decrypted = $last->refresh_token;
        $decryptOk = $decrypted !== null && $decrypted !== '';
        $this->line('decrypt ok? ' . ($decryptOk ? 'yes' : 'no') . ($decryptOk ? ' token_len=' . strlen($decrypted) : ''));

        $this->line('city_id: ' . ($last->city_id ?? 'null'));
        $this->line('app_id: ' . ($last->app_id ?? 'null'));
        $this->line('last_login_at: ' . ($last->last_login_at?->format('c') ?? 'null'));

        return self::SUCCESS;
    }
}
