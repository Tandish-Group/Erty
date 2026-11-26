<?php

namespace App\Services\Reports\Reports;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Reports\PropertyFigures;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use App\Support\Sql;
use Illuminate\Support\Facades\DB;

/** Repairs: requests opened and closed, open work by status and priority, time to close, and costs. */
class MaintenanceReport extends Report
{
    public function __construct(private PropertyFigures $figures)
    {
    }

    public function key(): string { return 'maintenance'; }
    public function title(): string { return 'Maintenance'; }
    public function description(): string { return 'Repairs opened and closed, open work by status and priority, how long repairs take, and what they cost.'; }
    public function icon(): string { return 'fa-tools'; }
    public function rights(): array { return ['reports.maintenance']; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        $properties = $this->figures->properties($f->complexId);
        $base = fn () => MaintenanceRequest::query()->where('managed_by', business_owner_id())->whereIn('complex_id', $properties->keys());
        $period = [$f->from, $f->to . ' 23:59:59'];

        $opened = $base()->whereBetween('reported_at', $period)->count();
        $closed = $base()->whereBetween('closed_at', $period);
        $closedCount = (clone $closed)->count();
        $avgDays = (clone $closed)->whereNotNull('reported_at')->avg(DB::raw(Sql::daysBetween('reported_at', 'closed_at')));
        $open = $base()->whereIn('status', MaintenanceRequest::OPEN_STATUSES);
        $showCosts = true; // repair costs are part of this report

        $result = (new ReportResult())
            ->metric('Reported', $opened, 'number', 'In the period')
            ->metric('Closed', $closedCount, 'number', $avgDays === null ? 'In the period' : ((float) $avgDays < 1 ? 'Closed within a day on average' : 'Average ' . round((float) $avgDays, 1) . ' days from report to close'))
            ->metric('Open now', (clone $open)->count(), 'number', (clone $open)->whereIn('priority', ['urgent', 'high'])->count() . ' urgent or high priority');
        if ($showCosts) {
            $result->metric('Repair costs', round((float) (clone $closed)->sum('cost'), 2), 'money', 'On repairs closed in the period');
        }

        $byProperty = $base()->where(fn ($q) => $q->whereBetween('reported_at', $period)->orWhereBetween('closed_at', $period)->orWhereIn('status', MaintenanceRequest::OPEN_STATUSES))
            ->groupBy('complex_id')
            ->selectRaw('complex_id,
                SUM(CASE WHEN reported_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as reported,
                SUM(CASE WHEN closed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as closed,
                SUM(CASE WHEN status IN (\'open\', \'assigned\', \'in_progress\') THEN 1 ELSE 0 END) as open,
                SUM(CASE WHEN closed_at BETWEEN ? AND ? AND cost_borne_by = \'tenant\' THEN cost ELSE 0 END) as tenant_cost,
                SUM(CASE WHEN closed_at BETWEEN ? AND ? AND (cost_borne_by IS NULL OR cost_borne_by <> \'tenant\') THEN cost ELSE 0 END) as landlord_cost',
                [...$period, ...$period, ...$period, ...$period])
            ->get()
            ->map(fn ($r) => [
                'property' => $properties[$r->complex_id] ?? '—',
                'reported' => (int) $r->reported, 'closed' => (int) $r->closed, 'open' => (int) $r->open,
                'landlord_cost' => round((float) $r->landlord_cost, 2), 'tenant_cost' => round((float) $r->tenant_cost, 2),
                'currency' => default_currency(),
            ])->sortBy('property')->values();

        $columns = ['property' => ['Property', 'text'], 'reported' => ['Reported', 'number'], 'closed' => ['Closed', 'number'], 'open' => ['Open now', 'number']];
        if ($showCosts) {
            $columns += ['landlord_cost' => ['Cost to the business', 'money'], 'tenant_cost' => ['Recharged to tenants', 'money']];
        }
        $result->section('By property', $columns, $byProperty, true, 'Reported and closed in the period; open as of today. Costs are for repairs closed in the period.', 'No repairs.');

        $categories = $base()->where(fn ($q) => $q->whereBetween('reported_at', $period)->orWhereBetween('closed_at', $period))
            ->groupBy('category')
            ->get(['category', DB::raw('COUNT(*) as n'), DB::raw('SUM(COALESCE(cost, 0)) as cost'),
                DB::raw('AVG(CASE WHEN closed_at IS NOT NULL AND reported_at IS NOT NULL THEN ' . Sql::daysBetween('reported_at', 'closed_at') . ' END) as days')])
            ->map(fn ($r) => [
                'category' => MaintenanceRequest::CATEGORIES[$r->category] ?? ucfirst((string) $r->category ?: 'Other'),
                'count' => (int) $r->n, 'days' => $r->days !== null ? round((float) $r->days, 1) : null,
                'cost' => round((float) $r->cost, 2), 'currency' => default_currency(),
            ])->sortByDesc('count')->values();
        $catColumns = ['category' => ['Category', 'text'], 'count' => ['Requests', 'number'], 'days' => ['Average days to close', 'number']];
        if ($showCosts) {
            $catColumns['cost'] = ['Cost', 'money'];
        }
        $result->section('By category', $catColumns, $categories, false, null, 'No repairs in the period.');

        $openRows = (clone $open)->with('assignee:id,first_name,last_name')->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->orderBy('reported_at')->get()
            ->map(fn (MaintenanceRequest $m) => [
                'reference' => $m->reference, 'title' => $m->title, 'property' => $properties[$m->complex_id] ?? '—',
                'priority' => MaintenanceRequest::PRIORITIES[$m->priority] ?? $m->priority, 'status' => MaintenanceRequest::STATUSES[$m->status] ?? $m->status,
                'reported' => $m->reported_at, 'days' => $m->reported_at ? (int) $m->reported_at->diffInDays(now()) : null,
                'handler' => $m->assignee ? trim($m->assignee->first_name . ' ' . $m->assignee->last_name) : 'Not assigned',
            ]);
        $result->section('Open requests', [
            'reference' => ['Request', 'text'], 'title' => ['Repair', 'text'], 'property' => ['Property', 'text'], 'priority' => ['Priority', 'text'],
            'status' => ['Status', 'text'], 'reported' => ['Reported', 'date'], 'days' => ['Days open', 'number'], 'handler' => ['Handled by', 'text'],
        ], $openRows, false, null, 'No open repairs.');

        if ($showCosts) {
            $result->note('A repair\'s cost is booked as a property expense when it is closed, so it also appears in Income & expenses. Costs recharged to tenants are billed to them.');
        }

        return $result;
    }
}
