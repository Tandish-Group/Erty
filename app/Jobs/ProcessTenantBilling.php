<?php
namespace App\Jobs;

use App\Models\TenantManagement;
use App\Services\Access\PropertyAccess;
use App\Services\Rent\TenantChargesService;
use App\Services\Tenancy\TenantMovementService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The daily tenant run (safe to repeat): carry out transfers whose date has arrived, issue every rent and
 * service-charge invoice due by today (plus the lead days), and add late fees to rent past its due date.
 */
class ProcessTenantBilling implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?string $date = null)
    {
    }

    public function handle(): array
    {
        $today = $this->date ? Carbon::parse($this->date)->startOfDay() : Carbon::today();
        $stats = ['transfers' => 0, 'invoices' => 0, 'late_fees' => 0, 'failed' => 0];

        app(PropertyAccess::class)->unrestricted(function () use ($today, &$stats) {
            try {
                $stats['transfers'] = app(TenantMovementService::class)->executeDue($today);
            } catch (\Throwable $e) {
                Log::error('Scheduled transfers failed: ' . $e->getMessage());
            }

            $charges = app(TenantChargesService::class);
            TenantManagement::query()->whereIn('status', ['Active', 'On Notice'])->orderBy('id')->each(function (TenantManagement $tenancy) use ($charges, $today, &$stats) {
                try {
                    $issued = $charges->billUpTo($tenancy, $today, notify: false);
                    $fees = $charges->applyLateFees($tenancy, $today);
                    $charges->notify($tenancy, $issued->merge($fees));
                    $stats['invoices'] += $issued->count();
                    $stats['late_fees'] += $fees->count();
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    Log::error("Billing failed for tenancy {$tenancy->id}: " . $e->getMessage());
                }
            });
        });

        Log::info('Daily tenant billing run', $stats + ['date' => $today->toDateString()]);
        return $stats;
    }
}
