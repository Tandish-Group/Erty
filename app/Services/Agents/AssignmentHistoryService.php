<?php

namespace App\Services\Agents;

use App\Services\Access\PropertyAccess;

use App\Models\AssignmentHistory;
use App\Models\Complex;
use Carbon\Carbon;

/**
 * Periods of responsibility for properties and units (assignment_history). Each change closes the
 * current row and opens a new one; rows are never edited or deleted. Dated lookups answer "who was
 * responsible when this rent was collected" for commission.
 */
class AssignmentHistoryService
{
    public function __construct(private PropertyAccess $access) {}

    /** Record who manages the property from now on: the company, or a primary agent. */
    public function recordProperty(Complex $complex, ?int $agentId, ?string $reason = null): void
    {
        $this->access->unrestricted(function () use ($complex, $agentId, $reason) {
            $current = $this->current($complex->id, AssignmentHistory::PROPERTY, null);
            if ($current && (int) $current->agent_id === (int) $agentId && $current->assignee_type === ($agentId ? 'agent' : 'company')) {
                return;
            }
            $this->close($current, $reason);
            AssignmentHistory::create([
                'complex_id' => $complex->id, 'scope' => AssignmentHistory::PROPERTY, 'unit_id' => null,
                'assignee_type' => $agentId ? 'agent' : 'company', 'agent_id' => $agentId, 'role' => $agentId ? 'primary' : 'company',
                'starts_at' => now(), 'assigned_by' => auth()->id(), 'reason' => $reason,
            ]);
        });
    }

    /** Record a unit's agent from now on (null = unassigned). */
    public function recordUnit(Complex $complex, string $kind, int $unitId, ?int $agentId, ?string $reason = null): void
    {
        $scope = AssignmentHistory::scopeForUnitKind($kind);
        $this->access->unrestricted(function () use ($complex, $scope, $unitId, $agentId, $reason) {
            $current = $this->current($complex->id, $scope, $unitId);
            if ($current && (int) $current->agent_id === (int) $agentId) {
                return;
            }
            $this->close($current, $reason);
            if ($agentId) {
                AssignmentHistory::create([
                    'complex_id' => $complex->id, 'scope' => $scope, 'unit_id' => $unitId, 'assignee_type' => 'agent', 'agent_id' => $agentId,
                    'role' => $complex->management_type === Complex::MANAGED_BY_INDIVIDUAL && (int) $complex->assigned_agent_id === (int) $agentId ? 'primary' : 'sub_agent',
                    'starts_at' => now(), 'assigned_by' => auth()->id(), 'reason' => $reason,
                ]);
            }
        });
    }

    /** The unit's agent on a date (null = nobody), from history; falls back to the earliest record for dates before history began. */
    public function unitAgentOn(string $kind, int $unitId, $date): ?int
    {
        $row = $this->on(AssignmentHistory::scopeForUnitKind($kind), $unitId, null, $date, true);
        return $row?->agent_id ? (int) $row->agent_id : null;
    }

    /** The property's primary agent on a date (null = company-managed). */
    public function primaryOn(int $complexId, $date): ?int
    {
        $row = $this->on(AssignmentHistory::PROPERTY, null, $complexId, $date, false);
        if (!$row) {
            // No history for this property yet: use its current set-up.
            $complex = $this->access->unrestricted(fn () => Complex::find($complexId, ['id', 'management_type', 'assigned_agent_id']));
            return $complex && !$complex->isCompanyManaged() ? (int) $complex->assigned_agent_id : null;
        }
        return $row->assignee_type === 'agent' ? (int) $row->agent_id : null;
    }

    public function history(int $complexId, string $scope, ?int $unitId = null)
    {
        return $this->access->unrestricted(fn () => AssignmentHistory::with(['agent:id,first_name,last_name', 'assignedBy:id,first_name,last_name', 'endedBy:id,first_name,last_name'])
            ->where('complex_id', $complexId)->where('scope', $scope)
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->orderByDesc('starts_at')->orderByDesc('id')->get());
    }

    private function current(int $complexId, string $scope, ?int $unitId): ?AssignmentHistory
    {
        return AssignmentHistory::where('complex_id', $complexId)->where('scope', $scope)
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId), fn ($q) => $q->whereNull('unit_id'))
            ->whereNull('ends_at')->latest('starts_at')->latest('id')->first();
    }

    private function close(?AssignmentHistory $row, ?string $reason): void
    {
        $row?->forceFill(['ends_at' => now(), 'ended_by' => auth()->id(), 'reason' => $row->reason ?? $reason])->save();
    }

    /**
     * The row in force at the end of the given day (today: right now). Days before the first row use
     * the first row: history started when this feature was installed, so that is the best evidence.
     */
    private function on(string $scope, ?int $unitId, ?int $complexId, $date, bool $unit): ?AssignmentHistory
    {
        $day = Carbon::parse($date);
        $at = $day->isToday() ? now() : $day->copy()->endOfDay();

        return $this->access->unrestricted(function () use ($scope, $unitId, $complexId, $at) {
            $base = fn () => AssignmentHistory::where('scope', $scope)
                ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
                ->when($complexId !== null, fn ($q) => $q->where('complex_id', $complexId));

            $inForce = $base()->where('starts_at', '<=', $at)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $at))
                ->latest('starts_at')->latest('id')->first();
            if ($inForce) {
                return $inForce;
            }
            // Ended before this date with nothing after: the unit was unassigned then.
            if ($base()->where('starts_at', '<=', $at)->exists()) {
                return null;
            }
            return $base()->oldest('starts_at')->oldest('id')->first();
        });
    }
}
