<?php

namespace App\Console\Commands;

use App\Models\Domain\TrendAgent\TaContractChange;
use Illuminate\Console\Command;

class TaContractLastCommand extends Command
{
    protected $signature = 'ta:contract:last
                            {--limit=20 : Max rows per endpoint}';

    protected $description = 'Show last contract changes per endpoint (table)';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        if ($limit < 1) {
            $limit = 20;
        }

        $endpoints = TaContractChange::query()
            ->select('endpoint', 'city_id', 'lang')
            ->distinct()
            ->get();

        if ($endpoints->isEmpty()) {
            $this->info('No contract changes recorded yet.');
            return self::SUCCESS;
        }

        $rows = [];
        foreach ($endpoints as $ep) {
            $last = TaContractChange::query()
                ->where('endpoint', $ep->endpoint)
                ->where('city_id', $ep->city_id)
                ->where('lang', $ep->lang)
                ->orderBy('detected_at', 'desc')
                ->limit($limit)
                ->get();
            foreach ($last as $c) {
                $rows[] = [
                    $c->endpoint,
                    $c->city_id ?? '-',
                    $c->lang ?? '-',
                    substr($c->old_payload_hash, 0, 12) . '..',
                    substr($c->new_payload_hash, 0, 12) . '..',
                    $c->detected_at->toIso8601String(),
                ];
            }
        }

        $this->table(
            ['endpoint', 'city_id', 'lang', 'old_hash', 'new_hash', 'detected_at'],
            $rows
        );
        return self::SUCCESS;
    }
}
