<?php

namespace App\Services\Reports;

use App\Models\Complex;
use App\Models\ComplexExpense;
use App\Models\TenantInvoice;
use App\Models\TenantPayment;
use App\Services\Access\PropertyAccess;
use App\Services\Commission\CommissionReportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Money and unit figures per property, shared by the reports. Every figure is limited to the signed-in
 * business and, for team members limited to assigned properties, to what they can see. Amounts keep their
 * currency: rows are per property and currency.
 *
 * Counting rules (the same as the tenant account and commission pages):
 * - billed: rent, service charges, late fees and other charges due in the period (not deposits, not voided);
 *   collected: what has been applied to those charges;
 * - received: confirmed tenant payments dated in the period (cash basis);
 * - arrears: open balances past their due date, today;
 * - expenses: property expenses dated in the period, except void and returned ones; repair costs are booked
 *   as expenses when a repair is closed, so they are included here once;
 * - agent commission: commission earned, approved or paid in the period.
 */
class PropertyFigures
{
    private const REVENUE_KINDS = ['rent', 'service_charge', 'late_fee', 'adjustment'];

    /** Properties in scope, id => title. */
    public function properties(?int $complexId = null): Collection
    {
        return Complex::forBusiness()->when($complexId, fn ($q) => $q->whereKey($complexId))->orderBy('title')->pluck('title', 'id');
    }

    /** [complex_id, currency, billed, collected] */
    public function billed(string $from, string $to, ?int $complexId = null): Collection
    {
        return TenantInvoice::query()
            ->join('tenant_management as tm', 'tm.id', '=', 'tenant_invoices.tenancy_id')
            ->where('tm.managed_by', business_owner_id())
            ->whereIn('tenant_invoices.kind', self::REVENUE_KINDS)
            ->where('tenant_invoices.status', '!=', TenantInvoice::VOID)
            ->whereBetween('tenant_invoices.due_date', [$from, $to . ' 23:59:59'])
            ->when($complexId, fn ($q) => $q->where('tm.complex_id', $complexId))
            ->groupBy('tm.complex_id', 'tenant_invoices.currency', 'tm.currency')
            ->get([
                'tm.complex_id',
                DB::raw('COALESCE(tenant_invoices.currency, tm.currency) as currency'),
                DB::raw('SUM(tenant_invoices.amount) as billed'),
                DB::raw('SUM(tenant_invoices.paid_amount) as collected'),
            ])
            ->groupBy(fn ($r) => $r->complex_id . '|' . ($r->currency ?: default_currency()))
            ->map(fn ($g) => (object) [
                'complex_id' => (int) $g->first()->complex_id,
                'currency' => $g->first()->currency ?: default_currency(),
                'billed' => round((float) $g->sum('billed'), 2),
                'collected' => round((float) $g->sum('collected'), 2),
            ])->values();
    }

    /** [complex_id, currency, received] confirmed payments dated in the period. */
    public function received(string $from, string $to, ?int $complexId = null): Collection
    {
        return DB::table('tenant_payments as p')
            ->join('tenant_management as tm', 'tm.id', '=', 'p.tenant_id')
            ->where('tm.managed_by', business_owner_id())
            ->where('p.entry_type', 'payment')->where('p.payment_status', TenantPayment::CONFIRMED)
            ->whereBetween('p.payment_date', [$from, $to . ' 23:59:59'])
            ->when($complexId, fn ($q) => $q->where('tm.complex_id', $complexId))
            ->tap(fn ($q) => app(PropertyAccess::class)->constrain($q, 'tm.complex_id', ['residential' => 'tm.residential_unit_id', 'commercial' => 'tm.commercial_unit_id']))
            ->groupBy('tm.complex_id', 'tm.currency')
            ->get(['tm.complex_id', 'tm.currency', DB::raw('SUM(p.amount_paid) as received')])
            ->map(fn ($r) => (object) ['complex_id' => (int) $r->complex_id, 'currency' => $r->currency ?: default_currency(), 'received' => round((float) $r->received, 2)]);
    }

    /** [complex_id, currency, arrears] overdue open balances today. */
    public function arrears(?int $complexId = null): Collection
    {
        return TenantInvoice::query()
            ->join('tenant_management as tm', 'tm.id', '=', 'tenant_invoices.tenancy_id')
            ->where('tm.managed_by', business_owner_id())
            ->whereIn('tenant_invoices.status', [TenantInvoice::UNPAID, TenantInvoice::PARTIAL])
            ->where('tenant_invoices.due_date', '<', now()->toDateString())
            ->when($complexId, fn ($q) => $q->where('tm.complex_id', $complexId))
            ->groupBy('tm.complex_id', 'tm.currency')
            ->get(['tm.complex_id', 'tm.currency', DB::raw('SUM(tenant_invoices.amount - tenant_invoices.paid_amount) as arrears')])
            ->map(fn ($r) => (object) ['complex_id' => (int) $r->complex_id, 'currency' => $r->currency ?: default_currency(), 'arrears' => round((float) $r->arrears, 2)]);
    }

    /** Expenses in the period (not void or returned). */
    public function expenseQuery(string $from, string $to, ?int $complexId = null)
    {
        return ComplexExpense::query()
            ->join('complexes as c', 'c.id', '=', 'complex_expenses.complex_id')
            ->where('c.user_id', business_owner_id())
            ->where('complex_expenses.transaction_type', 'expense')
            ->where(fn ($q) => $q->whereNull('complex_expenses.workflow_status')->orWhereNotIn('complex_expenses.workflow_status', ['void', 'returned']))
            ->whereBetween('complex_expenses.expense_date', [$from, $to])
            ->when($complexId, fn ($q) => $q->where('complex_expenses.complex_id', $complexId));
    }

    /** [complex_id, currency, expenses, approved, paid] */
    public function expenses(string $from, string $to, ?int $complexId = null): Collection
    {
        $paid = DB::table('property_expense_payments')->select('expense_id', DB::raw('SUM(amount) as paid'))->groupBy('expense_id');

        return $this->expenseQuery($from, $to, $complexId)
            ->leftJoinSub($paid, 'pp', 'pp.expense_id', '=', 'complex_expenses.id')
            ->groupBy('complex_expenses.complex_id', 'complex_expenses.currency')
            ->get([
                'complex_expenses.complex_id',
                'complex_expenses.currency',
                DB::raw('SUM(complex_expenses.amount) as expenses'),
                DB::raw("SUM(CASE WHEN complex_expenses.workflow_status = 'approved' THEN complex_expenses.amount ELSE 0 END) as approved"),
                DB::raw('SUM(COALESCE(pp.paid, 0)) as paid'),
            ])
            ->map(fn ($r) => (object) [
                'complex_id' => (int) $r->complex_id, 'currency' => $r->currency ?: default_currency(),
                'expenses' => round((float) $r->expenses, 2), 'approved' => round((float) $r->approved, 2), 'paid' => round((float) $r->paid, 2),
            ]);
    }

    /**
     * [complex_id, company_fee, agent_commission] on rent, from the commission ledger (business currency).
     * Commission on house sales is left out: it belongs with the sale, in the Sales report.
     */
    public function commission(string $from, string $to, ?int $complexId = null): Collection
    {
        return app(CommissionReportService::class)
            ->query(business_owner_id(), ['from' => $from, 'to' => $to, 'complex_id' => $complexId])
            ->whereIn('e.status', CommissionReportService::LIVE)
            ->where('e.entitlement', '!=', 'sale')
            ->groupBy('e.complex_id')
            ->get(['e.complex_id',
                DB::raw("SUM(CASE WHEN e.entitlement = 'management_fee' THEN e.amount ELSE 0 END) as company_fee"),
                DB::raw("SUM(CASE WHEN e.beneficiary_type = 'agent' THEN e.amount ELSE 0 END) as agent_commission")])
            ->map(fn ($r) => (object) [
                'complex_id' => (int) $r->complex_id,
                'company_fee' => round((float) $r->company_fee, 2),
                'agent_commission' => round((float) $r->agent_commission, 2),
            ]);
    }

    /**
     * Units per property: total, occupied, vacant, other (reserved, maintenance, under offer) and on notice.
     * Sold houses are no longer rentable and are left out.
     */
    public function units(?int $complexId = null): Collection
    {
        $ids = $this->properties($complexId)->keys();
        $residential = DB::table('complex_residential_units')->whereIn('complex_id', $ids)
            ->tap(fn ($q) => app(PropertyAccess::class)->constrain($q, 'complex_id', ['residential' => 'id']))
            ->where('status', '!=', 'sold')
            ->groupBy('complex_id')
            ->get(['complex_id', DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied"),
                DB::raw("SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as vacant")])
            ->keyBy('complex_id');
        $commercial = DB::table('commercial_units')->whereIn('complex_id', $ids)
            ->tap(fn ($q) => app(PropertyAccess::class)->constrain($q, 'complex_id', ['commercial' => 'id']))
            ->groupBy('complex_id')
            ->get(['complex_id', DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'Occupied' THEN 1 ELSE 0 END) as occupied"),
                DB::raw("SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as vacant")])
            ->keyBy('complex_id');
        $notice = DB::table('tenant_management')->where('managed_by', business_owner_id())->where('status', 'On Notice')
            ->whereIn('complex_id', $ids)
            ->tap(fn ($q) => app(PropertyAccess::class)->constrain($q, 'complex_id', ['residential' => 'residential_unit_id', 'commercial' => 'commercial_unit_id']))
            ->groupBy('complex_id')->get(['complex_id', DB::raw('COUNT(*) as n')])->pluck('n', 'complex_id');

        return $ids->map(function ($id) use ($residential, $commercial, $notice) {
            $r = $residential[$id] ?? null;
            $c = $commercial[$id] ?? null;
            $total = (int) ($r->total ?? 0) + (int) ($c->total ?? 0);
            $occupied = (int) ($r->occupied ?? 0) + (int) ($c->occupied ?? 0);
            $vacant = (int) ($r->vacant ?? 0) + (int) ($c->vacant ?? 0);

            return (object) [
                'complex_id' => (int) $id,
                'total' => $total,
                'occupied' => $occupied,
                'vacant' => $vacant,
                'other' => max(0, $total - $occupied - $vacant),
                'on_notice' => (int) ($notice[$id] ?? 0),
                'occupancy' => $total ? round($occupied / $total * 100, 1) : null,
            ];
        })->values();
    }

    /** Sum a collection's field per currency: [currency => amount]. */
    public static function byCurrency(Collection $rows, string $field): array
    {
        return $rows->groupBy(fn ($r) => $r->currency ?? default_currency())
            ->map(fn ($g) => round($g->sum($field), 2))
            ->all();
    }
}
