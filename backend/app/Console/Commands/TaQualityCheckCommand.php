<?php

namespace App\Console\Commands;

use App\Domain\TrendAgent\Quality\DataQualityRunner;
use App\Models\Domain\TrendAgent\TaDataQualityCheck;
use Illuminate\Console\Command;

class TaQualityCheckCommand extends Command
{
    protected $signature = 'ta:quality:check
                            {--scope= : Scope: blocks|apartments|block_detail|apartment_detail}
                            {--limit=200 : Max entities to check per scope}
                            {--cap=500 : Max records to write per run}';

    protected $description = 'Run data quality checks and write results to ta_data_quality_checks';

    public function handle(): int
    {
        $scope = $this->option('scope');
        $limit = (int) $this->option('limit');
        $cap = (int) $this->option('cap');

        if ($scope === null || $scope === '') {
            $scope = 'all';
        }
        $scopes = $scope === 'all'
            ? ['blocks', 'apartments', 'block_detail', 'apartment_detail']
            : array_map('trim', explode(',', $scope));

        $runner = app(DataQualityRunner::class);
        $total = 0;
        foreach ($scopes as $s) {
            if (! in_array($s, TaDataQualityCheck::SCOPES, true)) {
                $this->warn("Unknown scope: {$s}, skipped.");
                continue;
            }
            $written = $runner->runScope($s, $limit, $cap);
            $total += $written;
            $this->info("Scope {$s}: {$written} check(s) recorded.");
        }
        $this->info("Total: {$total} check(s) recorded.");
        return self::SUCCESS;
    }
}
