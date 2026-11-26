<?php

namespace App\Services\Reports\Reports;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Reports\PropertyFigures;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

/**
 * The whole business on one page: the headline figures of each area this person can see, and every
 * property side by side. Each column appears only with the right behind it.
 */
class OverviewReport extends Report
{
    public function __construct(private PropertyFigures $figures)
    {
    }

    public function key(): string { return 'overview'; }
    public function title(): string { return 'Business overview'; }
    public function description(): string { return 'Every property side by side: units and occupancy, rent billed and collected, expenses, net, arrears and open repairs.'; }
    public function icon(): string { return 'fa-th-large'; }
    public function rights(): array { return ['reports.view']; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        // Money columns come with the money reports right.
        $money = $this->can($user, 'reports.financial');
        $expensesAllowed = $money;
        $properties = $this->figures->properties($f->complexId);
        $units = $this->figures->units($f->complexId)->keyBy('complex_id');
        $repairs = MaintenanceRequest::query()->where('managed_by', business_owner_id())->whereIn('complex_id', $properties->keys())
            ->whereIn('status', MaintenanceRequest::OPEN_STATUSES)->groupBy('complex_id')
            ->get(['complex_id', DB::raw('COUNT(*) as n')])->pluck('n', 'complex_id');

        $billed = $money ? $this->figures->billed($f->from, $f->to, $f->complexId) : collect();
        $arrears = $money ? $this->figures->arrears($f->complexId) : collect();
        $expenses = $expensesAllowed ? $this->figures->expenses($f->from, $f->to, $f->complexId) : collect();
        $sum = fn ($rows, $id, $field) => round((float) $rows->where('complex_id', (int) $id)->sum($field), 2);

        $rows = $properties->map(function ($title, $id) use ($units, $repairs, $billed, $arrears, $expenses, $sum) {
            $u = $units[$id] ?? null;
            $collected = $sum($billed, $id, 'collected');
            $cost = $sum($expenses, $id, 'expenses');

            return [
                'property' => $title,
                'units' => $u->total ?? 0,
                'occupancy' => $u->occupancy ?? null,
                'vacant' => $u->vacant ?? 0,
                'billed' => $sum($billed, $id, 'billed'),
                'collected' => $collected,
                'expenses' => $cost,
                'net' => round($collected - $cost, 2),
                'arrears' => $sum($arrears, $id, 'arrears'),
                'repairs' => (int) ($repairs[$id] ?? 0),
                'currency' => default_currency(),
            ];
        })->values();

        $totalUnits = $units->sum('total');
        $result = (new ReportResult())
            ->metric('Properties', $properties->count(), 'number', $totalUnits . ' units')
            ->metric('Occupancy', $totalUnits ? round($units->sum('occupied') / $totalUnits * 100, 1) : 0, 'percent', $units->sum('vacant') . ' vacant today');
        if ($money) {
            $result->moneyMetric('Collected', PropertyFigures::byCurrency($billed, 'collected'), 'Of ' . money($billed->sum('billed')) . ' billed in the period')
                ->moneyMetric('Arrears', PropertyFigures::byCurrency($arrears, 'arrears'), 'Overdue today');
        } else {
            $result->metric('Open repairs', $repairs->sum(), 'number', 'Across your properties');
        }

        $columns = ['property' => ['Property', 'text'], 'units' => ['Units', 'number'], 'occupancy' => ['Occupancy', 'percent'], 'vacant' => ['Vacant', 'number']];
        if ($money) {
            $columns += ['billed' => ['Billed', 'money'], 'collected' => ['Collected', 'money']];
            if ($expensesAllowed) {
                $columns += ['expenses' => ['Expenses', 'money'], 'net' => ['Collected less expenses', 'money']];
            }
            $columns['arrears'] = ['Arrears today', 'money'];
        }
        $columns['repairs'] = ['Open repairs', 'number'];
        $result->section('Properties side by side', $columns, $rows, true, 'Money for the period; units, arrears and repairs as of today.', 'No properties yet.');

        if ($money && $billed->pluck('currency')->merge($expenses->pluck('currency'))->unique()->count() > 1) {
            $result->note('Some amounts are in other currencies; this table adds them together. Income & expenses keeps each currency separate.');
        }

        return $result;
    }
}
