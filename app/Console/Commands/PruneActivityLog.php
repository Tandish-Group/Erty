<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;

/** Removes activity log entries older than config('erty.activity_log.keep_days'). Runs every night. */
class PruneActivityLog extends Command
{
    protected $signature = 'activity:prune {--days= : Keep this many days instead of the configured number}';

    protected $description = 'Remove activity log entries older than the configured number of days';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?: config('erty.activity_log.keep_days', 365)));
        $deleted = 0;
        do {
            $ids = ActivityLog::where('created_at', '<', now()->subDays($days))->limit(5000)->pluck('id');
            $deleted += $ids->isEmpty() ? 0 : ActivityLog::whereIn('id', $ids)->delete();
        } while ($ids->count() === 5000);
        $this->info("Removed {$deleted} activity log " . ($deleted === 1 ? 'entry' : 'entries') . " older than {$days} days.");

        return self::SUCCESS;
    }
}
