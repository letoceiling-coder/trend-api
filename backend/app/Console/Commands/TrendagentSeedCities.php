<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class TrendagentSeedCities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trendagent:seed-cities';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Seeding TrendAgent cities into ta_cities...');

        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\TrendAgentCitiesSeeder::class,
        ]);

        $this->output->write(Artisan::output());

        $this->info('TrendAgent cities seeding completed.');

        return self::SUCCESS;
    }
}
