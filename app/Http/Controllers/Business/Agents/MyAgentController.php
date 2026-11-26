<?php

namespace App\Http\Controllers\Business\Agents;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\TenantManagement;
use App\Services\Commission\AgentCommissionService;
use App\Services\Agents\AgentProfileService;
use App\Services\Agents\AgentStatisticsService;
use App\Services\Access\PropertyAccess;
use Illuminate\Http\Request;

/**
 * "My agent dashboard": the signed-in person's own agent profile, work and commission.
 * Open to every sign-in with an agent profile, even when their role cannot open the Agents section.
 */
class MyAgentController extends Controller
{
    public function show(Request $request, AgentStatisticsService $stats, AgentCommissionService $commissions, PropertyAccess $access)
    {
        $user = auth()->user();
        $agent = AgentProfileService::current($user);
        if (!$agent) {
            $canCreateAgent = $user->hasBusinessPermission('agent_management', 'create');
            return view('pages.business.agents.my-dashboard', compact('agent', 'canCreateAgent'));
        }

        $agent->load(['department', 'role', 'workLocation', 'commissionTiers', 'reportsTo', 'subAgents']);
        $period = AgentCommissionService::period($request->query('period'), $request->query('from'), $request->query('to'));
        $commission = $commissions->forAgent($agent, $period['from'], $period['to']);
        $statement = $commissions->statement($agent, $period['from'], $period['to']);

        // Records credited to this person are theirs to see, also at properties outside their assignments.
        [$statistics, $performance, $leads, $tenancies, $tasks] = $access->unrestricted(fn () => [
            $stats->getAgentStats($agent->id),
            $stats->getPerformanceMetrics($agent->id),
            Lead::with(['complex:id,title,slug', 'profile'])->where('assigned_agent_id', $agent->id)->latest()->take(10)->get(),
            TenantManagement::with(['tenantProfile', 'complex:id,title,slug'])->where('assigned_agent_id', $agent->id)
                ->where('managed_by', $user->businessOwnerId())->latest()->take(10)->get(),
            LeadTask::with('lead.profile')->where('assigned_agent_id', $agent->id)->whereIn('status', ['pending', 'in_progress', 'blocked'])
                ->orderByRaw('sla_breach_at is null')->orderBy('sla_breach_at')->take(8)->get(),
        ]);
        // What this agent is assigned: whole properties (as lead agent) and individual units.
        [$leadProperties, $residentialUnits, $commercialUnits] = $access->unrestricted(fn () => [
            \App\Models\Complex::forBusiness($user->businessOwnerId())->where('assigned_agent_id', $agent->id)->orderBy('title')->get(['id', 'title', 'slug', 'type']),
            \App\Models\ComplexResidentialUnit::with('complex:id,title,slug')->where('assigned_agent_id', $agent->id)->orderBy('complex_id')->orderBy('unit_number')->get(['id', 'complex_id', 'unit_number', 'status', 'unit_category']),
            \App\Models\CommercialUnit::with('complex:id,title,slug')->where('assigned_agent_id', $agent->id)->orderBy('complex_id')->orderBy('unit_number')->get(['id', 'complex_id', 'unit_number', 'status', 'unit_sq_ft']),
        ]);
        $canOpenProperty = fn ($complexId) => $complexId && $access->allows($complexId) && $user->hasBusinessPermission('complex_management', 'view');
        $targetProgress = (float) $agent->sales_target > 0 ? min(100, round($commission['total_payments'] / (float) $agent->sales_target * 100)) : null;

        return view('pages.business.agents.my-dashboard', compact('agent', 'period', 'commission', 'statement', 'statistics', 'performance', 'leads', 'tenancies', 'tasks', 'canOpenProperty', 'targetProgress', 'leadProperties', 'residentialUnits', 'commercialUnits'));
    }
}
