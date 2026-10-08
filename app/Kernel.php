<?php

declare(strict_types=1);

namespace App;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Kernel as ConsoleKernel;

final class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('servers:cleanup-expired --config='.\escapeshellarg((string) \env('SHIPPER_CONFIG', 'shipper.yml')))
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require \base_path('routes/console.php');
    }
}
