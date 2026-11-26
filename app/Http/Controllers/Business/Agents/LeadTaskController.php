<?php

namespace App\Http\Controllers\Business\Agents;

use Exception;
use App\Models\Lead;
use App\Models\Agent;
use App\Models\LeadTask;
use Illuminate\Http\Request;
use App\Models\LeadTaskTemplate;
use App\Enums\LeadTaskStatusEnum;
use Illuminate\Support\Facades\Log;
use App\Enums\LeadWorkflowPhaseEnum;
use App\Http\Controllers\Controller;
use App\Services\Crm\LeadConversionService;

class LeadTaskController extends Controller
{
    use Concerns\ScopesLeadTaskBoard;

    protected $conversionService;

    public function __construct(LeadConversionService $conversionService)
    {
        $this->middleware('auth');
        $this->conversionService = $conversionService;
    }

    /**
     * Display agent's task dashboard
     */
    public function index(Request $request)
    {
        $agent = $this->ownAgent();
        if (!$agent && !$this->seesAllTasks()) {
            return redirect()->route('business.my_agent')
                ->with('info', "You don't have an agent profile yet, so no tasks are assigned to you.");
        }
        $scope = $this->boardScope($request);
        $agentFilter = $this->agentFilter($request, $scope);
        $agentIds = $this->boardAgentIds($scope, $agentFilter);

        // Auto-heal any stuck leads (completed phase but missing next phase tasks)
        Agent::whereIn('id', $agentIds ?: [0])->get()->each(fn (Agent $boardAgent) => $this->healStuckLeads($boardAgent));

        // Calculate dashboard metrics directly
        $allTasks = LeadTask::whereIn('assigned_agent_id', $agentIds ?: [0])->get();
        
        $metrics = [
            'total_tasks' => $allTasks->count(),
            'pending_tasks' => $allTasks->where('status.value', 'pending')->count(),
            'in_progress_tasks' => $allTasks->where('status.value', 'in_progress')->count(),
            'overdue_tasks' => $allTasks->filter(function ($task) {
                return ($task->is_overdue || ($task->sla_breach_at && $task->sla_breach_at->isPast()))
                    && !in_array($task->status->value, ['completed', 'cancelled', 'skipped'], true);
            })->count(),
            'completed_tasks' => $allTasks->whereIn('status.value', ['completed', 'skipped'])->count(),
            'blocked_tasks' => $allTasks->where('status.value', 'blocked')->count(),
            'due_today' => $allTasks->filter(fn ($task) => $task->sla_breach_at && $task->sla_breach_at->isToday()
                && !in_array($task->status->value, ['completed', 'cancelled', 'skipped'], true))->count(),
            // Tasks only start once a lead has an agent.
            'unassigned_leads' => Lead::forBusiness()->whereNull('assigned_agent_id')
                ->whereNotIn('status', ['converted', 'closed_won', 'closed_lost', 'lost'])->count(),
        ];
        $canSeeAll = $this->seesAllTasks();
        $boardAgents = $canSeeAll ? Agent::where('created_by', business_owner_id())->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'user_id']) : collect();
        $workflow = $this->workflow($allTasks);

        return view('pages.business.crm.tasks.index', compact('metrics', 'agent', 'scope', 'canSeeAll', 'boardAgents', 'agentFilter', 'workflow'));
    }

    /**
     * "How tasks are assigned": the lead phases, the tasks each phase creates (Erty's task templates),
     * and how many of the board's tasks are open or overdue in each phase.
     */
    private function workflow($tasks): array
    {
        $templates = LeadTaskTemplate::active()->ordered()->get()->groupBy('phase');
        $open = fn ($task) => !in_array($task->status->value, ['completed', 'cancelled', 'skipped'], true);
        $starts = [
            'qualification' => 'As soon as an agent is assigned to a lead (when the lead is captured with an agent, or assigned later). The first task starts automatically.',
            'engagement' => 'When every qualification task that moves the lead on is completed or skipped.',
            'conversion' => 'When every engagement task that moves the lead on is completed or skipped: the lead is ready to become a tenant.',
            'finalization' => 'When the conversion tasks are done, or when the lead is converted to a tenant.',
        ];
        $phases = [];
        foreach (['qualification', 'engagement', 'conversion', 'finalization'] as $phase) {
            $phaseTasks = $tasks->filter(fn ($task) => $task->phase === $phase);
            $phases[] = [
                'key' => $phase,
                'label' => LeadWorkflowPhaseEnum::from($phase)->label(),
                'starts' => $starts[$phase],
                'templates' => $templates->get($phase, collect())->values(),
                'open' => $phaseTasks->filter($open)->count(),
                'overdue' => $phaseTasks->filter(fn ($task) => $open($task) && ($task->is_overdue || ($task->sla_breach_at && $task->sla_breach_at->isPast())))->count(),
            ];
        }
        return $phases;
    }

    /**
     * Get tasks data for DataTables (Ajax)
     */
    public function getData(Request $request)
    {
        Log::info('Fetching lead tasks data'); 
    
        $scope = $this->boardScope($request);
        $agentIds = $this->boardAgentIds($scope, $this->agentFilter($request, $scope));
        
        if (!$agentIds) {
            return response()->json([
                'draw' => intval($request->draw ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'metrics' => ['total_tasks' => 0, 'pending_tasks' => 0, 'in_progress_tasks' => 0, 'overdue_tasks' => 0],
                'data' => []
            ]);
        }

        $agentTasks = LeadTask::whereIn('assigned_agent_id', $agentIds)->get(['status', 'is_overdue', 'sla_breach_at']);
        $dashboardMetrics = [
            'total_tasks' => $agentTasks->count(),
            'pending_tasks' => $agentTasks->filter(fn ($task) => $task->status === LeadTaskStatusEnum::PENDING)->count(),
            'in_progress_tasks' => $agentTasks->filter(fn ($task) => $task->status === LeadTaskStatusEnum::IN_PROGRESS)->count(),
            'overdue_tasks' => $agentTasks->filter(fn ($task) =>
                ($task->is_overdue || ($task->sla_breach_at && $task->sla_breach_at->isPast()))
                && !in_array($task->status->value, ['completed', 'cancelled', 'skipped'], true)
            )->count(),
        ];
    
        // Get all tasks grouped by lead
        $query = LeadTask::with(['lead', 'lead.profile', 'lead.assignedAgent', 'template', 'blockedByTask'])
            ->whereIn('assigned_agent_id', $agentIds);
    
        // Apply filters
        $status = $request->input('status');
        if ($status === 'overdue') {
            $query->whereNotIn('status', ['completed', 'cancelled', 'skipped'])
                ->where(function ($overdue) {
                    $overdue->where('is_overdue', true)
                        ->orWhere(function ($due) {
                            $due->whereNotNull('sla_breach_at')->where('sla_breach_at', '<=', now());
                        });
                });
        } elseif (in_array($status, array_map(fn ($case) => $case->value, LeadTaskStatusEnum::cases()), true)) {
            $query->where('status', $status);
        }
    
        if ($request->has('phase') && !empty($request->phase)) {
            $query->where('phase', $request->phase);
        }
    
        // Search
        if ($request->has('search') && !empty($request->search['value'])) {
            $search = $request->search['value'];
            $query->where(function($q) use ($search) {
                $q->whereLike('title', "%{$search}%")
                  ->orWhereLike('description', "%{$search}%")
                  ->orWhereHas('lead', function($leadQuery) use ($search) {
                      $leadQuery->whereLike('first_name', "%{$search}%")
                                ->orWhereLike('last_name', "%{$search}%")
                                ->orWhereLike('company_name', "%{$search}%")
                                ->orWhereLike('email', "%{$search}%");
                  });
            });
        }
    
        // Get all tasks
        $tasks = $query->orderBy('created_at', 'desc')->get();
        
        // Group tasks by lead_id
        $groupedTasks = $tasks->groupBy('lead_id');
        $workflowProgressTasksByLead = $groupedTasks->isEmpty()
            ? collect()
            : LeadTask::whereIn('lead_id', $groupedTasks->keys())
                ->get(['lead_id', 'status'])
                ->groupBy('lead_id');
        
        // Get total counts
        $totalRecords = LeadTask::whereIn('assigned_agent_id', $agentIds)
            ->select('lead_id')
            ->distinct()
            ->count();
        $filteredRecords = $groupedTasks->count();
    
        // Prepare data for DataTables
        $data = [];
        foreach ($groupedTasks as $leadId => $leadTasks) {
            $lead = $leadTasks->first()->lead;
            $tasksArray = [];
            
            foreach ($leadTasks as $task) {
                $tasksArray[] = [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $task->status->value,
                    'status_label' => $task->status->label(),
                    'status_badge' => $task->status->badgeClass(),
                    'phase' => $task->phase,
                    'phase_label' => LeadWorkflowPhaseEnum::from($task->phase)->label(),
                    'task_type' => $task->task_type->value,
                    'task_type_label' => $task->task_type->label(),
                    'is_required' => $task->is_required,
                    'blocks_next_phase' => $task->blocks_next_phase,
                    'is_overdue' => (bool) ($task->is_overdue || ($task->sla_breach_at && $task->sla_breach_at->isPast()))
                        && !in_array($task->status->value, ['completed', 'cancelled', 'skipped'], true),
                    'sla_hours' => $task->sla_hours,
                    'time_remaining' => $task->getTimeRemaining(),
                    'due_date' => $task->sla_breach_at?->format('Y-m-d H:i:s'),
                    'started_at' => $task->started_at?->format('Y-m-d H:i:s'),
                    'completed_at' => $task->completed_at?->format('Y-m-d H:i:s'),
                    'blocked_by' => $task->blocked_by_task_id ? [
                        'id' => $task->blockedByTask->id,
                        'title' => $task->blockedByTask->title
                    ] : null,
                ];
            }
            
            // Calculate progress
            $workflowTasks = $workflowProgressTasksByLead->get($leadId, collect());
            $totalTasks = $workflowTasks->count();
            $completedTasks = $workflowTasks->filter(fn ($task) => in_array($task->status->value, ['completed', 'skipped'], true))->count();
            $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
            
            $data[] = [
                'lead' => [
                    'id' => $lead->id,
                    'encrypted_lead_id' => encrypt($lead->id),
                    'lead_type' => $lead->profile->lead_type,
                    'company_name' => $lead->profile->company_name ?? '',
                    'representative_name' => $lead->profile->contact_person ?? '',
                    'first_name' => $lead->profile->first_name ?? '',
                    'last_name' => $lead->profile->last_name ?? '',
                    'name' => trim(($lead->profile->first_name ?? '') . ' ' . ($lead->profile->last_name ?? '')) ?: 'Unnamed Lead',
                    'email' => $lead->profile->email ?? '',
                    'phone' => $lead->profile->phone ?? '',
                    'status' => $lead->status,
                    'workflow_phase' => $lead->workflow_phase
                ],
                'tasks' => $tasksArray,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'progress_percentage' => $progressPercentage,
                'current_phase' => $lead->workflow_phase,
                'current_phase_label' => LeadWorkflowPhaseEnum::from($lead->workflow_phase)->label()
            ];
        }
    
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $length = $length < 1 ? 10 : min($length, 100);

        return response()->json([
            'draw' => intval($request->draw ?? 0),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'metrics' => $dashboardMetrics,
            'data' => array_slice($data, $start, $length)
        ]);
    }

    /**
     * Show specific task details
     */
    public function show($id)
    {
        $user = auth()->user();
        $agentIds = Agent::where('created_by', $user->businessOwnerId())->pluck('id');
        abort_if($agentIds->isEmpty(), 404);
        
        $task = LeadTask::with([
            'lead', 
            'lead.assignedAgent',
            'template',
            'blockedByTask',
            'blockingTasks',
            'creator',
            'completedBy'
        ])
        ->where('id', $id)
        ->whereIn('assigned_agent_id', $agentIds)
        ->firstOrFail();

        // A task is worked on its lead's task list: open that list at this task.
        return redirect()->to('/agents/lead-tasks/' . \Illuminate\Support\Facades\Crypt::encrypt($task->lead_id) . '#task-' . $task->id);
    }
    
    
    
    
    
    
    /**
     * Detect and heal leads stuck in phases (completed all blocking tasks but missing next phase tasks)
     */

    /** The owner, and team members who edit tasks across every property, can work on any agent's tasks. */

    private function healStuckLeads(Agent $agent): void
    {
        try {
            Log::info('Healing stuck leads for agent', ['agent_id' => $agent->id]);

            // Get all active leads assigned to this agent
            $leads = Lead::where('assigned_agent_id', $agent->id)
                ->whereNotIn('workflow_phase', ['finalization', 'completed'])
                ->whereNotIn('status', ['closed_won', 'closed_lost'])
                ->get();

            foreach ($leads as $lead) {
                // Check if lead can advance but hasn't
                if (app(\App\Services\Crm\LeadWorkflowService::class)->canLeadAdvancePhase($lead)) {
                    $currentPhase = $lead->workflow_phase;
                    
                    // Check if next phase has no tasks
                    $phaseOrder = ['qualification', 'engagement', 'conversion', 'finalization'];
                    $currentPhaseIndex = array_search($currentPhase, $phaseOrder);
                    
                    if ($currentPhaseIndex !== false && $currentPhaseIndex < count($phaseOrder) - 1) {
                        $nextPhase = $phaseOrder[$currentPhaseIndex + 1];
                        
                        // Check if lead already advanced but has no tasks in next phase
                        $nextPhaseTasks = LeadTask::where('lead_id', $lead->id)
                            ->where('phase', $nextPhase)
                            ->count();
                        
                        if ($nextPhaseTasks === 0) {
                            // Lead can advance - do it now
                            Log::info("Healing stuck lead: Advancing from {$currentPhase} to {$nextPhase}", [
                                'lead_id' => $lead->id,
                                'current_phase' => $currentPhase,
                                'next_phase' => $nextPhase
                            ]);
                            
                            $lead->update([
                                'workflow_phase' => $nextPhase,
                                'phase_started_at' => now()
                            ]);
                            
                            // Create missing phase tasks
                            app(\App\Services\Crm\LeadWorkflowService::class)->createPhaseSystemTasks($lead, $nextPhase);
                            
                            // Log interaction
                            $lead->addInteraction('phase_advanced', [
                                'old_phase' => $currentPhase,
                                'new_phase' => $nextPhase,
                                'reason' => 'Auto-healed stuck lead',
                                'healed_by' => 'system'
                            ]);
                        }
                    }
                }
                
                // Also check if lead is in a phase but has no tasks for current phase
                $currentPhaseTasks = LeadTask::where('lead_id', $lead->id)
                    ->where('phase', $lead->workflow_phase)
                    ->count();
                    
                if ($currentPhaseTasks === 0 && $lead->workflow_phase) {
                    Log::info("Healing stuck lead: Creating missing tasks for current phase", [
                        'lead_id' => $lead->id,
                        'phase' => $lead->workflow_phase
                    ]);
                    
                    app(\App\Services\Crm\LeadWorkflowService::class)->createPhaseSystemTasks($lead, $lead->workflow_phase);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error healing stuck leads: ' . $e->getMessage(), [
                'agent_id' => $agent->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    

    /**
     * Get tasks for a specific lead
     */
    public function getLeadTasks($leadId)
    {
        try {
            $user = auth()->user();
            $agentIds = $this->workableAgentIds();
            
            $lead = Lead::where('id', $leadId)
                ->whereIn('assigned_agent_id', $agentIds)
                ->firstOrFail();

            $tasks = LeadTask::with(['template', 'blockedByTask'])
                ->where('lead_id', $lead->id)
                ->orderBy('phase')
                ->orderBy('sequence_order')
                ->get()
                ->groupBy('phase');

            $phaseProgress = [];
            foreach ($tasks as $phase => $phaseTasks) {
                $total = $phaseTasks->count();
                $completed = $phaseTasks->whereIn('status', [
                    LeadTaskStatusEnum::COMPLETED, 
                    LeadTaskStatusEnum::SKIPPED
                ])->count();

                $phaseProgress[$phase] = [
                    'total' => $total,
                    'completed' => $completed,
                    'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
                    'tasks' => $phaseTasks->map(function($task) {
                        return [
                            'id' => $task->id,
                            'title' => $task->title,
                            'description' => $task->description,
                            'status' => $task->status->value,
                            'status_label' => $task->status->label(),
                            'status_badge' => $task->status->badgeClass(),
                            'task_type' => $task->task_type->value,
                            'is_required' => $task->is_required,
                            'blocks_next_phase' => $task->blocks_next_phase,
                            'is_overdue' => $task->is_overdue,
                            'time_remaining' => $task->getTimeRemaining(),
                            'completed_at' => $task->completed_at?->format('Y-m-d H:i:s'),
                            'completion_notes' => $task->completion_notes
                        ];
                    })
                ];
            }

            return response()->json([
                'success' => true,
                'lead' => [
                    'id' => $lead->id,
                    'name' => $lead->first_name . ' ' . $lead->last_name,
                    'workflow_phase' => $lead->workflow_phase,
                    'can_advance_phase' => $lead->canAdvanceToNextPhase()
                ],
                'phase_progress' => $phaseProgress,
                'overall_progress' => $lead->getPhaseProgress()
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get lead tasks: ' . $e->getMessage(), [
                'lead_id' => $leadId,
                'user_id' => business_owner_id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tasks: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get upcoming tasks (next 24 hours)
     */
    public function getUpcoming()
    {
        try {
            $agentIds = $this->boardAgentIds($this->boardScope(request()));
            
            if (!$agentIds) {
                return response()->json([
                    'success' => false,
                    'message' => 'No agent profile found'
                ], 404);
            }

            $tasks = LeadTask::with(['lead'])
                ->whereIn('assigned_agent_id', $agentIds)
                ->where('status', LeadTaskStatusEnum::PENDING)
                ->whereBetween('sla_breach_at', [now(), now()->addHours(24)])
                ->orderBy('sla_breach_at', 'asc')
                ->get()
                ->map(function($task) {
                    return [
                        'id' => $task->id,
                        'title' => $task->title,
                        'phase' => $task->phase,
                        'phase_label' => LeadWorkflowPhaseEnum::from($task->phase)->label(),
                        'time_remaining' => $task->getTimeRemaining(),
                        'sla_breach_at' => $task->sla_breach_at->toIso8601String(),
                        'lead' => [
                            'id' => $task->lead->id,
                            'name' => $task->lead->first_name . ' ' . $task->lead->last_name
                        ]
                    ];
                });

            return response()->json([
                'success' => true,
                'tasks' => $tasks
            ]);

        } catch (Exception $e) {
            Log::error('Failed to get upcoming tasks: ' . $e->getMessage(), [
                'user_id' => business_owner_id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming tasks'
            ], 500);
        }
    }
}
