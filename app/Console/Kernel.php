<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('data-hub:cleanup-temporary-files')->hourly()->withoutOverlapping();
        $schedule->command('fmtrx:communications-dispatch')->everyMinute()->withoutOverlapping()->before(function () {
            \Illuminate\Support\Facades\Cache::put('fmtrx.communications.scheduler_seen', now()->toIso8601String(), 3600);
        });
        $schedule->command('fmtrx:weekly-progress')->weeklyOn(1, '08:00')->timezone(config('app.timezone'))->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
