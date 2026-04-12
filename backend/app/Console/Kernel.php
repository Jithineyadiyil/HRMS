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

        // Mark overdue requests daily (closure — no artisan command required)
        $schedule->call(function () {
            \App\Models\EmployeeRequest::whereNotIn('status', ['completed','rejected','cancelled'])
                ->where('due_date', '<', now()->toDateString())
                ->update(['is_overdue' => true]);
        })->dailyAt('07:00')->timezone('Asia/Riyadh')->withoutOverlapping();

        // Auto-generate contract renewal requests 60 days before expiry
        $schedule->command('contracts:generate-renewals')
                 ->dailyAt('06:00')
                 ->timezone('Asia/Riyadh')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/contract-renewals.log'));
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
