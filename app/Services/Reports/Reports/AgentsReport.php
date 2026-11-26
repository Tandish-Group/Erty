<?php

namespace App\Services\Reports\Reports;

use App\Models\Agent;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\PropertySale;
use App\Models\TenantManagement;
use App\Models\User;
use App\Services\Access\ModuleService;
use App\Services\Access\PropertyAccess;
use App\Services\Commission\CommissionReportService;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

/** Per agent: leads, conversions, tasks, tenancies placed, rent collected, commission and sales. */
class AgentsReport extends Report
{
    public function __construct(private CommissionReportService $commission)
    {
    }

    public function key(): string { return 'agents'; }
    public function title(): string { return 'Agents & leads'; }
    public function description(): string { return 'Each agent\'s leads and conversions, open and overdue tasks, tenancies placed, rent collected and commission.'; }
    public function icon(): string { return 'fa-user-tie'; }
    public function rights(): array { return ['reports.agents']; }
    public function usesAgentFilter(): bool { return true; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        $owner = business_owner_id();
        $withLeads = true; // leads and tasks are part of this report
        $withSales = app(ModuleService::class)->enabled(ModuleService::SALES, $user) && $this->can($user, 'reports.sales');
        $byAgent = fn ($query, string $column = 'assigned_agent_id') => $query->whereNotNull($column)
            ->when($f->agentId, fn ($q) => $q->where($column, $f->agentId))
            ->when($f->complexId, fn ($q) => $q->where('complex_id', $f->complexId));

        $leads = $withLeads ? $byAgent(Lead::query()->where('user_id', $owner)->whereBetween('created_at', [$f->from, $f->to . ' 23:59:59']))
            ->groupBy('assigned_agent_id')->get(['assigned_agent_id', DB::raw('COUNT(*) as n')])->pluck('n', 'assigned_agent_id') : collect();
        $converted = $withLeads ? $byAgent(Lead::query()->where('user_id', $owner)->whereBetween('converted_at', [$f->from, $f->to . ' 23:59:59']))
            ->groupBy('assigned_agent_id')->get(['assigned_agent_id', DB::raw('COUNT(*) as n')])->pluck('n', 'assigned_agent_id') : collect();
        $openTasks = $withLeads ? $this->tasks($f)->whereNotIn('lead_tasks.status', ['completed', 'cancelled'])
            ->groupBy('lead_tasks.assigned_agent_id')->get(['lead_tasks.assigned_agent_id', DB::raw('COUNT(*) as n')])->pluck('n', 'assigned_agent_id') : collect();
        $overdueTasks = $withLeads ? $this->tasks($f)->overdue()
            ->groupBy('lead_tasks.assigned_agent_id')->get(['lead_tasks.assigned_agent_id', DB::raw('COUNT(*) as n')])->pluck('n', 'assigned_agent_id') : collect();
        $placed = $byAgent(TenantManagement::query()->where('managed_by', $owner)->whereBetween('move_in_date', [$f->from, $f->to])->whereNull('previous_tenancy_id'))
            ->groupBy('assigned_agent_id')->get(['assigned_agent_id', DB::raw('COUNT(*) as n')])->pluck('n', 'assigned_agent_id');
        $sales = $withSales ? $byAgent(PropertySale::query()->where('business_id', $owner)->whereBetween('agreement_date', [$f->from, $f->to])->where('status', '!=', 'cancelled'), 'agent_id')
            ->groupBy('agent_id')->get(['agent_id', DB::raw('COUNT(*) as n'), DB::raw('SUM(price) as value')])->keyBy('agent_id') : collect();
        $money = $this->commission->grouped($owner, ['from' => $f->from, 'to' => $f->to, 'complex_id' => $f->complexId, 'agent_id' => $f->agentId], 'agent')->keyBy('agent_id');

        $ids = collect([$leads->keys(), $converted->keys(), $openTasks->keys(), $placed->keys(), $sales->keys(), $money->keys()])->flatten()->unique();
        // Everyone sees the agents with activity; people who see every property also see quiet agents.
        $agents = Agent::query()->where('created_by', $owner)
            ->when($f->agentId, fn ($q) => $q->whereKey($f->agentId))
            ->when(app(PropertyAccess::class)->restricted(), fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->where(fn ($w) => $w->active()->orWhereIn('id', $ids)))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name', 'agent_type']);

        $rows = $agents->map(function (Agent $a) use ($leads, $converted, $openTasks, $overdueTasks, $placed, $sales, $money) {
            $m = $money[$a->id] ?? null;
            $received = (int) ($leads[$a->id] ?? 0);
            $won = (int) ($converted[$a->id] ?? 0);

            return [
                'agent' => trim($a->first_name . ' ' . $a->last_name) ?: $a->company_name,
                'leads' => $received,
                'converted' => $won,
                'rate' => $received ? round($won / $received * 100, 1) : null,
                'open_tasks' => (int) ($openTasks[$a->id] ?? 0),
                'overdue_tasks' => (int) ($overdueTasks[$a->id] ?? 0),
                'placed' => (int) ($placed[$a->id] ?? 0),
                'sales' => (int) ($sales[$a->id]->n ?? 0),
                'sales_value' => round((float) ($sales[$a->id]->value ?? 0), 2),
                'rent' => round((float) ($m->agent_rent ?? 0), 2),
                'earned' => round((float) ($m->agent_commission ?? 0), 2),
                'owed' => round((float) ($m->owed ?? 0), 2),
                'currency' => default_currency(),
            ];
        })->values();

        $result = (new ReportResult());
        if ($withLeads) {
            $result->metric('Leads received', $rows->sum('leads'), 'number', 'In the period')
                ->metric('Converted', $rows->sum('converted'), 'number', $rows->sum('leads') ? round($rows->sum('converted') / max(1, $rows->sum('leads')) * 100, 1) . '% of leads received' : 'Became tenants or buyers');
        }
        $result->metric('Tenancies placed', $rows->sum('placed'), 'number', 'New tenants moved in')
            ->metric('Commission earned', $rows->sum('earned'), 'money', money($rows->sum('owed')) . ' not paid out yet');

        $columns = ['agent' => ['Agent', 'text']];
        if ($withLeads) {
            $columns += ['leads' => ['Leads', 'number'], 'converted' => ['Converted', 'number'], 'rate' => ['Conversion', 'percent'],
                'open_tasks' => ['Open tasks', 'number'], 'overdue_tasks' => ['Overdue tasks', 'number']];
        }
        $columns['placed'] = ['Tenancies placed', 'number'];
        if ($withSales) {
            $columns += ['sales' => ['Sales', 'number'], 'sales_value' => ['Sales value', 'money']];
        }
        $columns += ['rent' => ['Rent collected', 'money'], 'earned' => ['Commission earned', 'money'], 'owed' => ['Not paid out', 'money']];
        $result->section('By agent', $columns, $rows, true, 'Leads and conversions in the period; open and overdue tasks today; rent collected on the agent\'s own units and the commission earned on it.', 'No agents yet.');

        $result->note('A task is overdue when its due date or its response deadline has passed. Commission counts entries earned, approved or paid in the period.');

        return $result;
    }

    private function tasks(ReportFilters $f)
    {
        return LeadTask::query()
            ->whereHas('lead', fn ($l) => $l->where('user_id', business_owner_id())->when($f->complexId, fn ($q) => $q->where('complex_id', $f->complexId)))
            ->whereNotNull('lead_tasks.assigned_agent_id')
            ->when($f->agentId, fn ($q) => $q->where('lead_tasks.assigned_agent_id', $f->agentId));
    }
}
