<?php

namespace App\Http\Controllers\Business\Crm;

use App\Enums\LeadWorkflowPhaseEnum;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Services\Crm\LeadPipeline;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CRM home (/customer_relationship): where the pipeline stands today and what needs doing next.
 * Every figure follows LeadPipeline (the same definitions as the Leads & pipeline report), and the Lead and
 * LeadTask models limit it to the properties the person may see.
 */
class CrmDashboardController extends Controller
{
    private const LIST_SIZE = 6;

    public function index()
    {
        $open = fn () => LeadPipeline::open();
        $overdueTasks = fn () => LeadPipeline::tasks()->overdue();
        $monthAgo = now()->subDays(30);

        $converted = LeadPipeline::leads()->where('converted_at', '>=', $monthAgo);
        $received = LeadPipeline::leads()->where('leads.created_at', '>=', $monthAgo)->count();

        return view('pages.business.crm.index', [
            'metrics' => [
                'open' => $open()->count(),
                'hot' => $open()->where('priority', 'hot')->count(),
                'newWeek' => LeadPipeline::leads()->where('leads.created_at', '>=', now()->startOfWeek())->count(),
                'received' => $received,
                'attention' => LeadPipeline::needingAttention($open())->count(),
                'overdue' => $overdueTasks()->count(),
                'converted' => (clone $converted)->count(),
                'tenants' => (clone $converted)->whereNotNull('converted_tenant_id')->count(),
                'buyers' => (clone $converted)->whereNotNull('converted_sale_id')->count(),
            ],
            'phases' => $this->phases($open),
            'attention' => $this->attention($open),
            'overdue' => $this->overdue($overdueTasks),
            'recent' => $open()->with(['profile', 'assignedAgent:id,first_name,last_name,company_name', 'complex:id,title'])
                ->latest('leads.created_at')->limit(self::LIST_SIZE)->get(),
            'sources' => LeadPipeline::leads()->where('leads.created_at', '>=', $monthAgo)->groupBy('source')
                ->orderByDesc(DB::raw('COUNT(*)'))->get(['source', DB::raw('COUNT(*) as n')])
                ->map(fn ($r) => ['label' => ucwords(str_replace('_', ' ', (string) $r->source ?: 'Not recorded')), 'count' => (int) $r->n]),
            'staleDays' => LeadPipeline::STALE_DAYS,
        ]);
    }

    /** Open leads in each working phase, and how many have sat in it too long. */
    private function phases(callable $open): array
    {
        $counts = $open()->groupBy('workflow_phase')
            ->selectRaw('workflow_phase, COUNT(*) as n, SUM(CASE WHEN phase_started_at < ? THEN 1 ELSE 0 END) as stuck', [now()->subDays(LeadPipeline::STALE_DAYS)])
            ->get()->keyBy('workflow_phase');
        $about = [
            'qualification' => 'Record the need, budget and timeline, and match the lead to a property.',
            'engagement' => 'Calls, meetings and viewings. The lead\'s agent works the follow-up tasks.',
            'conversion' => 'Agree the unit, rent or price and the move-in date.',
            'finalization' => 'Paperwork and payment, then the lead becomes a tenant or buyer.',
        ];

        return collect(LeadWorkflowPhaseEnum::cases())
            ->filter(fn ($p) => isset($about[$p->value]))
            ->values()
            ->map(fn ($p, $i) => [
                'step' => $i + 1,
                'label' => $p->label(),
                'about' => $about[$p->value],
                'count' => (int) ($counts[$p->value]->n ?? 0),
                'stuck' => (int) ($counts[$p->value]->stuck ?? 0),
            ])->all();
    }

    /** Open leads that have gone quiet, with the reason. */
    private function attention(callable $open)
    {
        return LeadPipeline::needingAttention($open())
            ->with(['profile', 'assignedAgent:id,first_name,last_name,company_name'])
            ->orderByRaw('COALESCE(leads.last_contacted_at, leads.created_at)')
            ->limit(self::LIST_SIZE)->get()
            ->map(function (Lead $lead) {
                $followUp = $lead->follow_up_date ? Carbon::parse($lead->follow_up_date) : null;
                $since = $lead->last_contacted_at ? Carbon::parse($lead->last_contacted_at) : null;
                $reason = $followUp && $followUp->lt(now()->startOfDay())
                    ? 'Follow-up was due ' . $followUp->format('j M')
                    : ($since ? 'Not contacted for ' . (int) $since->diffInDays(now()) . ' days' : 'Never contacted, added ' . $lead->created_at->diffForHumans());

                return ['lead' => $lead, 'reason' => $reason];
            });
    }

    /** Overdue follow-up tasks, oldest first. */
    private function overdue(callable $overdueTasks)
    {
        return $overdueTasks()->with(['lead.profile', 'assignedAgent:id,first_name,last_name,company_name'])
            ->orderByRaw('COALESCE(lead_tasks.due_date, lead_tasks.sla_breach_at)')
            ->limit(self::LIST_SIZE)->get()
            ->map(function (LeadTask $task) {
                $due = $task->due_date ?? ($task->sla_breach_at ? Carbon::parse($task->sla_breach_at) : null);

                return ['task' => $task, 'days' => $due ? max(1, (int) $due->diffInDays(now())) : null];
            });
    }
}
