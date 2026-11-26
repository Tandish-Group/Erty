<?php

namespace App\Http\Controllers\Business\Crm;

use Carbon\Carbon;
use App\Models\Lead;
use App\Models\Agent;
use App\Models\LeadTask;
use Illuminate\Http\Request;
use App\Enums\LeadTaskStatusEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Support\Sql;

class CrmReportsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display the Reports & Analytics dashboard.
     */
    public function index()
    {
        $userId = business_owner_id();

        $summaryStats   = $this->getSummaryStats($userId);
        $conversionData = $this->getConversionFunnel($userId);
        $sourceData     = $this->getLeadsBySource($userId);
        $trendData      = $this->getMonthlyTrend($userId);
        $agentData      = $this->getAgentPerformance($userId);
        $taskData       = $this->getTaskMetrics($userId);
        $priorityData   = $this->getLeadsByPriority($userId);
        $timelineData   = $this->getLeadsByTimeline($userId);

        return view('pages.business.crm.reports.index', compact(
            'summaryStats',
            'conversionData',
            'sourceData',
            'trendData',
            'agentData',
            'taskData',
            'priorityData',
            'timelineData'
        ));
    }

    /**
     * AJAX endpoint for filtered report data.
     */
    public function getData(Request $request)
    {
        $userId = business_owner_id();
        $period = $request->get('period', '30'); // days

        $startDate = Carbon::now()->subDays((int) $period)->startOfDay();

        return response()->json([
            'status'     => 200,
            'summary'    => $this->getSummaryStats($userId, $startDate),
            'conversion' => $this->getConversionFunnel($userId, $startDate),
            'sources'    => $this->getLeadsBySource($userId, $startDate),
            'trend'      => $this->getMonthlyTrend($userId),
            'agents'     => $this->getAgentPerformance($userId, $startDate),
            'tasks'      => $this->getTaskMetrics($userId, $startDate),
            'priority'   => $this->getLeadsByPriority($userId, $startDate),
            'timeline'   => $this->getLeadsByTimeline($userId, $startDate),
        ]);
    }

    // ─── Summary KPIs ────────────────────────────────────────────────

    private function getSummaryStats(int $userId, ?Carbon $since = null): array
    {
        $query = Lead::forBusiness($userId);
        if ($since) $query->where('created_at', '>=', $since);

        $totalLeads     = (clone $query)->count();
        $convertedLeads = (clone $query)->converted()->count();
        $conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;
        $lostLeads      = (clone $query)->where('status', 'closed_lost')->count();

        $avgDealSize = (clone $query)->converted()
            ->selectRaw('AVG(COALESCE(budget_max, budget_min, 0)) as avg_deal')
            ->value('avg_deal') ?? 0;

        $avgConversionTime = (clone $query)->whereNotNull('converted_at')
            ->selectRaw('AVG(' . Sql::daysBetween('created_at', 'converted_at') . ') as avg_days')
            ->value('avg_days') ?? 0;

        // Lead score distribution
        $avgLeadScore = (clone $query)->whereNotNull('lead_score')
            ->avg('lead_score') ?? 0;

        return [
            'total_leads'         => $totalLeads,
            'converted_leads'     => $convertedLeads,
            'conversion_rate'     => $conversionRate,
            'lost_leads'          => $lostLeads,
            'avg_deal_size'       => round($avgDealSize),
            'avg_conversion_days' => round($avgConversionTime, 1),
            'avg_lead_score'      => round($avgLeadScore),
        ];
    }

    // ─── Conversion Funnel ──────────────────────────────────────────

    private function getConversionFunnel(int $userId, ?Carbon $since = null): array
    {
        $query = Lead::forBusiness($userId);
        if ($since) $query->where('created_at', '>=', $since);

        $phases = [
            'qualification' => ['label' => 'Qualification', 'color' => 'primary'],
            'engagement'    => ['label' => 'Engagement',    'color' => 'info'],
            'conversion'    => ['label' => 'Conversion',    'color' => 'warning'],
            'finalization'  => ['label' => 'Finalization',  'color' => 'success'],
            'completed'     => ['label' => 'Completed',     'color' => 'dark'],
        ];

        $funnel = [];
        foreach ($phases as $phase => $meta) {
            $count = (clone $query)->where('workflow_phase', $phase)->count();
            $funnel[] = [
                'phase' => $phase,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'count' => $count,
            ];
        }

        // Also include leads that have converted (regardless of current phase)
        $convertedCount = (clone $query)->converted()->count();
        $totalCount = (clone $query)->count();

        return [
            'funnel'    => $funnel,
            'converted' => $convertedCount,
            'total'     => $totalCount,
        ];
    }

    // ─── Leads by Source ────────────────────────────────────────────

    private function getLeadsBySource(int $userId, ?Carbon $since = null): array
    {
        $query = Lead::forBusiness($userId);
        if ($since) $query->where('created_at', '>=', $since);

        $sources = (clone $query)
            ->select('source', DB::raw('COUNT(*) as total'))
            ->whereNotNull('source')
            ->groupBy('source')
            ->orderByDesc('total')
            ->get();

        $result = [];
        $colors = [
            'website'          => '#3E97FF',
            'referral'         => '#50CD89',
            'agent_referral'   => '#7239EA',
            'social_media'     => '#F1416C',
            'google_ads'       => '#FFC700',
            'facebook_ads'     => '#009EF7',
            'walk_in'          => '#47BE7D',
            'phone_call'       => '#F6C000',
            'email'            => '#5014D0',
            'event'            => '#D9214E',
            'website_schedule' => '#2884EF',
        ];

        foreach ($sources as $source) {
            $converted = (clone $query)
                ->where('source', $source->source)
                ->converted()
                ->count();

            $result[] = [
                'source'          => $source->source,
                'label'           => ucwords(str_replace('_', ' ', $source->source)),
                'total'           => $source->total,
                'converted'       => $converted,
                'conversion_rate' => $source->total > 0 ? round(($converted / $source->total) * 100, 1) : 0,
                'color'           => $colors[$source->source] ?? '#A1A5B7',
            ];
        }

        return $result;
    }

    // ─── Monthly Trend (last 12 months) ─────────────────────────────

    private function getMonthlyTrend(int $userId): array
    {
        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->startOfMonth());
        }

        $trend = [];
        foreach ($months as $month) {
            $start = $month->copy()->startOfMonth();
            $end   = $month->copy()->endOfMonth();

            $created = Lead::forBusiness($userId)
                ->whereBetween('created_at', [$start, $end])
                ->count();

            $converted = Lead::forBusiness($userId)
                ->whereBetween('converted_at', [$start, $end])
                ->count();

            $lost = Lead::forBusiness($userId)
                ->where('status', 'closed_lost')
                ->whereBetween('updated_at', [$start, $end])
                ->count();

            $trend[] = [
                'month'     => $month->format('M Y'),
                'month_short' => $month->format('M'),
                'created'   => $created,
                'converted' => $converted,
                'lost'      => $lost,
            ];
        }

        return $trend;
    }

    // ─── Agent Performance ──────────────────────────────────────────

    private function getAgentPerformance(int $userId, ?Carbon $since = null): array
    {
        $agents = Agent::where('created_by', $userId)
            ->where('status', 'active')
            ->where('is_active', true)
            ->get();

        $result = [];
        foreach ($agents as $agent) {
            $query = Lead::forBusiness($userId)
                ->where('assigned_agent_id', $agent->id);
            if ($since) $query->where('created_at', '>=', $since);

            $totalLeads     = (clone $query)->count();
            $convertedLeads = (clone $query)->converted()->count();
            $activeleads    = (clone $query)->whereIn('status', ['new', 'contacted', 'qualified', 'proposal', 'in_progress'])->count();

            $taskQuery = LeadTask::where('assigned_agent_id', $agent->id);
            if ($since) $taskQuery->where('created_at', '>=', $since);

            $tasksCompleted  = (clone $taskQuery)->where('status', LeadTaskStatusEnum::COMPLETED)->count();
            $tasksOverdue    = (clone $taskQuery)->overdue()->count();
            $totalTasks      = (clone $taskQuery)->count();

            $avgCompletionTime = (clone $taskQuery)
                ->where('status', LeadTaskStatusEnum::COMPLETED)
                ->whereNotNull('completed_at')
                ->whereNotNull('started_at')
                ->selectRaw('AVG(' . Sql::hoursBetween('started_at', 'completed_at') . ') as avg_hours')
                ->value('avg_hours') ?? 0;

            $result[] = [
                'id'                  => $agent->id,
                'name'                => $agent->first_name . ' ' . $agent->last_name,
                'type'                => $agent->agent_type,
                'total_leads'         => $totalLeads,
                'active_leads'        => $activeleads,
                'converted_leads'     => $convertedLeads,
                'conversion_rate'     => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0,
                'tasks_completed'     => $tasksCompleted,
                'tasks_overdue'       => $tasksOverdue,
                'total_tasks'         => $totalTasks,
                'task_completion_rate' => $totalTasks > 0 ? round(($tasksCompleted / $totalTasks) * 100, 1) : 0,
                'avg_completion_hours' => round($avgCompletionTime, 1),
            ];
        }

        // Sort by conversion rate descending
        usort($result, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

        return $result;
    }

    // ─── Task Metrics ───────────────────────────────────────────────

    private function getTaskMetrics(int $userId, ?Carbon $since = null): array
    {
        // Get all task IDs for this user's leads
        $leadIds = Lead::forBusiness($userId)->pluck('id');

        $query = LeadTask::whereIn('lead_id', $leadIds);
        if ($since) $query->where('created_at', '>=', $since);

        $total     = (clone $query)->count();
        $completed = (clone $query)->where('status', LeadTaskStatusEnum::COMPLETED)->count();
        $pending   = (clone $query)->where('status', LeadTaskStatusEnum::PENDING)->count();
        $inProg    = (clone $query)->where('status', LeadTaskStatusEnum::IN_PROGRESS)->count();
        $overdue   = (clone $query)->overdue()->count();
        $blocked   = (clone $query)->where('status', LeadTaskStatusEnum::BLOCKED)->count();

        $completionRate = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
        $overdueRate    = $total > 0 ? round(($overdue / $total) * 100, 1) : 0;

        $avgCompletionHours = (clone $query)
            ->where('status', LeadTaskStatusEnum::COMPLETED)
            ->whereNotNull('completed_at')
            ->whereNotNull('started_at')
            ->selectRaw('AVG(' . Sql::hoursBetween('started_at', 'completed_at') . ') as avg_hours')
            ->value('avg_hours') ?? 0;

        // SLA breach rate
        $slaTasks   = (clone $query)->whereNotNull('sla_breach_at')->count();
        $slaBreached = (clone $query)->where('is_overdue', true)->whereNotNull('sla_breach_at')->count();
        $slaBreachRate = $slaTasks > 0 ? round(($slaBreached / $slaTasks) * 100, 1) : 0;

        // Tasks by type
        $byType = (clone $query)
            ->select('task_type', DB::raw('COUNT(*) as count'))
            ->groupBy('task_type')
            ->pluck('count', 'task_type')
            ->toArray();

        return [
            'total'                => $total,
            'completed'            => $completed,
            'pending'              => $pending,
            'in_progress'          => $inProg,
            'overdue'              => $overdue,
            'blocked'              => $blocked,
            'completion_rate'      => $completionRate,
            'overdue_rate'         => $overdueRate,
            'sla_breach_rate'      => $slaBreachRate,
            'avg_completion_hours' => round($avgCompletionHours, 1),
            'by_type'              => $byType,
        ];
    }

    // ─── Leads by Priority ──────────────────────────────────────────

    private function getLeadsByPriority(int $userId, ?Carbon $since = null): array
    {
        $query = Lead::forBusiness($userId);
        if ($since) $query->where('created_at', '>=', $since);

        $priorities = [
            'high'   => ['label' => 'Hot',  'color' => 'danger'],
            'medium' => ['label' => 'Warm', 'color' => 'warning'],
            'low'    => ['label' => 'Cold', 'color' => 'info'],
        ];

        $result = [];
        foreach ($priorities as $key => $meta) {
            $total = (clone $query)->where('priority', $key)->count();
            $converted = (clone $query)->where('priority', $key)->converted()->count();

            $result[] = [
                'priority'        => $key,
                'label'           => $meta['label'],
                'color'           => $meta['color'],
                'total'           => $total,
                'converted'       => $converted,
                'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0,
            ];
        }

        return $result;
    }

    // ─── Leads by Timeline ──────────────────────────────────────────

    private function getLeadsByTimeline(int $userId, ?Carbon $since = null): array
    {
        $query = Lead::forBusiness($userId);
        if ($since) $query->where('created_at', '>=', $since);

        $timelines = [
            'immediately'    => 'Immediately',
            'within_1_month' => 'Within 1 Month',
            '1_3_months'     => '1-3 Months',
            '3_6_months'     => '3-6 Months',
            '6_months_plus'  => '6+ Months',
            'flexible'       => 'Flexible',
        ];

        $result = [];
        foreach ($timelines as $key => $label) {
            $total = (clone $query)->where('timeline', $key)->count();
            if ($total > 0) {
                $result[] = [
                    'timeline' => $key,
                    'label'    => $label,
                    'total'    => $total,
                ];
            }
        }

        return $result;
    }
}
