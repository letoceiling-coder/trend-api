<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

class TaFillCommand extends Command
{
    protected $signature = 'ta:fill
                            {--city= : City ID (defaults to TRENDAGENT_DEFAULT_CITY_ID)}
                            {--lang=ru : Language}
                            {--blocks-pages=10 : Max pages for blocks}
                            {--apartments-pages=5 : Max pages for apartments}';

    protected $description = 'Разово загрузить списки блоков и квартир в БД (синк в текущем процессе, без очереди)';

    public function handle(): int
    {
        $cityId = $this->option('city') ?? Config::get('trendagent.default_city_id');
        $lang = $this->option('lang');
        $blocksPages = (int) $this->option('blocks-pages');
        $apartmentsPages = (int) $this->option('apartments-pages');

        if (! $cityId) {
            $this->error('City ID is required. Set TRENDAGENT_DEFAULT_CITY_ID in .env or use --city=...');
            return self::FAILURE;
        }

        $this->info('Fill TA DB: city=' . $cityId . ', lang=' . $lang);
        $this->line('Blocks max pages: ' . $blocksPages . ', Apartments max pages: ' . $apartmentsPages);
        $this->newLine();

        $exitBlocks = Artisan::call('trendagent:sync:blocks', [
            '--city' => $cityId,
            '--lang' => $lang,
            '--max-pages' => $blocksPages,
            '--count' => 50,
        ]);
        $this->line(Artisan::output());
        if ($exitBlocks !== 0) {
            $this->error('Blocks sync failed.');
            return self::FAILURE;
        }

        $this->newLine();
        $exitApts = Artisan::call('trendagent:sync:apartments', [
            '--city' => $cityId,
            '--lang' => $lang,
            '--max-pages' => $apartmentsPages,
            '--count' => 50,
        ]);
        $this->line(Artisan::output());
        if ($exitApts !== 0) {
            $this->error('Apartments sync failed.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Done. Run php artisan ta:stats to see counts.');
        return self::SUCCESS;
    }
}
