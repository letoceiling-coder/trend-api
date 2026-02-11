<?php

namespace App\Console\Commands;

use App\Models\Domain\TrendAgent\TaContractChange;
use Carbon\Carbon;
use Illuminate\Console\Command;

class TaContractCheckCommand extends Command
{
    protected $signature = 'ta:contract:check
                            {--since=24h : Period (e.g. 24h, 7d, 1h)}';

    protected $description = 'Show contract changes for period, grouped by endpoint';

    public function handle(): int
    {
        $since = $this->parseSince($this->option('since'));
        if ($since === null) {
            $this->error('Invalid --since. Use e.g. 24h, 7d, 1h');
            return self::FAILURE;
        }

        $changes = TaContractChange::query()
            ->where('detected_at', '>=', $since)
            ->orderBy('detected_at', 'desc')
            ->get();

        if ($changes->isEmpty()) {
            $this->info('No contract changes in the last ' . $this->option('since'));
            return self::SUCCESS;
        }

        $byEndpoint = $changes->groupBy(fn ($c) => $c->endpoint . '|' . ($c->city_id ?? '') . '|' . ($c->lang ?? ''));

        foreach ($byEndpoint as $key => $items) {
            $this->line('');
            $this->info($key);
            $this->line(str_repeat('-', 60));
            foreach ($items as $c) {
                $this->line('  ' . $c->detected_at->toIso8601String() . '  old=' . substr($c->old_payload_hash, 0, 12) . '..  new=' . substr($c->new_payload_hash, 0, 12) . '..');
                if ($c->old_top_keys || $c->new_top_keys) {
                    $this->line('    top_keys: ' . json_encode($c->old_top_keys) . ' -> ' . json_encode($c->new_top_keys));
                }
                if ($c->old_data_keys || $c->new_data_keys) {
                    $this->line('    data_keys: ' . json_encode($c->old_data_keys) . ' -> ' . json_encode($c->new_data_keys));
                }
            }
        }

        $this->newLine();
        $this->info('Total changes: ' . $changes->count());
        return self::SUCCESS;
    }

    private function parseSince(string $value): ?\DateTimeInterface
    {
        $value = strtolower(trim($value));
        if (preg_match('/^(\d+)([hdm])$/', $value, $m)) {
            $num = (int) $m[1];
            if ($num <= 0) {
                return null;
            }
            return match ($m[2]) {
                'h' => now()->subHours($num),
                'd' => now()->subDays($num),
                'm' => now()->subMinutes($num),
                default => null,
            };
        }
        return null;
    }
}
