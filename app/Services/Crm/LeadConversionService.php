<?php

namespace App\Services\Crm;

use Exception;
use App\Models\Lead;
use App\Models\LeadTask;
use App\Models\LeadTaskTemplate;
use App\Models\TenantManagement;
use App\Enums\LeadTaskStatusEnum;
use App\Enums\LeadWorkflowPhaseEnum;

class LeadConversionService
{
    public function initiateSystemAssignedTasks(Lead $lead): void
    {
        // Create initial system tasks when lead assigned to agent
        $templates = LeadTaskTemplate::systemAssigned()
            ->inPhase('qualification')
            ->active()
            ->ordered()
            ->get();

        foreach ($templates as $template) {
            $template->createTaskForLead($lead);
        }
    }

    public function createAgentTask(
        Lead $lead,
        string $phase,
        string $title,
        ?string $description = null,
        ?int $slaHours = null,
        bool $isRequired = false,
        bool $blocksNextPhase = false
    ): LeadTask {
        return LeadTask::create([
            'lead_id' => $lead->id,
            'assigned_agent_id' => $lead->assigned_agent_id,
            'task_type' => 'agent_operational',
            'phase' => $phase,
            'title' => $title,
            'description' => $description,
            'sla_hours' => $slaHours,
            'is_required' => $isRequired,
            'blocks_next_phase' => $blocksNextPhase,
            'status' => LeadTaskStatusEnum::PENDING
        ]);
    }

    public function createFinalizationTasks(Lead $lead): void
    {
        $templates = \App\Models\LeadTaskTemplate::systemAssigned()
            ->inPhase('finalization')
            ->active()
            ->ordered()
            ->get();

        foreach ($templates as $template) {
            $template->createTaskForLead($lead);
        }
    }

    /**
     * A lead became a tenant: link the tenancy, mark the lead won, close its open pre-conversion
     * tasks and start the finalization tasks for its agent.
     */
    public function markConverted(Lead $lead, TenantManagement $tenancy): void
    {
        if ($lead->converted_tenant_id) {
            throw new Exception('This lead has already been converted.');
        }
        $lead->forceFill([
            'converted_tenant_id' => $tenancy->id,
            'converted_at' => now(),
            'converted_by' => auth()->id(),
            'status' => 'closed_won',
            'workflow_phase' => LeadWorkflowPhaseEnum::FINALIZATION->value,
            'phase_started_at' => now(),
        ])->saveQuietly();

        LeadTask::where('lead_id', $lead->id)
            ->whereIn('phase', ['qualification', 'engagement', 'conversion'])
            ->whereNotIn('status', [LeadTaskStatusEnum::COMPLETED->value, LeadTaskStatusEnum::SKIPPED->value, LeadTaskStatusEnum::CANCELLED->value])
            ->get()
            ->each(fn (LeadTask $task) => $task->forceFill([
                'status' => LeadTaskStatusEnum::COMPLETED,
                'completed_at' => now(),
                'completed_by' => auth()->id(),
                'completion_notes' => 'Tenant onboarded: ' . $tenancy->tenancy_ref,
            ])->save());

        if ($lead->assigned_agent_id) {
            $this->createFinalizationTasks($lead);
        }
        $lead->addInteraction('converted_to_tenant', ['tenancy_id' => $tenancy->id, 'tenancy_ref' => $tenancy->tenancy_ref]);
        app(\App\Services\NotificationCenter::class)->leadConverted($lead, $tenancy);
    }

    /**
     * A lead bought a house: link the sale, mark the lead won and close its open pre-conversion tasks.
     * There is no tenant onboarding to finalise, so no finalization tasks start.
     */
    public function markSold(Lead $lead, \App\Models\PropertySale $sale): void
    {
        if ($lead->converted_tenant_id || $lead->converted_sale_id) {
            throw new Exception('This lead has already been converted.');
        }
        $lead->forceFill([
            'converted_sale_id' => $sale->id,
            'converted_at' => now(),
            'converted_by' => auth()->id(),
            'status' => 'closed_won',
            'workflow_phase' => LeadWorkflowPhaseEnum::COMPLETED->value,
            'phase_started_at' => now(),
        ])->saveQuietly();

        LeadTask::where('lead_id', $lead->id)
            ->whereNotIn('status', [LeadTaskStatusEnum::COMPLETED->value, LeadTaskStatusEnum::SKIPPED->value, LeadTaskStatusEnum::CANCELLED->value])
            ->get()
            ->each(fn (LeadTask $task) => $task->forceFill([
                'status' => LeadTaskStatusEnum::COMPLETED,
                'completed_at' => now(),
                'completed_by' => auth()->id(),
                'completion_notes' => 'House sale started: ' . $sale->reference,
            ])->save());

        $lead->addInteraction('converted_to_sale', ['sale_id' => $sale->id, 'sale_reference' => $sale->reference]);
    }

}