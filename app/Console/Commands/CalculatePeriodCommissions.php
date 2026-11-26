<?php

namespace App\Console\Commands;

use App\Services\Commission\CommissionPeriodService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Period-based commission (net of expenses, flat per tenant, tiered) for a month, written for approval. */
class CalculatePeriodCommissions extends Command
{
    protected $signature = 'commissions:calculate {--month= : YYYY-MM, default last month} {--business= : Owner user id; omit for every business}';

    protected $description = 'Calculate period-based commission for a month (entries wait for approval)';

    public function handle(CommissionPeriodService $periods): int
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth() : now()->subMonthNoOverflow()->startOfMonth();
        $from = $month->toDateString();
        $to = $month->copy()->endOfMonth()->toDateString();
        $owners = $this->option('business') ? collect([(int) $this->option('business')]) : DB::table('businesses')->pluck('user_id');
        foreach ($owners as $ownerId) {
            $r = $periods->calculate((int) $ownerId, $from, $to);
            $this->line("Business owner {$ownerId}, {$from} to {$to}: {$r['created']} new, {$r['updated']} updated, {$r['adjustments']} adjustments.");
        }
        return self::SUCCESS;
    }
}
