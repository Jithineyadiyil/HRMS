<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run every day at 00:05 AM Riyadh time
        $schedule->command('leave:accrue')
                 ->dailyAt('00:05')
                 ->timezone('Asia/Riyadh')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/leave-accrual.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
