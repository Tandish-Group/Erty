<?php

namespace App\Listeners;

use App\Events\LeadPhaseAdvanced;
use App\Models\LeadTaskTemplate;

class CreatePhaseTasksListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(LeadPhaseAdvanced $event): void
    {
        // Get system-assigned task templates for the new phase
        $templates = LeadTaskTemplate::systemAssigned()
            ->inPhase($event->newPhase->value)
            ->active()
            ->ordered()
            ->get();

        // Create tasks from templates
        foreach ($templates as $template) {
            // Phase events may be retried; template-based creation is idempotent.
            $template->createTaskForLead($event->lead);
        }
    }
}
