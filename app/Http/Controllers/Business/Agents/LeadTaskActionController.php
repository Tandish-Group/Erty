<?php

namespace App\Http\Controllers\Business\Agents;

use App\Enums\LeadTaskStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Services\Crm\LeadConversionService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Working a lead task: start, complete (advancing the lead's phase), block, skip, and adding a task. */
class LeadTaskActionController extends Controller
{
    use Concerns\ScopesLeadTaskBoard;

    public function __construct(
        private LeadConversionService $conversionService,
    ) {
        $this->middleware('auth');
    }

    /**
     * Start a task (mark as in progress)
     */
    public function start($id)
    {
        try {
            $user = auth()->user();
            $agentIds = $this->workableAgentIds();
            
            $task = LeadTask::where('id', $id)
                ->whereIn('assigned_agent_id', $agentIds)
                ->firstOrFail();

            if ($task->status !== LeadTaskStatusEnum::PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending tasks can be started'
                ], 400);
            }

            $task->markInProgress();

            // Log interaction on lead
            $task->lead->addInteraction('task_started', [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'started_by' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task started successfully',
                'task' => [
                    'id' => $task->id,
                    'status' => $task->status->value,
                    'started_at' => $task->started_at->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to start task: ' . $e->getMessage(), [
                'task_id' => $id,
                'user_id' => business_owner_id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start task: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Complete a task with structured data capture and phase progression
     */
    public function complete(Request $request, $id)
    {
        $request->validate([
            'completion_notes' => 'nullable|string|max:5000',
            'completion_data' => 'nullable|array'
        ]);
    
        DB::beginTransaction();
        
        try {
            $user = auth()->user();
            $agentIds = $this->workableAgentIds();
            
            if (!$agentIds) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agent profile not found'
                ], 404);
            }
            
            $task = LeadTask::with('lead')->where('id', $id)
                ->whereIn('assigned_agent_id', $agentIds)
                ->firstOrFail();
    
            if (in_array($task->status->value, ['completed', 'cancelled'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Task is already completed or cancelled'
                ], 400);
            }
    
            $lead = $task->lead;
            
            // Process task-specific completion data
            $completionData = $request->completion_data ?? [];
            $this->processTaskCompletion($task, $lead, $completionData);
    
            // Mark task as completed
            $task->markCompleted(
                $request->completion_notes ?? json_encode($completionData),
                $completionData,
                $user->id
            );
    
            // Log interaction on lead
            $lead->addInteraction('task_completed', [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'phase' => $task->phase,
                'completed_by' => $user->name,
                'completion_data' => $completionData
            ]);
    
            // Check if all blocking tasks in current phase are completed
            $canAdvancePhase = app(\App\Services\Crm\LeadWorkflowService::class)->canLeadAdvancePhase($lead);
            $phaseAdvanced = false;
            $newPhase = null;
    
            if ($canAdvancePhase && $task->blocks_next_phase) {
                $phaseAdvanced = $this->advanceLeadToNextPhase($lead);
                if ($phaseAdvanced) {
                    $newPhase = $lead->fresh()->workflow_phase;
                    
                    // Create tasks for the new phase
                    app(\App\Services\Crm\LeadWorkflowService::class)->createPhaseSystemTasks($lead, $newPhase);
                    
                    // Log phase advancement
                    $lead->addInteraction('phase_advanced', [
                        'old_phase' => $task->phase,
                        'new_phase' => $newPhase,
                        'advanced_by' => $user->name,
                        'trigger_task' => $task->title
                    ]);
                }
            }
    
            DB::commit();
    
            $phaseProgress = $this->getLeadPhaseProgress($lead);
    
            return response()->json([
                'success' => true,
                'message' => 'Task completed successfully',
                'task' => [
                    'id' => $task->id,
                    'status' => $task->status->value,
                    'completed_at' => $task->completed_at->format('Y-m-d H:i:s')
                ],
                'phase_advanced' => $phaseAdvanced,
                'new_phase' => $newPhase,
                'can_advance_phase' => app(\App\Services\Crm\LeadWorkflowService::class)->canLeadAdvancePhase($lead->fresh()),
                'phase_progress' => $phaseProgress
            ]);
    
        } catch (Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to complete task: ' . $e->getMessage(), [
                'task_id' => $id,
                'user_id' => business_owner_id(),
                'trace' => $e->getTraceAsString()
            ]);
    
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete task: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Mark task as blocked
     */
    public function markBlocked(Request $request, $id)
    {
        $request->validate([
            'blocked_by_task_id' => 'required|exists:lead_tasks,id',
            'block_reason' => 'required|string|max:1000'
        ]);

        try {
            $user = auth()->user();
            $agentIds = $this->workableAgentIds();
            
            $task = LeadTask::where('id', $id)
                ->whereIn('assigned_agent_id', $agentIds)
                ->firstOrFail();

            // Validate blocking task exists and belongs to same lead
            $blockingTask = LeadTask::where('id', $request->blocked_by_task_id)
                ->where('lead_id', $task->lead_id)
                ->firstOrFail();

            $task->markBlocked($request->blocked_by_task_id, $request->block_reason);

            // Log interaction
            $task->lead->addInteraction('task_blocked', [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'blocked_by_task' => $blockingTask->title,
                'reason' => $request->block_reason,
                'blocked_by_user' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task marked as blocked',
                'task' => [
                    'id' => $task->id,
                    'status' => $task->status->value,
                    'blocked_by' => [
                        'id' => $blockingTask->id,
                        'title' => $blockingTask->title
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to mark task as blocked: ' . $e->getMessage(), [
                'task_id' => $id,
                'user_id' => business_owner_id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark task as blocked: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Skip a task (only if not required)
     */
    public function skip(Request $request, $id)
    {
        $request->validate([
            'skip_reason' => 'required|string|max:1000'
        ]);

        try {
            $user = auth()->user();
            $agentIds = $this->workableAgentIds();
            
            $task = LeadTask::where('id', $id)
                ->whereIn('assigned_agent_id', $agentIds)
                ->firstOrFail();

            if ($task->is_required) {
                return response()->json([
                    'success' => false,
                    'message' => 'Required tasks cannot be skipped'
                ], 400);
            }

            $task->skip($request->skip_reason);

            // Log interaction
            $task->lead->addInteraction('task_skipped', [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'reason' => $request->skip_reason,
                'skipped_by' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task skipped successfully',
                'task' => [
                    'id' => $task->id,
                    'status' => $task->status->value
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to skip task: ' . $e->getMessage(), [
                'task_id' => $id,
                'user_id' => business_owner_id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to skip task: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Create a new agent operational task
     */
    public function store(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'phase' => 'required|in:qualification,engagement,conversion,finalization',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'sla_hours' => 'nullable|integer|min:1|max:720',
            'is_required' => 'nullable|boolean',
            'blocks_next_phase' => 'nullable|boolean'
        ]);

        try {
            $user = auth()->user();
            $agentIds = $this->workableAgentIds();
            
            $lead = Lead::where('id', $request->lead_id)
                ->whereIn('assigned_agent_id', $agentIds)
                ->firstOrFail();

            $task = $this->conversionService->createAgentTask(
                lead: $lead,
                phase: $request->phase,
                title: $request->title,
                description: $request->description,
                slaHours: $request->sla_hours,
                isRequired: $request->boolean('is_required', false),
                blocksNextPhase: $request->boolean('blocks_next_phase', false)
            );

            // Log interaction
            $lead->addInteraction('task_created', [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'task_type' => 'agent_operational',
                'created_by' => $user->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully',
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'status' => $task->status->value,
                    'phase' => $task->phase
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Failed to create task: ' . $e->getMessage(), [
                'lead_id' => $request->lead_id,
                'user_id' => business_owner_id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create task: ' . $e->getMessage()
            ], 500);
        }
    }
    /**
     * Process task-specific completion logic
     */
    private function processTaskCompletion(LeadTask $task, Lead $lead, array $completionData)
    {
        // Handle task-specific actions based on task title
        switch ($task->title) {
            case 'Update Lead Status':
                if (isset($completionData['new_status'])) {
                    $lead->update([
                        'status' => $completionData['new_status'],
                        'priority' => $completionData['priority'] ?? $lead->priority
                    ]);
                }
                break;
    
            case 'Initial Contact Attempt':
                // Update lead with contact information
                if (isset($completionData['interest_level'])) {
                    $lead->update(['priority' => $this->mapInterestToPriority($completionData['interest_level'])]);
                }
                break;
    
            case 'Initiate Tenant Onboarding':
                // The onboarding details stay on the task (completion_data); the tenancy itself is
                // created with "Convert to tenant", which marks the lead converted.
                $lead->addInteraction('tenant_onboarding_started', array_filter([
                    'property_selected' => $completionData['property_selected'] ?? null,
                    'lease_start_date' => $completionData['lease_start_date'] ?? null,
                    'monthly_rent' => $completionData['monthly_rent'] ?? null,
                ]));
                break;
    
            case 'Close Lead Record':
                if (isset($completionData['final_status'])) {
                    $lead->update(['status' => $completionData['final_status']]);
                }
                break;
        }
    }
    /**
     * Advance lead to the next workflow phase
     */
    private function advanceLeadToNextPhase(Lead $lead): bool
    {
        $phaseOrder = ['qualification', 'engagement', 'conversion', 'finalization'];
        $currentPhaseIndex = array_search($lead->workflow_phase, $phaseOrder);
    
        if ($currentPhaseIndex === false || $currentPhaseIndex >= count($phaseOrder) - 1) {
            return false; // Already at last phase or invalid phase
        }
    
        $nextPhase = $phaseOrder[$currentPhaseIndex + 1];
        
        $lead->update([
            'workflow_phase' => $nextPhase,
            'phase_updated_at' => now()
        ]);
    
        return true;
    }
    /**
     * Get progress for all phases of a lead
     */
    private function getLeadPhaseProgress(Lead $lead): array
    {
        $phases = ['qualification', 'engagement', 'conversion', 'finalization'];
        $progress = [];
    
        foreach ($phases as $phase) {
            $tasks = LeadTask::where('lead_id', $lead->id)
                ->where('phase', $phase)
                ->get();
    
            $total = $tasks->count();
            $completed = $tasks->whereIn('status', ['completed', 'skipped'])->count();
    
            $progress[$phase] = [
                'total' => $total,
                'completed' => $completed,
                'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'is_current' => $phase === $lead->workflow_phase
            ];
        }
    
        return $progress;
    }
    /**
     * Map interest level to priority
     */
    private function mapInterestToPriority(string $interestLevel): string
    {
        return match($interestLevel) {
            'very_high' => 'hot',
            'high' => 'hot',
            'moderate' => 'warm',
            'low' => 'cold',
            'not_interested' => 'cold',
            default => 'warm'
        };
    }
}
