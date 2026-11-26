<?php

namespace App\Services\Reports\Reports;

use App\Enums\LeadTaskStatusEnum;
use App\Enums\LeadWorkflowPhaseEnum;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\User;
use App\Services\Access\PropertyAccess;
use App\Services\Crm\LeadPipeline;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportResult;
use App\Support\Sql;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Leads and the pipeline: what came in during the period and what became of it, where leads come from,
 * the pipeline today, follow-up work, and leads that need attention.
 *
 * "Received" means created in the period. Converted, lost and still-open describe what has become of those
 * leads so far (a cohort), so the conversion rate compares like with like.
 */
class LeadsReport extends Report
{
    private const STALE_DAYS = LeadPipeline::STALE_DAYS;

    public function key(): string { return 'leads'; }
    public function title(): string { return 'Leads & pipeline'; }
    public function description(): string { return 'Leads received and what became of them, where they come from, the pipeline today, follow-up tasks and leads that need attention.'; }
    public function icon(): string { return 'fa-filter'; }
    public function rights(): array { return ['reports.agents']; }
    public function usesAgentFilter(): bool { return true; }

    public function build(ReportFilters $f, User $user): ReportResult
    {
        $leads = fn () => LeadPipeline::leads()
            ->when($f->complexId, fn ($q) => $q->where('leads.complex_id', $f->complexId))
            ->when($f->agentId, fn ($q) => $q->where('leads.assigned_agent_id', $f->agentId));
        $period = [$f->from, $f->to . ' 23:59:59'];
        $received = fn () => $leads()->whereBetween('leads.created_at', $period);

        $total = $received()->count();
        $converted = $received()->converted()->count();
        $lost = $received()->where('status', 'closed_lost')->count();
        $daysToConvert = $received()->whereNotNull('converted_at')->avg(DB::raw(Sql::daysBetween('leads.created_at', 'leads.converted_at')));
        $openNow = LeadPipeline::open($leads());

        $result = (new ReportResult())
            ->metric('Leads received', $total, 'number', 'In the period')
            ->metric('Converted', $converted, 'number', $total ? round($converted / $total * 100, 1) . '% of leads received became tenants or buyers' : 'Of the leads received')
            ->metric('Lost', $lost, 'number', $total ? round($lost / $total * 100, 1) . '% of leads received' : 'Of the leads received')
            ->metric('Open now', (clone $openNow)->count(), 'number', $daysToConvert !== null ? 'Converting took ' . $this->days((float) $daysToConvert) . ' on average' : 'In the pipeline today');

        $this->trend($result, $f, $received);

        // The pipeline today, by phase (not limited to the period).
        $phases = collect(LeadWorkflowPhaseEnum::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()]);
        $byPhase = (clone $openNow)->groupBy('workflow_phase')
            ->selectRaw('workflow_phase, COUNT(*) as n, SUM(CASE WHEN phase_started_at < ? THEN 1 ELSE 0 END) as stuck', [now()->subDays(self::STALE_DAYS)])
            ->get()->keyBy('workflow_phase');
        $openTotal = max(1, (int) $byPhase->sum('n'));
        $result->section('Pipeline today', [
            'phase' => ['Phase', 'text'], 'leads' => ['Open leads', 'number'], 'share' => ['Share', 'percent'],
            'stuck' => ['In this phase over ' . self::STALE_DAYS . ' days', 'number'],
        ], $phases->map(fn ($label, $key) => [
            'phase' => $label,
            'leads' => (int) ($byPhase[$key]->n ?? 0),
            'share' => round(((int) ($byPhase[$key]->n ?? 0)) / $openTotal * 100, 1),
            'stuck' => (int) ($byPhase[$key]->stuck ?? 0),
        ])->values(), true, 'Every open lead today, whatever the period, by the phase it is in.', 'No open leads.');

        // Where the period's leads came from, and how they did.
        $result->section('Sources', [
            'source' => ['Source', 'text'], 'received' => ['Received', 'number'], 'converted' => ['Converted', 'number'],
            'lost' => ['Lost', 'number'], 'rate' => ['Conversion', 'percent'],
        ], $this->breakdown($received, 'source', fn ($v) => ucwords(str_replace('_', ' ', (string) $v ?: 'Not recorded'))), true, null, 'No leads in the period.');

        $result->section('Priority', [
            'priority' => ['Priority', 'text'], 'received' => ['Received', 'number'], 'converted' => ['Converted', 'number'],
            'lost' => ['Lost', 'number'], 'rate' => ['Conversion', 'percent'],
        ], $this->breakdown($received, 'priority', fn ($v) => LeadPipeline::priorityLabel($v)), true, null, 'No leads in the period.');

        $timelines = ['immediately' => 'Immediately', 'within_1_month' => 'Within a month', '1_3_months' => '1–3 months', '3_6_months' => '3–6 months', '6_months_plus' => 'Over 6 months', 'flexible' => 'Flexible'];
        $result->section('What they are looking for', [
            'want' => ['Looking to', 'text'], 'timeline' => ['When', 'text'], 'received' => ['Received', 'number'], 'converted' => ['Converted', 'number'], 'rate' => ['Conversion', 'percent'],
        ], $received()->groupBy('purpose', 'timeline')
            ->get(['purpose', 'timeline', DB::raw('COUNT(*) as n'),
                DB::raw('SUM(CASE WHEN converted_tenant_id IS NOT NULL OR converted_sale_id IS NOT NULL THEN 1 ELSE 0 END) as won')])
            ->map(fn ($r) => [
                'want' => ['rent' => 'Rent', 'buy' => 'Buy', 'lease' => 'Lease'][$r->purpose] ?? ucfirst((string) $r->purpose ?: 'Not recorded'),
                'timeline' => $timelines[$r->timeline] ?? ($r->timeline ? ucwords(str_replace('_', ' ', $r->timeline)) : 'Not recorded'),
                'received' => (int) $r->n, 'converted' => (int) $r->won,
                'rate' => $r->n ? round($r->won / $r->n * 100, 1) : null,
            ])->sortByDesc('received')->values(), true, null, 'No leads in the period.');

        $result->section('Agents', [
            'agent' => ['Agent', 'text'], 'received' => ['Leads received', 'number'], 'converted' => ['Converted', 'number'], 'rate' => ['Conversion', 'percent'],
            'open' => ['Open leads now', 'number'], 'done' => ['Tasks completed', 'number'], 'overdue' => ['Tasks overdue now', 'number'],
            'hours' => ['Average hours to complete a task', 'number'],
        ], $this->agents($f, $received, $openNow, $period), true, 'Leads received in the period and what became of them; open leads and overdue tasks as of today.', 'No agents with leads or tasks.');

        $this->tasks($result, $f, $period);
        $this->needingAttention($result, $openNow);

        $result->note('A lead is converted when it becomes a tenant or a house buyer, and lost when it is closed as lost. Conversion compares the leads received in the period with what has become of them since.');
        $result->note('A follow-up task is overdue once its due date or its response deadline has passed.');

        return $result;
    }

    /** Received / converted / lost for each value of a column. */
    private function breakdown(callable $received, string $column, callable $label)
    {
        return $received()->groupBy($column)
            ->get([$column, DB::raw('COUNT(*) as n'),
                DB::raw('SUM(CASE WHEN converted_tenant_id IS NOT NULL OR converted_sale_id IS NOT NULL THEN 1 ELSE 0 END) as won'),
                DB::raw("SUM(CASE WHEN status = 'closed_lost' THEN 1 ELSE 0 END) as gone")])
            ->map(fn ($r) => [
                $column => $label($r->{$column}),
                'received' => (int) $r->n, 'converted' => (int) $r->won, 'lost' => (int) $r->gone,
                'rate' => $r->n ? round($r->won / $r->n * 100, 1) : null,
            ])->sortByDesc('received')->values();
    }

    private function agents(ReportFilters $f, callable $received, $openNow, array $period)
    {
        $byAgent = fn ($q) => $q->whereNotNull('assigned_agent_id')->groupBy('assigned_agent_id');
        $got = $byAgent($received())->get(['assigned_agent_id', DB::raw('COUNT(*) as n'),
            DB::raw('SUM(CASE WHEN converted_tenant_id IS NOT NULL OR converted_sale_id IS NOT NULL THEN 1 ELSE 0 END) as won')])->keyBy('assigned_agent_id');
        $open = $byAgent(clone $openNow)->get(['assigned_agent_id', DB::raw('COUNT(*) as n')])->pluck('n', 'assigned_agent_id');
        $tasks = fn () => $this->taskQuery($f);
        $done = $tasks()->where('lead_tasks.status', LeadTaskStatusEnum::COMPLETED)->whereBetween('lead_tasks.completed_at', $period)
            ->groupBy('lead_tasks.assigned_agent_id')
            ->get(['lead_tasks.assigned_agent_id', DB::raw('COUNT(*) as n'),
                DB::raw('AVG(CASE WHEN lead_tasks.started_at IS NOT NULL THEN ' . Sql::hoursBetween('lead_tasks.started_at', 'lead_tasks.completed_at') . ' END) as hours')])
            ->keyBy('assigned_agent_id');
        $overdue = $tasks()->overdue()->groupBy('lead_tasks.assigned_agent_id')->get(['lead_tasks.assigned_agent_id', DB::raw('COUNT(*) as n')])->pluck('n', 'assigned_agent_id');

        $ids = collect([$got->keys(), $open->keys(), $done->keys(), $overdue->keys()])->flatten()->filter()->unique();
        $restricted = app(PropertyAccess::class)->restricted();

        return Agent::query()->where('created_by', business_owner_id())
            ->when($f->agentId, fn ($q) => $q->whereKey($f->agentId))
            // People limited to their properties see the agents with work there; others also see quiet active agents.
            ->when($restricted, fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->where(fn ($w) => $w->active()->orWhereIn('id', $ids)))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name'])
            ->map(fn (Agent $a) => [
                'agent' => trim($a->first_name . ' ' . $a->last_name) ?: $a->company_name,
                'received' => (int) ($got[$a->id]->n ?? 0),
                'converted' => (int) ($got[$a->id]->won ?? 0),
                'rate' => ($got[$a->id]->n ?? 0) ? round($got[$a->id]->won / $got[$a->id]->n * 100, 1) : null,
                'open' => (int) ($open[$a->id] ?? 0),
                'done' => (int) ($done[$a->id]->n ?? 0),
                'overdue' => (int) ($overdue[$a->id] ?? 0),
                'hours' => isset($done[$a->id]) && $done[$a->id]->hours !== null ? round((float) $done[$a->id]->hours, 1) : null,
            ])->sortByDesc('received')->values();
    }

    /** Follow-up tasks on the business's leads (limited to the properties the person can reach). */
    private function taskQuery(ReportFilters $f)
    {
        return LeadPipeline::tasks()
            ->when($f->complexId, fn ($q) => $q->whereHas('lead', fn ($l) => $l->where('complex_id', $f->complexId)))
            ->when($f->agentId, fn ($q) => $q->where('lead_tasks.assigned_agent_id', $f->agentId));
    }

    private function tasks(ReportResult $result, ReportFilters $f, array $period): void
    {
        $created = $this->taskQuery($f)->whereBetween('lead_tasks.created_at', $period);
        $statuses = ['pending' => 'Not started', 'in_progress' => 'In progress', 'blocked' => 'Blocked', 'completed' => 'Completed', 'skipped' => 'Skipped', 'cancelled' => 'Cancelled'];
        $counts = (clone $created)->groupBy('lead_tasks.status')->get(['lead_tasks.status', DB::raw('COUNT(*) as n')])
            ->mapWithKeys(fn ($r) => [$r->status instanceof \BackedEnum ? $r->status->value : (string) $r->status => (int) $r->n]);
        $result->section('Follow-up tasks', [
            'status' => ['Status', 'text'], 'tasks' => ['Tasks created in the period', 'number'],
        ], collect($statuses)->map(fn ($label, $key) => ['status' => $label, 'tasks' => (int) ($counts[$key] ?? 0)])->filter(fn ($r) => $r['tasks'] > 0)->values(),
            true, 'Tasks created in the period and where they stand now.', 'No tasks were created in the period.');

        $overdue = $this->taskQuery($f)->overdue()->with(['lead.profile', 'assignedAgent:id,first_name,last_name'])
            ->orderByRaw('COALESCE(lead_tasks.due_date, lead_tasks.sla_breach_at)')->limit(50)->get()
            ->map(function (LeadTask $t) {
                // Overdue by its due date, or by its response deadline when it has no due date.
                $due = $t->due_date ?? ($t->sla_breach_at ? \Carbon\Carbon::parse($t->sla_breach_at) : null);

                return [
                    'due' => $due, 'task' => $t->title, 'lead' => $t->lead?->full_name,
                    'agent' => $t->assignedAgent ? trim($t->assignedAgent->first_name . ' ' . $t->assignedAgent->last_name) : '—',
                    'days' => $due ? (int) $due->diffInDays(now()) : null,
                ];
            });
        $result->section('Overdue follow-ups', [
            'due' => ['Was due', 'date'], 'days' => ['Days overdue', 'number'], 'task' => ['Task', 'text'], 'lead' => ['Lead', 'text'], 'agent' => ['Agent', 'text'],
        ], $overdue, false, 'Open tasks past their due date or response deadline, oldest first (up to 50).', 'No follow-ups are overdue.');
    }

    private function needingAttention(ReportResult $result, $openNow): void
    {
        $rows = LeadPipeline::needingAttention(clone $openNow)
            ->with(['profile', 'assignedAgent:id,first_name,last_name'])
            ->orderByRaw('COALESCE(last_contacted_at, leads.created_at)')->limit(50)->get()
            ->map(fn (Lead $l) => [
                'lead' => $l->full_name,
                'phase' => LeadWorkflowPhaseEnum::tryFrom((string) $l->workflow_phase)?->label() ?? ucfirst((string) $l->workflow_phase),
                'priority' => LeadPipeline::priorityLabel($l->priority),
                'agent' => $l->assignedAgent ? trim($l->assignedAgent->first_name . ' ' . $l->assignedAgent->last_name) : 'No agent',
                'contacted' => $l->last_contacted_at,
                'follow_up' => $l->follow_up_date,
            ]);
        $result->section('Leads needing attention', [
            'lead' => ['Lead', 'text'], 'phase' => ['Phase', 'text'], 'priority' => ['Priority', 'text'], 'agent' => ['Agent', 'text'],
            'contacted' => ['Last contacted', 'date'], 'follow_up' => ['Follow-up was due', 'date'],
        ], $rows, false, 'Open leads not contacted for ' . self::STALE_DAYS . ' days, or past their follow-up date (up to 50).', 'Every open lead has been followed up.');
    }

    /** Received and converted per month across the period (up to 24 months). */
    private function trend(ReportResult $result, ReportFilters $f, callable $received): void
    {
        $start = Carbon::parse($f->from)->startOfMonth();
        $end = Carbon::parse($f->to)->startOfMonth();
        if ($start->diffInMonths($end) < 1 || $start->diffInMonths($end) > 24) {
            return;
        }
        $months = collect(CarbonPeriod::create($start, '1 month', $end));
        $series = ['Received' => [], 'Converted' => []];
        foreach ($months as $month) {
            $range = [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()];
            $series['Received'][] = $received()->whereBetween('leads.created_at', $range)->count();
            $series['Converted'][] = LeadPipeline::leads()
                ->when($f->complexId, fn ($q) => $q->where('complex_id', $f->complexId))
                ->when($f->agentId, fn ($q) => $q->where('assigned_agent_id', $f->agentId))
                ->whereBetween('converted_at', $range)->count();
        }
        $result->chart('Leads by month', $months->map(fn ($m) => $m->format('M Y'))->all(),
            collect($series)->map(fn ($values, $name) => ['name' => $name, 'values' => $values])->values()->all());
    }

    private function days(float $days): string
    {
        return $days < 1 ? 'less than a day' : round($days, 1) . ' days';
    }
}
