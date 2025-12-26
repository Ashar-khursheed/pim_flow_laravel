<?php
namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;
class Kernel extends ConsoleKernel
{
    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule)
    {
        // Deactivate expired coupons every hour
        $schedule->command('coupons:deactivate-expired')->hourly();

        // Generate product feed every hour
        $schedule->command('feed:generate')->hourly();
        $schedule->command('seo:dailyUpdateLlmsSeo')->dailyAt('02:00');
        $schedule->command('finance:overdue-status')->dailyAt('02:00');
    }
}
