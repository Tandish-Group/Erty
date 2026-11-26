<?php

namespace App\Observers;

use App\Models\Agent;
use App\Services\Agents\AgentAssignmentService;
use App\Services\Commission\CommissionAgreementService;

/**
 * Agent profile changes that matter for responsibility and commission:
 * - commission settings edited on the profile become a new agreement version (history is kept);
 * - an agent switched off or removed stops being responsible for units and properties.
 */
class AgentCommissionObserver
{
    public function saved(Agent $agent): void
    {
        $hasTerms = $agent->commission_type || (float) $agent->override_rate > 0;
        if (($agent->wasRecentlyCreated && $hasTerms) || (!$agent->wasRecentlyCreated && $agent->wasChanged(['commission_type', 'commission_rate', 'override_rate']))) {
            app(CommissionAgreementService::class)->syncFromAgent($agent);
        }
        if ($agent->wasChanged('is_active') && !$agent->is_active) {
            app(AgentAssignmentService::class)->endAgent($agent, 'Agent switched off');
        } elseif ($agent->wasChanged('user_id') && !$agent->user_id) {
            // Only agents who sign in can hold properties and units.
            app(AgentAssignmentService::class)->endAgent($agent, 'Agent no longer signs in');
        }
    }

    public function deleted(Agent $agent): void
    {
        app(AgentAssignmentService::class)->endAgent($agent, 'Agent removed');
    }
}
