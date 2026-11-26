<?php

namespace App\Observers;

use App\Models\Lead;
use App\Models\LeadTask;
use App\Enums\LeadTaskStatusEnum;
use App\Services\Crm\LeadConversionService;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    protected $conversionService;

    public function __construct(LeadConversionService $conversionService)
    {
        $this->conversionService = $conversionService;
    }

    /**
     * Handle the Lead "created" event.
     * This fires when a new lead is created
     */
    public function created(Lead $lead)
    {
        Log::info('LeadObserver: Lead created', [
            'lead_id' => $lead->id,
            'assigned_agent_id' => $lead->assigned_agent_id
        ]);

        // Tell the agent it was given to, or the people who assign leads that it needs an agent.
        $lead->assigned_agent_id
            ? app(\App\Services\NotificationCenter::class)->leadAssigned($lead)
            : app(\App\Services\NotificationCenter::class)->leadNeedsAgent($lead);

        // If lead is created with an agent already assigned
        if ($lead->assigned_agent_id) {
            Log::info('LeadObserver: Lead created with agent assigned, creating tasks', [
                'lead_id' => $lead->id,
                'agent_id' => $lead->assigned_agent_id
            ]);

            try {
                $this->conversionService->initiateSystemAssignedTasks($lead);
                
                Log::info('LeadObserver: Tasks created successfully', [
                    'lead_id' => $lead->id
                ]);

                // Update workflow phase
                $lead->workflow_phase = 'qualification';
                $lead->phase_started_at = now();
                $lead->saveQuietly();

                // Automatically start the first task (Acknowledge Lead Assignment)
                $this->autoStartFirstTask($lead);

                Log::info('LeadObserver: Workflow phase updated', [
                    'lead_id' => $lead->id,
                    'phase' => 'qualification'
                ]);
            } catch (\Exception $e) {
                Log::error('LeadObserver: Failed to create tasks on lead creation', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Handle the Lead "updated" event.
     * This fires when a lead is updated
     */
    public function updated(Lead $lead)
    {
        Log::info('LeadObserver: Lead updated', [
            'lead_id' => $lead->id,
            'assigned_agent_id' => $lead->assigned_agent_id,
            'wasChanged' => $lead->wasChanged('assigned_agent_id'),
            'original' => $lead->getOriginal('assigned_agent_id')
        ]);

        // `updated` fires after the save; wasChanged() records which fields changed.
        if ($lead->wasChanged('assigned_agent_id')) {
            $previousValue = $lead->getOriginal('assigned_agent_id');
            if ($lead->assigned_agent_id) {
                app(\App\Services\NotificationCenter::class)->leadAssigned($lead);
            }
            
            Log::info('LeadObserver: Agent assignment detected', [
                'lead_id' => $lead->id,
                'new_agent' => $lead->assigned_agent_id,
                'previous_agent' => $previousValue
            ]);
            
            if (!$previousValue && $lead->assigned_agent_id) {
                Log::info('LeadObserver: Creating system tasks for lead', [
                    'lead_id' => $lead->id,
                    'agent_id' => $lead->assigned_agent_id
                ]);
                
                try {
                    $this->conversionService->initiateSystemAssignedTasks($lead);
                    
                    Log::info('LeadObserver: Tasks created successfully', [
                        'lead_id' => $lead->id
                    ]);

                    // Automatically start the first task (Acknowledge Lead Assignment)
                    $this->autoStartFirstTask($lead);
                    
                } catch (\Exception $e) {
                    Log::error('LeadObserver: Failed to create tasks', [
                        'lead_id' => $lead->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
                
                // Update workflow phase
                $lead->workflow_phase = 'qualification';
                $lead->phase_started_at = now();
                $lead->saveQuietly(); // Avoid recursion
                
                Log::info('LeadObserver: Workflow phase updated', [
                    'lead_id' => $lead->id,
                    'phase' => 'qualification'
                ]);
            } else {
                // Keep pending work with the current owner of the lead. Completed task
                // assignments remain unchanged as part of the activity history.
                LeadTask::where('lead_id', $lead->id)
                    ->whereNotIn('status', ['completed', 'skipped', 'cancelled'])
                    ->update(['assigned_agent_id' => $lead->assigned_agent_id]);

                Log::info('LeadObserver: Updated ownership of open lead tasks', [
                    'lead_id' => $lead->id,
                    'previous_agent' => $previousValue,
                    'new_agent' => $lead->assigned_agent_id
                ]);
            }
        }
    }

    /**
     * Automatically start the first task in Qualification phase
     */
    protected function autoStartFirstTask(Lead $lead): void
    {
        try {
            // Find the first task in qualification phase (Acknowledge Lead Assignment)
            $firstTask = LeadTask::where('lead_id', $lead->id)
                ->where('phase', 'qualification')
                ->where('status', LeadTaskStatusEnum::PENDING)
                ->orderBy('sequence_order')
                ->first();

            if ($firstTask) {
                // Auto-start the first task
                $firstTask->update([
                    'status' => LeadTaskStatusEnum::IN_PROGRESS,
                    'started_at' => now()
                ]);

                // Log the interaction
                $lead->addInteraction('task_auto_started', [
                    'task_id' => $firstTask->id,
                    'task_title' => $firstTask->title,
                    'phase' => 'qualification',
                    'auto_started_at' => now()->toDateTimeString()
                ]);

                Log::info('LeadObserver: First task auto-started', [
                    'lead_id' => $lead->id,
                    'task_id' => $firstTask->id,
                    'task_title' => $firstTask->title
                ]);
            }
        } catch (\Exception $e) {
            Log::error('LeadObserver: Failed to auto-start first task', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
