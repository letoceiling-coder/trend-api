<?php

namespace App\Console\Commands;

use App\Models\Domain\TrendAgent\TaCity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TaFillAllCommand extends Command
{
    protected $signature = 'ta:fill-all
                            {--lang=ru : Language}
                            {--blocks-pages=5 : Max pages for blocks per region}
                            {--apartments-pages=3 : Max pages for apartments per region}';

    protected $description = 'Разово загрузить списки блоков и квартир по всем регионам (из ta_cities); логирование ошибок';

    public function handle(): int
    {
        $lang = $this->option('lang');
        $blocksPages = (int) $this->option('blocks-pages');
        $apartmentsPages = (int) $this->option('apartments-pages');

        $regions = TaCity::getRegionsToSync();
        if ($regions->isEmpty()) {
            $this->error('No regions: run php artisan trendagent:seed-cities or set TRENDAGENT_DEFAULT_CITY_ID.');
            Log::warning('ta:fill-all: no regions to sync');
            return self::FAILURE;
        }

        $this->info('Regions: ' . $regions->count() . ' (lang=' . $lang . ')');
        $this->newLine();

        $okBlocks = [];
        $failBlocks = [];
        $okApartments = [];
        $failApartments = [];

        foreach ($regions as $r) {
            $cityId = $r['city_id'];
            $key = $r['key'];
            $name = $r['name'];

            $this->line("--- {$key} ({$name}) ---");

            $exitBlocks = Artisan::call('trendagent:sync:blocks', [
                '--city' => $cityId,
                '--lang' => $lang,
                '--max-pages' => $blocksPages,
                '--count' => 50,
            ]);
            $out = trim(Artisan::output());
            if ($out !== '') {
                $this->line($out);
            }
            if ($exitBlocks !== 0) {
                $failBlocks[] = $key;
                Log::error('ta:fill-all blocks sync failed', ['city_id' => $cityId, 'key' => $key]);
            } else {
                $okBlocks[] = $key;
            }

            $exitApts = Artisan::call('trendagent:sync:apartments', [
                '--city' => $cityId,
                '--lang' => $lang,
                '--max-pages' => $apartmentsPages,
                '--count' => 50,
            ]);
            $out = trim(Artisan::output());
            if ($out !== '') {
                $this->line($out);
            }
            if ($exitApts !== 0) {
                $failApartments[] = $key;
                Log::error('ta:fill-all apartments sync failed', ['city_id' => $cityId, 'key' => $key]);
            } else {
                $okApartments[] = $key;
            }

            $this->newLine();
        }

        $this->info('=== Summary ===');
        $this->table(
            ['Scope', 'OK', 'Failed', 'Failed keys'],
            [
                ['blocks', count($okBlocks), count($failBlocks), count($failBlocks) > 0 ? implode(', ', $failBlocks) : '—'],
                ['apartments', count($okApartments), count($failApartments), count($failApartments) > 0 ? implode(', ', $failApartments) : '—'],
            ]
        );
        if (count($failBlocks) > 0 || count($failApartments) > 0) {
            $this->warn('Errors logged to storage/logs/laravel.log');
            return self::FAILURE;
        }
        $this->info('Done. Run php artisan ta:stats to see counts.');
        return self::SUCCESS;
    }
}
