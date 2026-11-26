<?php

namespace App\Services\Rent;

use App\Services\Access\PropertyAccess;

use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tenant collection reports. Figures follow the tenant account rules:
 * - "billed" is rent, service charge, late fees and other charges due in the period (not deposits,
 *   which are held and refundable, and not voided or waived charges);
 * - "collected" is what has been applied to those invoices;
 * - arrears are open invoice balances past their due date, aged by days overdue;
 * - money received is confirmed payments by the date received (cash basis).
 * Every query is limited to the signed-in business (tenancies' managed_by).
 */
class TenantReportService
{
    private const REVENUE_KINDS = ['rent', 'service_charge', 'late_fee', 'adjustment'];

    private function baseInvoiceQuery(string $from, string $to, ?int $complexId = null)
    {
        $q = TenantInvoice::query()
            ->join('tenant_management as tm', 'tm.id', '=', 'tenant_invoices.tenancy_id')
            ->join('tenant_profiles as tp', 'tp.id', '=', 'tm.tenant_profile_id')
            ->where('tm.managed_by', business_owner_id())
            ->whereIn('tenant_invoices.kind', self::REVENUE_KINDS)
            ->where('tenant_invoices.status', '!=', TenantInvoice::VOID)
            ->whereBetween('tenant_invoices.due_date', [$from, $to . ' 23:59:59']);

        if ($complexId) {
            $q->where('tm.complex_id', $complexId);
        }

        return $q;
    }

    /** Overall summary cards. */
    public function getSummary(string $from, string $to, ?int $complexId = null): array
    {
        $today = now()->toDateString();
        $rows = $this->baseInvoiceQuery($from, $to, $complexId)
            ->select([
                DB::raw('SUM(tenant_invoices.amount) AS total_expected'),
                DB::raw('SUM(tenant_invoices.paid_amount) AS total_collected'),
                DB::raw('COUNT(tenant_invoices.id) AS total_invoices'),
                DB::raw("SUM(CASE WHEN tenant_invoices.status = 'Paid' THEN 1 ELSE 0 END) AS paid_count"),
            ])
            ->first();
        $overdueCount = $this->baseInvoiceQuery($from, $to, $complexId)
            ->whereIn('tenant_invoices.status', [TenantInvoice::UNPAID, TenantInvoice::PARTIAL])
            ->where('tenant_invoices.due_date', '<', $today)->count();

        $expected = (float) ($rows->total_expected ?? 0);
        $collected = (float) ($rows->total_collected ?? 0);

        $money = $this->moneyQuery($from, $to, $complexId);
        $deposits = $this->depositTotals($complexId);

        return [
            'total_expected' => $expected,
            'total_collected' => $collected,
            'total_outstanding' => max(0, round($expected - $collected, 2)),
            'collection_rate' => $expected > 0 ? round(($collected / $expected) * 100, 1) : 0,
            'total_invoices' => (int) ($rows->total_invoices ?? 0),
            'paid_count' => (int) ($rows->paid_count ?? 0),
            'overdue_count' => $overdueCount,
            'cash_received' => round((float) (clone $money)->where('p.entry_type', 'payment')->where('p.payment_status', TenantPayment::CONFIRMED)->sum('p.amount_paid'), 2),
            'pending_clearance' => round((float) (clone $money)->where('p.entry_type', 'payment')->where('p.payment_status', TenantPayment::PENDING)->sum('p.amount_paid'), 2),
            'refunds_paid' => round((float) (clone $money)->where('p.entry_type', 'refund')->where('p.payment_status', TenantPayment::CONFIRMED)->sum('p.amount_paid'), 2),
            'deposits_held' => $deposits['held'],
            'deposits_outstanding' => $deposits['outstanding'],
            'aging' => $this->agingTotals($complexId),
        ];
    }

    /** Overdue invoices (balance left, due date passed), oldest first, with their aging bucket. */
    public function getArrears(string $from, string $to, ?int $complexId = null): array
    {
        $today = Carbon::today();
        $rows = $this->baseInvoiceQuery($from, $to, $complexId)
            ->whereIn('tenant_invoices.status', [TenantInvoice::UNPAID, TenantInvoice::PARTIAL])
            ->where('tenant_invoices.due_date', '<', $today->toDateString())
            ->select([
                'tenant_invoices.id AS invoice_id', 'tenant_invoices.invoice_number', 'tenant_invoices.kind', 'tenant_invoices.due_date',
                'tenant_invoices.amount', 'tenant_invoices.paid_amount', 'tenant_invoices.status', 'tenant_invoices.currency',
                'tm.id AS tenancy_id', 'tm.tenancy_ref', 'tm.complex_name', 'tm.unit_number',
                DB::raw("COALESCE(NULLIF(tp.business_name, ''), tp.tenant_name) AS tenant_name"),
            ])
            ->orderBy('tenant_invoices.due_date')
            ->get();

        return $rows->map(function ($r) use ($today) {
            $days = (int) Carbon::parse($r->due_date)->startOfDay()->diffInDays($today);
            return [
                'invoice_id' => $r->invoice_id,
                'invoice_number' => $r->invoice_number,
                'kind' => TenantInvoice::KIND_LABELS[$r->kind] ?? $r->kind,
                'tenancy_ref' => $r->tenancy_ref,
                'tenant_name' => $r->tenant_name,
                'complex_name' => $r->complex_name ?: 'N/A',
                'unit_number' => $r->unit_number ?: 'N/A',
                'currency' => $r->currency ?: default_currency(),
                'amount' => (float) $r->amount,
                'paid_amount' => (float) $r->paid_amount,
                'balance' => round((float) $r->amount - (float) $r->paid_amount, 2),
                'due_date' => Carbon::parse($r->due_date)->format('d M Y'),
                'days_overdue' => $days,
                'bucket' => self::bucket($days),
                'status' => $r->status === TenantInvoice::PARTIAL ? 'Overdue (part paid)' : 'Overdue',
            ];
        })->values()->toArray();
    }

    public static function bucket(int $days): string
    {
        return match (true) {
            $days <= 30 => '1–30 days',
            $days <= 60 => '31–60 days',
            $days <= 90 => '61–90 days',
            default => 'Over 90 days',
        };
    }

    /** Overdue balances today by age, across all dates. */
    public function agingTotals(?int $complexId = null): array
    {
        $today = Carbon::today();
        $buckets = ['1–30 days' => 0.0, '31–60 days' => 0.0, '61–90 days' => 0.0, 'Over 90 days' => 0.0];
        TenantInvoice::query()
            ->join('tenant_management as tm', 'tm.id', '=', 'tenant_invoices.tenancy_id')
            ->where('tm.managed_by', business_owner_id())
            ->when($complexId, fn ($q) => $q->where('tm.complex_id', $complexId))
            ->whereIn('tenant_invoices.status', [TenantInvoice::UNPAID, TenantInvoice::PARTIAL])
            ->where('tenant_invoices.due_date', '<', $today->toDateString())
            ->get(['tenant_invoices.due_date', 'tenant_invoices.amount', 'tenant_invoices.paid_amount'])
            ->each(function ($i) use (&$buckets, $today) {
                $buckets[self::bucket((int) Carbon::parse($i->due_date)->startOfDay()->diffInDays($today))] += (float) $i->amount - (float) $i->paid_amount;
            });
        return array_map(fn ($v) => round($v, 2), $buckets);
    }

    /** Money received, refunds and entries awaiting clearance, by date received. */
    public function getPayments(string $from, string $to, ?int $complexId = null): array
    {
        return $this->moneyQuery($from, $to, $complexId)
            ->leftJoin('tenant_profiles as tp', 'tp.id', '=', 'tm.tenant_profile_id')
            ->whereIn('p.entry_type', ['payment', 'refund'])
            ->orderByDesc('p.payment_date')->orderByDesc('p.id')
            ->get(['p.id', 'p.payment_date', 'p.entry_type', 'p.payment_method', 'p.transaction_reference', 'p.amount_paid', 'p.unallocated_amount',
                'p.payment_status', 'p.reversal_reason', 'tm.tenancy_ref', 'tm.complex_name', 'tm.unit_number', 'tm.currency',
                DB::raw("COALESCE(NULLIF(tp.business_name, ''), tp.tenant_name) AS tenant_name")])
            ->map(fn ($r) => [
                'date' => Carbon::parse($r->payment_date)->format('d M Y'),
                'type' => $r->entry_type === 'refund' ? 'Refund' : 'Payment',
                'tenant_name' => $r->tenant_name,
                'tenancy_ref' => $r->tenancy_ref,
                'property' => trim(($r->complex_name ?: '') . ($r->unit_number ? ' · ' . $r->unit_number : '')),
                'method' => str_replace('_', ' ', (string) $r->payment_method),
                'reference' => $r->transaction_reference,
                'currency' => $r->currency ?: default_currency(),
                'amount' => (float) $r->amount_paid,
                'credit_left' => (float) $r->unallocated_amount,
                'status' => ['pending' => 'Awaiting clearance', 'confirmed' => 'Confirmed', 'reversed' => 'Reversed'][$r->payment_status] ?? $r->payment_status,
                'note' => $r->reversal_reason,
            ])->values()->toArray();
    }

    /** Deposit register: per tenancy account, what was billed, is held, and is still to be paid. */
    public function getDeposits(?int $complexId = null): array
    {
        $account = app(TenantAccountService::class);
        $seen = [];
        $rows = [];
        $visible = TenantManagement::query()->where('managed_by', business_owner_id())->pluck('id')->flip();
        TenantManagement::query()->where('managed_by', business_owner_id())
            ->when($complexId, fn ($q) => $q->where('complex_id', $complexId))
            ->with('tenantProfile')->orderByDesc('id')->get()
            ->each(function (TenantManagement $t) use ($account, &$seen, &$rows, $visible) {
                $chain = $account->chainIds($t);
                $key = implode('-', $chain);
                if (isset($seen[$key])) {
                    return;
                }
                $seen[$key] = true;
                $current = $account->currentTenancy($t);
                if (!isset($visible[$current->id])) {
                    return; // moved on to a tenancy this person cannot see
                }
                $required = $account->depositRequired($current);
                if ($required <= 0 && $account->depositHeld($current) <= 0) {
                    return;
                }
                $ids = $chain;
                $paidIn = (float) DB::table('tenant_invoice_payment_allocations')->whereNull('reversed_at')
                    ->whereIn('invoice_id', TenantInvoice::query()->whereIn('tenancy_id', $ids)->where('kind', 'deposit')->select('id'))->sum('allocated_amount');
                $releases = TenantPayment::query()->whereIn('tenant_id', $ids)->where('entry_type', 'deposit_release')
                    ->where('payment_status', TenantPayment::CONFIRMED)->get();
                $applied = (float) $releases->filter(fn ($r) => ($r->allocation_data['purpose'] ?? 'applied') === 'applied')->sum('amount_paid');
                $refunded = (float) $releases->filter(fn ($r) => ($r->allocation_data['purpose'] ?? null) === 'returned')->sum('amount_paid');
                $profile = $current->tenantProfile;
                $rows[] = [
                    'tenancy_id' => $current->id,
                    'tenancy_ref' => $current->tenancy_ref,
                    'tenant_name' => $profile?->tenant_type === 'Business' ? ($profile->business_name ?: $profile->tenant_name) : $profile?->tenant_name,
                    'property' => trim(($current->complex_name ?: '') . ($current->unit_number ? ' · ' . $current->unit_number : '')),
                    'status' => $current->status,
                    'currency' => $current->currency ?: default_currency(),
                    'required' => $required,
                    'paid' => round($paidIn, 2),
                    'applied' => round($applied, 2),
                    'refunded' => round($refunded, 2),
                    'held' => $account->depositHeld($current),
                    'outstanding' => round(max(0, $required - $paidIn), 2),
                ];
            });
        return $rows;
    }

    /** Per-tenancy account balances (for the Accounts tab). */
    public function getAccounts(?int $complexId = null): array
    {
        $account = app(TenantAccountService::class);
        $seen = [];
        $rows = [];
        $visible = TenantManagement::query()->where('managed_by', business_owner_id())->pluck('id')->flip();
        TenantManagement::query()->where('managed_by', business_owner_id())
            ->when($complexId, fn ($q) => $q->where('complex_id', $complexId))
            ->with('tenantProfile')->orderBy('complex_name')->orderBy('unit_number')->get()
            ->each(function (TenantManagement $t) use ($account, &$seen, &$rows, $visible) {
                $current = $account->currentTenancy($t);
                if (isset($seen[$current->id]) || !isset($visible[$current->id])) {
                    return;
                }
                $seen[$current->id] = true;
                $s = $account->summary($current);
                if ($current->status !== 'Active' && abs($s['balance']) < 0.01 && $s['deposit_held'] <= 0) {
                    return; // ended and settled
                }
                $profile = $current->tenantProfile;
                $chainIds = $account->chainIds($current);
                $last = \App\Models\TenantFollowUp::query()->whereIn('tenancy_id', $chainIds)->latest('contacted_on')->latest('id')->first();
                $promise = \App\Models\TenantFollowUp::query()->whereIn('tenancy_id', $chainIds)->where('promise_status', 'open')->orderBy('promise_date')->first();
                $rows[] = [
                    'tenancy_id' => $current->id,
                    'tenancy_ref' => $current->tenancy_ref,
                    'tenant_name' => $profile?->tenant_type === 'Business' ? ($profile->business_name ?: $profile->tenant_name) : $profile?->tenant_name,
                    'property' => trim(($current->complex_name ?: '') . ($current->unit_number ? ' · ' . $current->unit_number : '')),
                    'status' => $current->status,
                    'last_follow_up' => $last ? $last->contacted_on->format('j M Y') . ' · ' . (\App\Models\TenantFollowUp::METHODS[$last->method] ?? $last->method) : null,
                    'promise' => $promise ? ['amount' => (float) $promise->promise_amount, 'date' => $promise->promise_date->format('j M Y'), 'passed' => $promise->promise_date->lt(today())] : null,
                ] + $s;
            });
        usort($rows, fn ($a, $b) => $b['overdue'] <=> $a['overdue'] ?: $b['balance'] <=> $a['balance']);
        return $rows;
    }

    private function moneyQuery(string $from, string $to, ?int $complexId)
    {
        return DB::table('tenant_payments as p')
            ->join('tenant_management as tm', 'tm.id', '=', 'p.tenant_id')
            ->where('tm.managed_by', business_owner_id())
            ->when($complexId, fn ($q) => $q->where('tm.complex_id', $complexId))
            ->tap(fn ($q) => app(PropertyAccess::class)->constrain($q, 'tm.complex_id', ['residential' => 'tm.residential_unit_id', 'commercial' => 'tm.commercial_unit_id']))
            ->whereBetween('p.payment_date', [$from, $to . ' 23:59:59']);
    }

    private function depositTotals(?int $complexId): array
    {
        $rows = collect($this->getDeposits($complexId));
        return ['held' => round($rows->sum('held'), 2), 'outstanding' => round($rows->sum('outstanding'), 2)];
    }

}
