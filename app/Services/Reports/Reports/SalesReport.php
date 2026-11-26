<?php

namespace App\Services\Reports\Reports;

use App\Models\CommissionEntry;
use App\Models\PropertySale;
use App\Models\PropertySaleInstalment;
use App\Models\User;
use App\Services\Access\ModuleService;
use App\Services\Commission\CommissionReportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Carbon\Carbon;

/** House sales: started and completed, money collected, balances, instalments overdue and due soon. */
class SalesReport extends Report
{
    public function key(): string { return 'sales'; }
    public function title(): string { return 'Sales'; }
    public function description(): string { return 'Houses sold: value, money collected in the period, balances, instalments overdue and due in the next 30 days.'; }
    public function icon(): string { return 'fa-handshake'; }
    public function rights(): array { return ['reports.sales']; }
    public function module(): ?string { return ModuleService::SALES; }
    public function usesAgentFilter(): bool { return true; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        $owner = business_owner_id();
        $today = Carbon::today();
        // Sales still running, plus any started, completed or paid into during the period.
        $sales = PropertySale::query()->where('business_id', $owner)
            ->when($f->complexId, fn ($q) => $q->where('complex_id', $f->complexId))
            ->when($f->agentId, fn ($q) => $q->where('agent_id', $f->agentId))
            ->where(fn ($q) => $q->whereIn('status', PropertySale::LIVE)
                ->orWhereBetween('agreement_date', [$f->from, $f->to])
                ->orWhereBetween('completed_at', [$f->from, $f->to . ' 23:59:59'])
                ->orWhereHas('payments', fn ($p) => $p->whereBetween('paid_on', [$f->from, $f->to])))
            ->with(['complex:id,title', 'unit:id,unit_number', 'agent:id,first_name,last_name', 'payments', 'instalments'])
            ->orderByDesc('agreement_date')->get();

        $rows = $sales->map(fn (PropertySale $s) => [
            'reference' => $s->reference,
            'house' => trim(($s->complex?->title ?? '') . ($s->unit ? ' · ' . $s->unit->unit_number : '')),
            'buyer' => $s->buyer_name,
            'agent' => $s->agent ? trim($s->agent->first_name . ' ' . $s->agent->last_name) : '—',
            'agreed' => $s->agreement_date,
            'status' => $s->statusLabel(),
            'price' => (float) $s->price,
            'collected_period' => round((float) $s->payments->where('status', 'confirmed')->filter(fn ($p) => $p->paid_on && $p->paid_on->between($f->from, $f->to . ' 23:59:59'))->sum('amount'), 2),
            'collected' => $s->collected(),
            'balance' => $s->status === 'cancelled' ? 0 : $s->balance(),
            'overdue' => round((float) $s->overdue()->sum(fn ($i) => $i->outstanding()), 2),
            'currency' => $s->currency ?: default_currency(),
        ]);

        $instalments = PropertySaleInstalment::query()
            ->whereIn('sale_id', $sales->whereIn('status', PropertySale::LIVE)->pluck('id'))
            ->whereIn('status', ['due', 'part_paid'])
            ->where('due_date', '<=', $today->copy()->addDays(30)->toDateString())
            ->with('sale:id,reference,buyer_name,currency')->orderBy('due_date')->get()
            ->map(fn ($i) => [
                'due' => $i->due_date, 'when' => $i->due_date->lt($today) ? (int) $i->due_date->diffInDays($today) . ' days overdue' : ((int) $today->diffInDays($i->due_date) === 0 ? 'Due today' : 'Due in ' . (int) $today->diffInDays($i->due_date) . ' days'),
                'sale' => $i->sale?->reference, 'buyer' => $i->sale?->buyer_name, 'instalment' => $i->label,
                'outstanding' => $i->outstanding(), 'currency' => $i->sale?->currency ?: default_currency(),
            ]);

        $commission = round((float) app(CommissionReportService::class)
            ->query($owner, ['from' => $f->from, 'to' => $f->to, 'complex_id' => $f->complexId, 'agent_id' => $f->agentId])
            ->where('e.entitlement', 'sale')->whereIn('e.status', CommissionReportService::LIVE)->sum('e.amount'), 2);

        $started = $rows->filter(fn ($r) => $r['agreed'] && Carbon::parse($r['agreed'])->between($f->from, $f->to . ' 23:59:59'));
        $result = (new ReportResult())
            ->metric('Sales agreed', $started->count(), 'number', 'In the period')
            ->moneyMetric('Collected', $rows->groupBy('currency')->map(fn ($g) => round($g->sum('collected_period'), 2))->all(), 'Buyer payments in the period')
            ->moneyMetric('Balance to collect', $rows->groupBy('currency')->map(fn ($g) => round($g->sum('balance'), 2))->all(), 'On running sales')
            ->moneyMetric('Overdue', $rows->groupBy('currency')->map(fn ($g) => round($g->sum('overdue'), 2))->all(), 'Instalments past their due date');

        $result->section('Sales', [
            'reference' => ['Sale', 'text'], 'house' => ['House', 'text'], 'buyer' => ['Buyer', 'text'], 'agent' => ['Agent', 'text'],
            'agreed' => ['Agreed', 'date'], 'status' => ['Status', 'text'], 'price' => ['Price', 'money'],
            'collected_period' => ['Paid in period', 'money'], 'collected' => ['Paid so far', 'money'], 'balance' => ['Balance', 'money'], 'overdue' => ['Overdue', 'money'],
        ], $rows, true, 'Running sales, and sales agreed, completed or paid into during the period.', 'No sales.');

        $result->section('Instalments overdue or due in the next 30 days', [
            'due' => ['Due', 'date'], 'when' => ['When', 'text'], 'sale' => ['Sale', 'text'], 'buyer' => ['Buyer', 'text'],
            'instalment' => ['Instalment', 'text'], 'outstanding' => ['Outstanding', 'money'],
        ], $instalments, true, null, 'Nothing is overdue or due soon.');

        $result->note('Sales commission earned in the period: ' . money($commission) . '.');

        return $result;
    }
}
