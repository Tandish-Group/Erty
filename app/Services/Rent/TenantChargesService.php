<?php

namespace App\Services\Rent;

use App\Mail\TenantChargesMail;
use App\Models\TenantBilling;
use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantTenancyBilling;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * What a tenant is charged and when.
 *
 * Rent is billed in advance on the tenancy's own cycle: periods start on the move-in date
 * (after any build-out period), each period is billed on its first day and is due after the
 * grace days. Quarterly "post-pay" tenancies are billed at the period end instead.
 * Commercial tenancies with a service charge get a separate service-charge invoice per period.
 * The move-in invoice set is the deposit plus the first period.
 */
class TenantChargesService
{
    /** Invoices are issued this many days before a period starts. */
    public const LEAD_DAYS = 4;

    public function __construct(private TenantAccountService $account) {}

    // ─── Periods ──────────────────────────────────────────────────────────

    public static function monthsPerPeriod(?string $frequency): ?int
    {
        return match (strtolower((string) $frequency)) {
            'weekly' => null,
            'quarterly' => 3,
            'bi-annually', 'biannually', 'semi-annually' => 6,
            'annually', 'yearly' => 12,
            default => 1,
        };
    }

    /** First day rent is charged from: the lease start (or move-in) after any build-out period; after a transfer, the old cycle. */
    public function anchor(TenantManagement $tenancy): Carbon
    {
        if ($tenancy->billing_start_date) {
            return Carbon::parse($tenancy->billing_start_date)->startOfDay();
        }
        $start = Carbon::parse($tenancy->lease_start_date ?? $tenancy->move_in_date ?? $tenancy->created_at)->startOfDay();
        return $start->addDays((int) ($tenancy->build_out_period_days ?? 0));
    }

    /** @return array{start: Carbon, end: Carbon, due: Carbon} */
    public function period(TenantManagement $tenancy, int $n): array
    {
        $anchor = $this->anchor($tenancy);
        $months = self::monthsPerPeriod($tenancy->payment_frequency);
        if ($months === null) {
            $start = $anchor->copy()->addWeeks($n);
            $end = $start->copy()->addWeek()->subDay();
        } else {
            $start = $anchor->copy()->addMonthsNoOverflow($n * $months);
            $end = $anchor->copy()->addMonthsNoOverflow(($n + 1) * $months)->subDay();
        }
        $grace = (int) ($tenancy->grace_period_days ?? 0);
        $postPay = strtolower((string) $tenancy->payment_frequency) === 'quarterly' && $tenancy->tenancy_quarterly_payment_type === 'post_pay';
        $due = ($postPay ? $end->copy() : $start->copy())->addDays($grace);
        return ['start' => $start, 'end' => $end, 'due' => $due, 'bill_on' => $postPay ? $end->copy() : $start->copy()];
    }

    /** Rent for one period. */
    public function rentFor(TenantManagement $tenancy): float
    {
        $months = self::monthsPerPeriod($tenancy->payment_frequency);
        return round((float) $tenancy->monthly_payment * ($months ?? 12 / 52), 2);
    }

    /** Service charge for one period (commercial: rate per sq ft per month × occupied area). */
    public function serviceChargeFor(TenantManagement $tenancy): float
    {
        $rate = (float) ($tenancy->service_charge_per_sq_ft ?? 0);
        $area = (float) ($tenancy->occupied_sq_area ?? 0);
        if ($rate <= 0 || $area <= 0) {
            return 0.0;
        }
        $months = self::monthsPerPeriod($tenancy->payment_frequency);
        return round($rate * $area * ($months ?? 12 / 52), 2);
    }

    // ─── Issuing ──────────────────────────────────────────────────────────

    /**
     * Move-in: the deposit invoice and every period that has started (or starts within the lead days),
     * at least the first. Periods ending on or before "rent paid up to" are recorded as settled before Erty.
     *
     * @return Collection<int, TenantInvoice>
     */
    public function startBilling(TenantManagement $tenancy, bool $notify = true): Collection
    {
        $issued = collect();
        DB::transaction(function () use ($tenancy, &$issued) {
            $deposit = round((float) ($tenancy->security_deposit ?? 0), 2);
            $hasDeposit = TenantInvoice::query()->where('tenancy_id', $tenancy->id)->where('kind', 'deposit')->exists();
            if ($deposit > 0 && !$hasDeposit && !$tenancy->previous_tenancy_id) {
                $moveIn = Carbon::parse($tenancy->move_in_date ?? $tenancy->lease_start_date ?? now())->startOfDay();
                $issued->push($this->issue($tenancy, 'deposit', $deposit, $moveIn, null, null, 'Security deposit (held, refundable under the agreement)'));
            }
            $issued = $issued->merge($this->billUpTo($tenancy, now(), firstAtLeast: true, notify: false));
        });
        if ($notify) {
            $this->notify($tenancy, $issued);
        }
        return $issued;
    }

    /**
     * Issue every period invoice due by $date (plus the lead days) that hasn't been issued yet.
     *
     * @return Collection<int, TenantInvoice>
     */
    public function billUpTo(TenantManagement $tenancy, Carbon $date, bool $firstAtLeast = false, bool $notify = true): Collection
    {
        $issued = collect();
        // Tenants on notice still owe rent until they leave.
        if (!in_array($tenancy->status, ['Active', 'On Notice'], true) || (float) $tenancy->monthly_payment <= 0) {
            return $issued;
        }
        $limit = $date->copy()->startOfDay()->addDays(self::LEAD_DAYS);
        $stop = $tenancy->lease_end_date ? Carbon::parse($tenancy->lease_end_date)->startOfDay() : null;
        $paidUpTo = $tenancy->rent_paid_up_to ? Carbon::parse($tenancy->rent_paid_up_to)->startOfDay() : null;

        DB::transaction(function () use ($tenancy, $limit, $stop, $paidUpTo, $firstAtLeast, &$issued) {
            TenantManagement::query()->whereKey($tenancy->id)->lockForUpdate()->first();
            for ($n = 0; $n < 600; $n++) {
                $p = $this->period($tenancy, $n);
                if ($stop && $p['start']->gt($stop)) {
                    break;
                }
                if ($p['bill_on']->gt($limit) && !($firstAtLeast && $n === 0)) {
                    break;
                }
                if (TenantTenancyBilling::query()->where('tenancy_id', $tenancy->id)->whereDate('billing_period_start', $p['start']->toDateString())->exists()) {
                    continue;
                }
                $settled = $paidUpTo && $p['end']->lte($paidUpTo);
                $billing = $this->recordPeriod($tenancy, $p, $settled);
                if ($settled) {
                    continue;
                }
                $label = $p['start']->format('j M Y') . ' – ' . $p['end']->format('j M Y');
                $issued->push($this->issue($tenancy, 'rent', $this->rentFor($tenancy), $p['due'], $p['start'], $p['end'],
                    ucfirst(strtolower((string) ($tenancy->agreement_type ?: 'Rent'))) . ' for ' . $label, $billing->id));
                $sc = $this->serviceChargeFor($tenancy);
                if ($sc > 0) {
                    $issued->push($this->issue($tenancy, 'service_charge', $sc, $p['due'], $p['start'], $p['end'],
                        'Service charge for ' . $label . ' (' . rtrim(rtrim(number_format((float) $tenancy->occupied_sq_area, 2), '0'), '.') . ' sq ft)', $billing->id));
                }
            }
        });
        if ($notify) {
            $this->notify($tenancy, $issued);
        }
        return $issued;
    }

    /**
     * One late fee per overdue rent invoice once its due date (which already includes the grace days) has passed.
     *
     * @return Collection<int, TenantInvoice>
     */
    public function applyLateFees(TenantManagement $tenancy, Carbon $date): Collection
    {
        $fee = round((float) ($tenancy->late_fee_amount ?? 0), 2);
        $issued = collect();
        if ($fee <= 0) {
            return $issued;
        }
        $overdue = TenantInvoice::query()->where('tenancy_id', $tenancy->id)->where('kind', 'rent')
            ->whereIn('status', [TenantInvoice::UNPAID, TenantInvoice::PARTIAL])
            ->whereDate('due_date', '<', $date->toDateString())->get();
        foreach ($overdue as $invoice) {
            $exists = TenantInvoice::query()->where('related_invoice_id', $invoice->id)->where('kind', 'late_fee')->exists();
            if (!$exists) {
                $issued->push($this->issue($tenancy, 'late_fee', $fee, $date->copy()->startOfDay(), null, null,
                    'Late fee: ' . $invoice->invoice_number . ' was due ' . $invoice->due_date->format('j M Y'), null, $invoice->id));
            }
        }
        return $issued;
    }

    /** A one-off charge (adjustment, damages, a transfer difference, a deposit top-up). */
    public function charge(TenantManagement $tenancy, string $kind, float $amount, string $description, ?Carbon $due = null): TenantInvoice
    {
        return $this->issue($tenancy, $kind, round($amount, 2), $due ?? now()->startOfDay(), null, null, $description);
    }

    public function issue(TenantManagement $tenancy, string $kind, float $amount, Carbon $due, ?Carbon $start, ?Carbon $end, string $description, ?int $billingId = null, ?int $relatedId = null): TenantInvoice
    {
        $previous = $this->account->balance($tenancy);
        $invoice = TenantInvoice::create([
            'invoice_number' => $this->number($kind, $tenancy),
            'tenancy_id' => $tenancy->id,
            'tenant_id' => $tenancy->tenant_profile_id,
            'billing_id' => $billingId,
            'invoice_type' => TenantInvoice::KIND_LABELS[$kind] ?? ucfirst($kind),
            'kind' => $kind,
            'related_invoice_id' => $relatedId,
            'currency' => $tenancy->currency ?: default_currency(),
            'amount' => $amount,
            'current_billing_amount' => $amount,
            'pending_payments_amount' => max(0, $previous), // shown as "previous balance"; never added to the amount
            'pending_payments_data' => json_encode(['previous_balance' => $previous]),
            'paid_amount' => 0,
            'due_date' => $due->toDateString(),
            'billing_period_start' => $start?->toDateString(),
            'billing_period_end' => $end?->toDateString(),
            'status' => TenantInvoice::UNPAID,
            'line_items' => [['type' => $kind, 'description' => $description, 'quantity' => 1, 'unit_price' => $amount, 'total' => $amount]],
        ]);
        $this->account->applyCredit($tenancy, Carbon::now()->startOfDay());
        return $invoice->fresh();
    }

    /** Send the tenant one email for the invoices just issued (deposit, rent, service charge, fees). */
    public function notify(TenantManagement $tenancy, Collection $invoices): void
    {
        if ($invoices->isEmpty()) {
            return;
        }
        try {
            $tenancy->loadMissing('tenantProfile');
            $profile = $tenancy->tenantProfile;
            $email = $profile?->tenant_type === 'Business' ? ($profile->business_email ?: $profile->tenant_email) : ($profile?->tenant_email ?: $profile?->business_email);
            if ($email) {
                Mail::to($email)->send(new TenantChargesMail($tenancy, $invoices->map->fresh(), $this->account->summary($tenancy)));
            }
        } catch (\Throwable $e) {
            Log::warning('Tenant charges email failed: ' . $e->getMessage(), ['tenancy_id' => $tenancy->id]);
        }
    }

    private function recordPeriod(TenantManagement $tenancy, array $p, bool $settled): TenantBilling
    {
        $tenancyBilling = TenantTenancyBilling::create([
            'tenancy_id' => $tenancy->id,
            'tenancy_ref' => $tenancy->tenancy_ref,
            'currency' => $tenancy->currency ?: default_currency(),
            'billing_date' => $p['bill_on']->toDateString(),
            'billing_period_start' => $p['start']->toDateString(),
            'billing_period_end' => $p['end']->toDateString(),
            'billing_period_display' => $p['start']->format('j M') . '–' . $p['end']->format('j M y'), // column holds 20 characters
            'tenancy_payment_frequency' => $tenancy->payment_frequency ?: 'Monthly',
            'payment_due_date' => $p['due']->toDateString(),
            'grace_period' => (int) ($tenancy->grace_period_days ?? 0),
            'tenancy_billing_amount' => $this->rentFor($tenancy),
            'status' => $settled ? 'Settled before Erty' : 'Invoiced',
            'rent_invoice_dispatched' => !$settled,
            'rent_invoice_dispatched_at' => $settled ? null : now(),
            'paid_amount' => $settled ? $this->rentFor($tenancy) : 0,
            'system_notes' => $settled ? 'Rent for this period was paid before the tenancy was added to Erty.' : null,
        ]);
        return TenantBilling::create([
            'tenancy_id' => $tenancy->id,
            'tenancy_ref' => $tenancy->tenancy_ref,
            'currency' => $tenancy->currency ?: default_currency(),
            'billing_date' => $p['bill_on']->toDateString(),
            'billing_type' => 'tenancy_only',
            'tenancy_billing_id' => $tenancyBilling->id,
            'total_amount' => $this->rentFor($tenancy) + $this->serviceChargeFor($tenancy),
            'status' => $settled ? 'Settled before Erty' : 'Invoiced',
        ]);
    }

    private function number(string $kind, TenantManagement $tenancy): string
    {
        $prefix = match ($kind) {
            'service_charge' => 'SC',
            'deposit' => 'DEP',
            'late_fee' => 'LF',
            'adjustment' => 'ADJ',
            default => match (strtolower((string) $tenancy->agreement_type)) { 'lease' => 'LEASE', 'rent_to_own' => 'RTO', default => 'RENT' },
        };
        do {
            $number = $prefix . '-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        } while (TenantInvoice::query()->where('invoice_number', $number)->exists());
        return $number;
    }
}
