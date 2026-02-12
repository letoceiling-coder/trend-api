<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrendagentSeedCities extends Command
{
    protected $signature = 'trendagent:seed-cities';

    protected $description = 'Fill ta_cities with TrendAgent regions (Moscow, SPb, Krasnodar, etc.)';

    private static function cities(): array
    {
        return [
            ['key' => 'msk', 'city_id' => '5a5cb42159042faa9a218d04', 'name' => 'Москва', 'base_url' => 'https://msk.trendagent.ru'],
            ['key' => 'spb', 'city_id' => '58c665588b6aa52311afa01b', 'name' => 'Санкт-Петербург', 'base_url' => 'https://spb.trendagent.ru'],
            ['key' => 'krd', 'city_id' => '604b5243f9760700074ac345', 'name' => 'Краснодарский край, Сочи, Республика Адыгея', 'base_url' => 'https://krasnodar.trendagent.ru'],
            ['key' => 'rnd', 'city_id' => '61926fb5bb267a0008de132b', 'name' => 'Ростов-на-Дону', 'base_url' => 'https://rostov.trendagent.ru'],
            ['key' => 'crimea', 'city_id' => '682700dd0e7daf77097d0779', 'name' => 'Крым', 'base_url' => 'https://crimea.trendagent.ru'],
            ['key' => 'kzn', 'city_id' => '642157fca50429d21e3aa14f', 'name' => 'Казань', 'base_url' => 'https://kzn.trendagent.ru'],
            ['key' => 'ufa', 'city_id' => '674eff862307c824cf56ced3', 'name' => 'Уфа', 'base_url' => 'https://ufa.trendagent.ru'],
            ['key' => 'ekb', 'city_id' => '650974f78d34c0f790a012a9', 'name' => 'Екатеринбург', 'base_url' => 'https://ekb.trendagent.ru'],
            ['key' => 'nsk', 'city_id' => '618120c1a56997000866c4d8', 'name' => 'Новосибирск', 'base_url' => 'https://nsk.trendagent.ru'],
            ['key' => 'dubai', 'city_id' => '63d10e79a8975354f0d41c80', 'name' => 'Абу-Даби, Дубай, Рас-Эль-Хайма, Шарджа, Умм-эль-Кайвайн, Аджман', 'base_url' => 'https://trendagent.ae'],
        ];
    }

    public function handle(): int
    {
        if (! Schema::hasTable('ta_cities')) {
            $this->error('Table ta_cities does not exist. Run: php artisan migrate');
            return self::FAILURE;
        }

        $this->info('Seeding ta_cities...');

        foreach (self::cities() as $city) {
            DB::table('ta_cities')->updateOrInsert(
                ['key' => $city['key']],
                [
                    'city_id' => $city['city_id'],
                    'name' => $city['name'],
                    'base_url' => $city['base_url'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $this->line('  ' . $city['key'] . ' — ' . $city['name']);
        }

        $this->info('Done. ' . count(self::cities()) . ' region(s) in ta_cities.');
        return self::SUCCESS;
    }
}
