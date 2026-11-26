<?php

namespace App\Console\Commands;

use App\Models\LeadTask;
use App\Services\NotificationCenter;
use Illuminate\Console\Command;

/**
 * Tell agents about lead tasks that have gone past their due time, and the sale's agent and finance staff
 * about house-sale instalments past their due date: once each, in the bell panel.
 */
class NotifyOverdueTasks extends Command
{
    protected $signature = 'tasks:notify-overdue';

    protected $description = 'Notify agents about overdue lead tasks and sale instalments (once each)';

    public function handle(NotificationCenter $notifications): int
    {
        $sent = 0;
        LeadTask::with('lead')
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('sla_breach_at')->where('sla_breach_at', '<', now())
            ->whereNotNull('assigned_agent_id')
            ->orderBy('id')
            ->chunkById(200, function ($tasks) use ($notifications, &$sent) {
                foreach ($tasks as $task) {
                    $sent += $notifications->taskOverdue($task) ? 1 : 0;
                }
            });
        $this->info("Overdue task reminders sent: {$sent}");

        $instalments = 0;
        \App\Models\PropertySaleInstalment::whereIn('status', ['due', 'part_paid'])->whereNull('overdue_notified_at')
            ->whereDate('due_date', '<', today())
            ->whereIn('sale_id', \App\Models\PropertySale::withoutGlobalScopes()->whereIn('status', \App\Models\PropertySale::LIVE)->select('id'))
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($notifications, &$instalments) {
                foreach ($rows as $instalment) {
                    $notifications->instalmentOverdue($instalment);
                    $instalment->forceFill(['overdue_notified_at' => now()])->save();
                    $instalments++;
                }
            });
        $this->info("Overdue sale instalment reminders sent: {$instalments}");
        return self::SUCCESS;
    }
}
