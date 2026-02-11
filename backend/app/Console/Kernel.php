<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // TrendAgent: blocks list every 15 min; detail jobs are dispatched by SyncBlocksJob after run
        $schedule->command('trendagent:dispatch:blocks')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10);

        // TrendAgent: apartments list every 15 min; detail jobs are dispatched by SyncApartmentsJob after run
        $schedule->command('trendagent:dispatch:apartments')
            ->everyFifteenMinutes()
            ->withoutOverlapping(10);

        // TrendAgent: refresh stale/missing block details (hourly)
        $schedule->command('trendagent:dispatch:stale-block-details')
            ->hourly()
            ->withoutOverlapping();

        // TrendAgent: refresh stale/missing apartment details (hourly)
        $schedule->command('trendagent:dispatch:stale-apartment-details')
            ->hourly()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
