<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Application console kernel.
 *
 * Schedules all Artisan commands that must run periodically.
 * Replace the Windows Task Scheduler / cron guidance in LEAVE_ACCRUAL_SETUP.md
 * with a single server-side cron entry:
 *
 *   * * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
 */
class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule): void
    {
        // ── Annual Leave Accrual ─────────────────────────────────────────
        // Runs every working day (Sun–Thu) at 00:05 to credit daily accrual
        // for all active employees. The command itself checks for working days,
        // so running it daily on all days is harmless but wastes CPU on weekends.
        $schedule
            ->command('leave:accrue')
            ->weekdays()
            ->at('00:05')
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/leave-accrual.log'));

        // ── Mark Overdue Loans ───────────────────────────────────────────
        // Checks for loan installments past their due date each morning
        $schedule
            ->command('loans:mark-overdue')
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the Artisan commands provided by the application.
     *
     * @return void
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
