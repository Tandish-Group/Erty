<?php

namespace App\Console;

use App\Jobs\ProcessTenantBilling;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {   
        // Lead tasks past their due time: one reminder per task to the agent.
        $schedule->command('tasks:notify-overdue')->hourly()->withoutOverlapping()->timezone(config('app.timezone'));

        // Activity log entries past their keeping period.
        $schedule->command('activity:prune')->dailyAt('00:30')->withoutOverlapping()->timezone(config('app.timezone'));

        // Run backup commands
        $schedule->command('backup:clean')->daily()->at('01:00')->withoutOverlapping()->timezone(config('app.timezone'));
        $schedule->command('backup:run')->daily()->at('01:30')->withoutOverlapping()->timezone(config('app.timezone'));

        // Process tenant billing daily
        // Runs in-process: the queue has no worker, and the run is quick and safe to repeat.
        $schedule->job(new ProcessTenantBilling, null, 'sync')
            ->dailyAt('23:00')->withoutOverlapping()->timezone(config('app.timezone'));

        // Period commission (net of expenses, flat per tenant, tiered) for last month, waiting for approval.
        $schedule->command('commissions:calculate')
            ->monthlyOn(1, '02:00')->withoutOverlapping()->onOneServer()->timezone(config('app.timezone'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }

}
