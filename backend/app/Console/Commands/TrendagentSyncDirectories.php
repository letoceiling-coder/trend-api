<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Sync\TrendAgentSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class TrendagentSyncDirectories extends Command
{
    protected $signature = 'trendagent:sync:directories
                            {--types= : Comma-separated directory types}
                            {--city= : City ID (defaults to config)}
                            {--lang=ru : Language}
                            {--no-raw : Do not store raw payload cache}';

    protected $description = 'Sync directories from TrendAgent apartment API';

    public function handle(TrendAgentSyncService $sync): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $storeRawPayload = ! $this->option('no-raw');

        $typesOption = $this->option('types');
        $types = $typesOption
            ? array_map('trim', explode(',', $typesOption))
            : $this->getDefaultDirectoryTypes();

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID or use --city option.');
            return self::FAILURE;
        }

        $this->line('Syncing directories...');
        $this->line('City: ' . $cityId);
        $this->line('Lang: ' . $lang);
        $this->line('Types: ' . implode(', ', $types));

        $startTime = microtime(true);

        $run = $sync->syncDirectories($types, $cityId, $lang, $storeRawPayload);

        $duration = round((microtime(true) - $startTime) * 1000);

        if ($run->status === 'success') {
            $this->info('✓ Sync completed successfully');
            $this->line('Items fetched: ' . $run->items_fetched);
            $this->line('Items saved: ' . $run->items_saved);
            $this->line('Duration: ' . $duration . 'ms');
            $this->line('Run ID: ' . $run->id);
            return self::SUCCESS;
        } else {
            $this->error('✗ Sync failed');
            $this->line('Error: ' . $run->error_message);
            $this->line('Run ID: ' . $run->id);
            return self::FAILURE;
        }
    }

    /**
     * Default directory types from docs/trendagent/04-filters-and-directories.md
     */
    protected function getDefaultDirectoryTypes(): array
    {
        return [
            'rooms',
            'deadlines',
            'deadline_keys',
            'regions',
            'subways',
            'building_types',
            'finishings',
            'parking_types',
            'locations',
        ];
    }
}
