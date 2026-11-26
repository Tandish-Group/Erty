<?php

namespace App\Services\Agents;

use App\Services\Access\PropertyAccess;
use App\Services\NotificationCenter;
use App\Services\Properties\ComplexInventoryService;

use App\Models\Agent;
use App\Models\CommercialUnit;
use App\Models\Complex;
use App\Models\ComplexResidentialUnit;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Who manages a property and its units.
 *
 * A property is managed by the company (management_type "company": the team works it according to their
 * roles) or by an individual primary manager (complexes.assigned_agent_id). Units can be delegated to
 * agents (sub-agents). Only agents who sign in can be assigned.
 * - Owners and admins (complex_management.assign) set how a property is managed and assign any unit.
 * - The primary manager of an individually managed property assigns its units to themselves or to
 *   agents who report to them.
 * - On a company-managed property, anyone who can edit properties and works on it assigns its units.
 * Every change is recorded in assignment_history (AssignmentHistoryService) and the property audit;
 * active tenancies on a unit follow its agent.
 */
class AgentAssignmentService
{
    /** Active agents of the business that have a sign-in. */
    public function signInAgents(?int $ownerId = null)
    {
        return Agent::where('created_by', $ownerId ?? business_owner_id())->whereNotNull('user_id')
            ->where('is_active', true)->orderBy('first_name')->orderBy('last_name')->get();
    }

    public function isAssignable(?int $agentId, ?int $ownerId = null): bool
    {
        return $agentId === null || Agent::where('created_by', $ownerId ?? business_owner_id())->whereNotNull('user_id')
            ->where('is_active', true)->whereKey($agentId)->exists();
    }

    public function assertAssignable(?int $agentId, string $field = 'assigned_agent_id'): void
    {
        if (!$this->isAssignable($agentId)) {
            throw ValidationException::withMessages([$field => 'Choose an active agent who signs in. External agents and team members without an agent profile cannot be assigned.']);
        }
    }

    public function isAdmin(User $user): bool
    {
        return $user->isBusinessOwner() || $user->hasBusinessPermission('complex_management', 'assign');
    }

    /** The signed-in person is this property's lead agent. */
    public function isLeadAgent(User $user, Complex $complex): bool
    {
        $agent = AgentProfileService::current($user);
        return $agent && $complex->assigned_agent_id && (int) $agent->id === (int) $complex->assigned_agent_id;
    }

    /** A company-managed property's units can be assigned by anyone who edits properties and works on it. */
    public function isCompanyPropertyEditor(User $user, Complex $complex): bool
    {
        return $complex->isCompanyManaged()
            && $user->hasBusinessPermission('complex_management', 'edit')
            && app(PropertyAccess::class)->allows($complex->id);
    }

    public function canAssignUnits(User $user, Complex $complex): bool
    {
        return $this->isAdmin($user) || $this->isLeadAgent($user, $complex) || $this->isCompanyPropertyEditor($user, $complex);
    }

    /** Agents this person may put on units of the property. */
    public function assignableForUnits(User $user, Complex $complex): Collection
    {
        if ($this->isAdmin($user) || $this->isCompanyPropertyEditor($user, $complex)) {
            return $this->signInAgents();
        }
        if (!$this->isLeadAgent($user, $complex)) {
            return collect();
        }
        $lead = AgentProfileService::current($user);
        return $this->signInAgents()->filter(fn (Agent $a) => $a->id === $lead->id || (int) $a->reports_to_agent_id === (int) $lead->id)->values();
    }

    /**
     * Assign (or clear) the agent on a set of units of one kind.
     * @return int number of units changed
     */
    public function assignUnits(User $user, Complex $complex, string $kind, array $unitIds, ?int $agentId): int
    {
        if (!$this->canAssignUnits($user, $complex)) {
            abort(403, $complex->isCompanyManaged()
                ? 'You need permission to edit properties to assign agents to this property\'s units.'
                : 'Only the property\'s primary manager or an administrator can assign agents to its units.');
        }
        if ($agentId !== null && !$this->assignableForUnits($user, $complex)->contains('id', $agentId)) {
            throw ValidationException::withMessages(['agent_id' => $this->isAdmin($user)
                ? 'Choose an active agent who signs in.'
                : 'You can assign units to yourself or to agents who report to you.']);
        }
        $model = $kind === 'commercial' ? CommercialUnit::class : ComplexResidentialUnit::class;
        $units = $model::where('complex_id', $complex->id)->whereIn('id', array_map('intval', $unitIds))->get();
        if ($units->count() !== count(array_unique($unitIds))) {
            throw ValidationException::withMessages(['unit_ids' => 'Choose units of this property.']);
        }

        $inventory = app(ComplexInventoryService::class);
        $history = app(AssignmentHistoryService::class);
        $changed = 0;
        $moved = collect();
        $previous = [];
        DB::transaction(function () use ($units, $agentId, $complex, $inventory, $history, $kind, &$changed, $moved, &$previous) {
            foreach ($units as $unit) {
                if ((int) $unit->assigned_agent_id === (int) $agentId) continue;
                $previous[] = $unit->assigned_agent_id;
                $moved->push($unit);
                $inventory->audit($complex, $kind . '_unit_agent', ($unit->unit_number ?? $unit->id) . ': ' . ($unit->assigned_agent_id ?: 'none'), ($unit->unit_number ?? $unit->id) . ': ' . ($agentId ?: 'none'));
                $unit->forceFill(['assigned_agent_id' => $agentId])->save();
                $history->recordUnit($complex, $kind, (int) $unit->id, $agentId);
                $this->syncTenancyAgents($complex, $kind, (int) $unit->id, $agentId);
                $changed++;
            }
        });
        app(PropertyAccess::class)->forget();
        if ($changed) {
            app(NotificationCenter::class)->unitsAssigned($complex, $kind, $moved, $agentId, $previous);
        }
        return $changed;
    }

    /**
     * Manage the property as the company (no primary) or through one primary manager. Unit assignments
     * stay as they are. Earned commission is never moved: it is attributed when rent is collected.
     */
    public function setManagement(User $user, Complex $complex, string $type, ?int $agentId, ?string $reason = null): void
    {
        abort_unless($this->isAdmin($user), 403, 'Only an administrator can change who manages a property.');
        if (!in_array($type, [Complex::MANAGED_BY_COMPANY, Complex::MANAGED_BY_INDIVIDUAL], true)) {
            throw ValidationException::withMessages(['management_type' => 'Choose company or individual management.']);
        }
        if ($type === Complex::MANAGED_BY_INDIVIDUAL) {
            if (!$agentId) {
                throw ValidationException::withMessages(['agent_id' => 'Choose the primary property manager.']);
            }
            $this->assertAssignable($agentId, 'agent_id');
        } else {
            $agentId = null;
        }

        $oldAgentId = $complex->isCompanyManaged() ? null : ($complex->assigned_agent_id ? (int) $complex->assigned_agent_id : null);
        DB::transaction(function () use ($complex, $type, $agentId, $reason) {
            $before = $complex->isCompanyManaged() ? 'company' : 'agent ' . $complex->assigned_agent_id;
            $complex->forceFill(['management_type' => $type, 'assigned_agent_id' => $agentId])->save();
            app(AssignmentHistoryService::class)->recordProperty($complex, $agentId, $reason);
            app(ComplexInventoryService::class)->audit($complex, 'management', $before, $agentId ? 'agent ' . $agentId : 'company');
        });
        app(PropertyAccess::class)->forget();
        app(NotificationCenter::class)->managementChanged($complex, $oldAgentId, $agentId);
    }

    /**
     * Someone stops working as an agent (left the team, or the agent profile was switched off): their units
     * become unassigned and properties they managed alone become company-managed. Records stay.
     */
    public function endAgent(Agent $agent, string $reason): void
    {
        $access = app(PropertyAccess::class);
        $access->unrestricted(function () use ($agent, $reason) {
            DB::transaction(function () use ($agent, $reason) {
                $history = app(AssignmentHistoryService::class);
                foreach (['residential' => ComplexResidentialUnit::class, 'commercial' => CommercialUnit::class] as $kind => $model) {
                    foreach ($model::where('assigned_agent_id', $agent->id)->get() as $unit) {
                        $complex = Complex::find($unit->complex_id);
                        $unit->forceFill(['assigned_agent_id' => null])->save();
                        if ($complex) {
                            $history->recordUnit($complex, $kind, (int) $unit->id, null, $reason);
                            $this->syncTenancyAgents($complex, $kind, (int) $unit->id, null);
                        }
                    }
                }
                foreach (Complex::where('assigned_agent_id', $agent->id)->get() as $complex) {
                    $complex->forceFill(['management_type' => Complex::MANAGED_BY_COMPANY, 'assigned_agent_id' => null])->save();
                    $history->recordProperty($complex, null, $reason);
                    app(ComplexInventoryService::class)->audit($complex, 'management', 'agent ' . $agent->id, 'company');
                }
            });
        });
        $access->forget();
    }

    /**
     * Tenancies living in a unit follow the unit's agent (for the CRM and as the commission fallback);
     * with nobody on the unit they go back to the property's primary manager, if any.
     */
    private function syncTenancyAgents(Complex $complex, string $kind, int $unitId, ?int $agentId): void
    {
        $column = $kind === 'commercial' ? 'commercial_unit_id' : 'residential_unit_id';
        $target = $agentId ?: ($complex->isCompanyManaged() ? null : $complex->assigned_agent_id);
        app(PropertyAccess::class)->unrestricted(fn () => \App\Models\TenantManagement::where($column, $unitId)
            ->whereIn('status', ['Active', 'Pending'])->update(['assigned_agent_id' => $target]));
    }

    /** The agent a tenancy at this unit goes to: the unit's agent, otherwise the property's agent. */
    public function defaultAgentFor(Complex $complex, ComplexResidentialUnit|CommercialUnit|null $unit = null): ?Agent
    {
        $agentId = $unit?->assigned_agent_id ?: $complex->assigned_agent_id;
        return $agentId ? Agent::where('created_by', $complex->user_id)->find($agentId) : null;
    }
}
