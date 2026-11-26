<?php

namespace App\Services\Crm;

use App\Enums\LeadTaskStatusEnum;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\LeadTaskTemplate;

/** Lead workflow rules: when a lead may move to its next phase, and the system tasks each phase starts with. */
class LeadWorkflowService
{
    /**
     * Check if lead can advance to next phase
     */
    public function canLeadAdvancePhase(Lead $lead): bool
    {
        // Get all blocking tasks in the current phase
        $blockingTasks = LeadTask::where('lead_id', $lead->id)
            ->where('phase', $lead->workflow_phase)
            ->where('blocks_next_phase', true)
            ->get();
    
        if ($blockingTasks->isEmpty()) {
            return false;
        }
    
        // Check if all blocking tasks are completed or skipped
        foreach ($blockingTasks as $task) {
            if (!in_array($task->status->value, ['completed', 'skipped'])) {
                return false;
            }
        }
    
        return true;
    }
    /**
     * Create system tasks for a specific phase
     */
    public function createPhaseSystemTasks(Lead $lead, string $phase)
    {
        // Get system task templates for the new phase
        $templates = LeadTaskTemplate::where('phase', $phase)
            ->where('task_type', 'system_assigned')
            ->where('is_active', true)
            ->orderBy('sequence_order')
            ->get();
    
        foreach ($templates as $template) {
            // Check if task already exists
            $existingTask = LeadTask::where('lead_id', $lead->id)
                ->where('template_id', $template->id)
                ->first();

            if (!$existingTask) {
                LeadTask::create([
                    'lead_id' => $lead->id,
                    'assigned_agent_id' => $lead->assigned_agent_id,
                    'template_id' => $template->id,
                    'title' => $template->title,
                    'description' => $template->description,
                    'task_type' => $template->task_type,
                    'phase' => $template->phase,
                    'sequence_order' => $template->sequence_order,
                    'status' => LeadTaskStatusEnum::PENDING,
                    'is_required' => $template->is_required,
                    'blocks_next_phase' => $template->blocks_next_phase,
                    'sla_hours' => $template->sla_hours,
                    'sla_breach_at' => $template->sla_hours ? now()->addHours($template->sla_hours) : null,
                    'blocked_by_task_id' => $this->getBlockingTaskId($lead, $template),
                    'created_by' => business_owner_id()
                ]);
            }
        }
    }
    /**
     * Get blocking task ID based on dependencies
     */
    public function getBlockingTaskId(Lead $lead, $template): ?int
    {
        if (!$template->depends_on_task_template_id) {
            return null;
        }
    
        $blockingTask = LeadTask::where('lead_id', $lead->id)
            ->where('lead_task_template_id', $template->depends_on_task_template_id)
            ->first();
    
        return $blockingTask?->id;
    }
}
