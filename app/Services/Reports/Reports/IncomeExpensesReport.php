<?php

namespace App\Services\Reports\Reports;

use App\Models\User;
use App\Services\Reports\PropertyFigures;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/** Income and expenses per property: billed, collected, received, expenses, agent commission and net. */
class IncomeExpensesReport extends Report
{
    public function __construct(private PropertyFigures $figures)
    {
    }

    public function key(): string { return 'income'; }
    public function title(): string { return 'Income & expenses'; }
    public function description(): string { return 'Rent and charges billed and collected, money received, expenses and agent commission, with the net per property.'; }
    public function icon(): string { return 'fa-coins'; }
    public function rights(): array { return ['reports.financial']; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        // The money reports right covers every column here: income, expenses and agent commission.
        $showExpenses = true;
        $showCommission = true;
        $properties = $this->figures->properties($f->complexId);
        $billed = $this->figures->billed($f->from, $f->to, $f->complexId);
        $received = $this->figures->received($f->from, $f->to, $f->complexId);
        $expenses = $showExpenses ? $this->figures->expenses($f->from, $f->to, $f->complexId) : collect();
        $commission = $showCommission ? $this->figures->commission($f->from, $f->to, $f->complexId)->keyBy('complex_id') : collect();
        $currency = default_currency();

        // One row per property and currency.
        $keys = collect([$billed, $received, $expenses])->flatten(1)->map(fn ($r) => $r->complex_id . '|' . $r->currency)
            ->merge($commission->keys()->map(fn ($id) => $id . '|' . $currency))->unique();
        $find = fn ($rows, $id, $cur) => $rows->first(fn ($r) => $r->complex_id === (int) $id && $r->currency === $cur);
        $rows = $keys->map(function ($key) use ($find, $billed, $received, $expenses, $commission, $properties, $currency) {
            [$id, $cur] = explode('|', $key, 2);
            $b = $find($billed, $id, $cur);
            $e = $find($expenses, $id, $cur);
            $agent = $cur === $currency ? (float) ($commission[(int) $id]->agent_commission ?? 0) : 0.0;
            $collected = (float) ($b->collected ?? 0);
            $cost = (float) ($e->expenses ?? 0);

            return [
                'property' => $properties[(int) $id] ?? 'Other',
                'currency' => $cur,
                'billed' => (float) ($b->billed ?? 0),
                'collected' => $collected,
                'rate' => ($b->billed ?? 0) > 0 ? round($collected / $b->billed * 100, 1) : null,
                'received' => (float) ($find($received, $id, $cur)->received ?? 0),
                'expenses' => $cost,
                'commission' => $agent,
                'net' => round($collected - $cost - $agent, 2),
            ];
        })->sortBy('property')->values();

        $result = new ReportResult();
        $result->moneyMetric('Billed', PropertyFigures::byCurrency($billed, 'billed'), 'Rent and charges due in the period')
            ->moneyMetric('Collected', PropertyFigures::byCurrency($billed, 'collected'), 'Applied to those charges');
        if ($showExpenses) {
            $result->moneyMetric('Expenses', PropertyFigures::byCurrency($expenses, 'expenses'), 'Including repair costs');
        }
        $result->moneyMetric('Net', $rows->groupBy('currency')->map(fn ($g) => round($g->sum('net'), 2))->all(), 'Collected less expenses and agent commission');

        $columns = [
            'property' => ['Property', 'text'],
            'billed' => ['Billed', 'money'],
            'collected' => ['Collected', 'money'],
            'rate' => ['Collection rate', 'percent'],
            'received' => ['Received', 'money'],
        ];
        if ($showExpenses) $columns['expenses'] = ['Expenses', 'money'];
        if ($showCommission) $columns['commission'] = ['Agent commission', 'money'];
        $columns['net'] = ['Net', 'money'];
        $result->section('By property', $columns, $rows, true, 'Collected less expenses and agent commission. Received is money that came in during the period, whatever it paid for.');

        if ($showExpenses) {
            $byType = $this->figures->expenseQuery($f->from, $f->to, $f->complexId)
                ->leftJoin('expense_types as et', 'et.id', '=', 'complex_expenses.type')
                ->groupBy('et.expense_type', 'complex_expenses.currency')
                ->get([DB::raw("COALESCE(et.expense_type, 'Other') as category"), 'complex_expenses.currency',
                    DB::raw('COUNT(*) as count'), DB::raw('SUM(complex_expenses.amount) as amount'),
                    DB::raw("SUM(CASE WHEN complex_expenses.workflow_status = 'approved' THEN complex_expenses.amount ELSE 0 END) as approved")])
                ->map(fn ($r) => ['category' => $r->category, 'currency' => $r->currency ?: $currency, 'count' => (int) $r->count, 'amount' => round((float) $r->amount, 2), 'approved' => round((float) $r->approved, 2)])
                ->sortByDesc('amount')->values();
            $result->section('Expenses by category', [
                'category' => ['Category', 'text'], 'count' => ['Entries', 'number'], 'amount' => ['Recorded', 'money'], 'approved' => ['Approved', 'money'],
            ], $byType, true, 'Every expense dated in the period except void and returned ones; approved is the part already approved.');
        }

        $payments = collect(app(\App\Services\Rent\TenantReportService::class)->getPayments($f->from, $f->to, $f->complexId));
        $result->section('Money received', [
            'date' => ['Date', 'text'], 'type' => ['Type', 'text'], 'tenant_name' => ['Tenant', 'text'], 'tenancy_ref' => ['Tenancy', 'text'],
            'property' => ['Property / unit', 'text'], 'method' => ['Method', 'text'], 'reference' => ['Reference', 'text'],
            'amount' => ['Amount', 'money'], 'status' => ['Status', 'text'],
        ], $payments, false, 'Every tenant payment and refund dated in the period, newest first, including ones still awaiting clearance and reversed ones.', 'No money received in the period.');

        $this->trend($result, $f, $showExpenses);
        $result->note('Billed and collected follow each charge\'s due date; received follows the date money came in.');
        $result->note('Deposits are held for tenants and are not income, so they are left out.');
        $result->note('Agent commission here is commission on rent. House sales and their commission are in the Sales report.');
        if ($showExpenses) {
            $result->note('Repair costs are booked as property expenses when a repair is closed, so they are counted once, as expenses. A repair recharged to the tenant also appears as a charge billed to them.');
        }

        return $result;
    }

    /** Monthly billed, collected and expenses across the period (up to 24 months). */
    private function trend(ReportResult $result, ReportFilters $f, bool $withExpenses): void
    {
        $start = Carbon::parse($f->from)->startOfMonth();
        $end = Carbon::parse($f->to)->startOfMonth();
        if ($start->diffInMonths($end) < 1 || $start->diffInMonths($end) > 24) {
            return;
        }
        $months = collect(CarbonPeriod::create($start, '1 month', $end))->map(fn ($m) => $m->format('Y-m'));
        $series = ['Billed' => [], 'Collected' => []] + ($withExpenses ? ['Expenses' => []] : []);
        foreach ($months as $month) {
            $from = $month . '-01';
            $to = Carbon::parse($from)->endOfMonth()->toDateString();
            $b = $this->figures->billed($from, $to, $f->complexId);
            $series['Billed'][] = round($b->sum('billed'), 2);
            $series['Collected'][] = round($b->sum('collected'), 2);
            if ($withExpenses) {
                $series['Expenses'][] = round($this->figures->expenseQuery($from, $to, $f->complexId)->sum('complex_expenses.amount'), 2);
            }
        }
        $result->chart('By month', $months->map(fn ($m) => Carbon::parse($m . '-01')->format('M Y'))->all(),
            collect($series)->map(fn ($values, $name) => ['name' => $name, 'values' => $values])->values()->all());
    }
}
