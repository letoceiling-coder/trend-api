<?php

namespace App\Console;

use App\Jobs\TaQueueHeartbeatJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;

class Kernel extends ConsoleKernel
{
    public const SCHEDULE_LAST_RUN_CACHE_KEY = 'ta:schedule:last_run_at';
    public const SCHEDULE_LAST_RUN_TTL_SECONDS = 600;

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Runtime verification: schedule ran (health checks last_run_at)
        $schedule->call(function (): void {
            Cache::put(self::SCHEDULE_LAST_RUN_CACHE_KEY, now(), self::SCHEDULE_LAST_RUN_TTL_SECONDS);
        })->everyMinute();

        // Runtime verification: queue worker heartbeat (worker runs this job and writes cache)
        $schedule->job(new TaQueueHeartbeatJob())->everyMinute();

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

        // TrendAgent: Telegram alerts check every 5 min
        $schedule->command('ta:alerts:check --since=15m')
            ->everyFiveMinutes()
            ->withoutOverlapping(5);
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
