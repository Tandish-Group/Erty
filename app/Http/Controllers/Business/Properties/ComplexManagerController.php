<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Services\Agents\PropertyAssignmentService;
use Illuminate\Http\Request;

/**
 * Who manages a property: its team members (complex_management.assign), whether the company or a primary
 * manager is responsible, the agents on its units, and the history of those assignments.
 */
class ComplexManagerController extends Controller
{
    public function __construct(private PropertyAssignmentService $assignments)
    {
    }

    public function update(Request $request, string $slug)
    {
        $data = $request->validate(['user_ids' => 'nullable|array', 'user_ids.*' => 'integer']);
        $complex = Complex::forBusiness()->where('slug', $slug)->firstOrFail();

        $this->assignments->setForComplex($complex, $data['user_ids'] ?? []);
        $managers = $complex->managers()->orderBy('first_name')->get(['users.id', 'first_name', 'last_name']);

        return response()->json([
            'success' => true,
            'message' => $managers->isEmpty() ? 'No team members are assigned to this property now.' : 'Property managers updated.',
            'managers' => $managers->map(fn ($m) => ['id' => $m->id, 'name' => trim($m->first_name . ' ' . $m->last_name)]),
        ]);
    }

    /** Assign or clear the agent on selected units (administrators, or the property's lead agent). */
    public function unitAgents(Request $request, string $slug, \App\Services\Agents\AgentAssignmentService $agents)
    {
        $data = $request->validate([
            'kind' => 'required|in:residential,commercial',
            'unit_ids' => 'required|array|min:1|max:500',
            'unit_ids.*' => 'integer',
            'agent_id' => 'nullable|integer',
        ]);
        $complex = Complex::forBusiness()->where('slug', $slug)->firstOrFail();
        $changed = $agents->assignUnits(auth()->user(), $complex, $data['kind'], $data['unit_ids'], $data['agent_id'] ?? null);

        return response()->json([
            'success' => true,
            'changed' => $changed,
            'message' => $data['agent_id'] ?? null
                ? "Agent assigned to {$changed} " . \Illuminate\Support\Str::plural('unit', $changed) . '.'
                : "Unit agent removed from {$changed} " . \Illuminate\Support\Str::plural('unit', $changed) . '; the property agent applies.',
        ]);
    }

    /** Managed by the company, or by one primary property manager (administrators). */
    public function management(Request $request, string $slug, \App\Services\Agents\AgentAssignmentService $agents)
    {
        $data = $request->validate([
            'management_type' => 'required|in:company,individual',
            'agent_id' => 'nullable|required_if:management_type,individual|integer',
            'reason' => 'nullable|string|max:255',
        ], ['agent_id.required_if' => 'Choose the primary property manager.']);
        $complex = Complex::forBusiness()->where('slug', $slug)->firstOrFail();
        $agents->setManagement(auth()->user(), $complex, $data['management_type'], isset($data['agent_id']) ? (int) $data['agent_id'] : null, $data['reason'] ?? null);
        $complex->refresh()->load('assignedAgent');

        return response()->json([
            'success' => true,
            'message' => $complex->isCompanyManaged()
                ? 'The property is now managed by the company. Staff work on it according to their roles.'
                : $complex->assignedAgent->full_name . ' is now the primary property manager.',
        ]);
    }

    /** Assignment history of one unit (or of the property itself, kind "property"). */
    public function history(string $slug, string $kind, ?int $unit = null)
    {
        abort_unless(in_array($kind, ['residential', 'commercial', 'property'], true), 404);
        $complex = Complex::forBusiness()->where('slug', $slug)->firstOrFail();
        if ($kind !== 'property') {
            abort_unless(app(\App\Services\Access\PropertyAccess::class)->allowsUnit($complex->id, $kind, $unit), 404);
        }
        $scope = $kind === 'property' ? \App\Models\AssignmentHistory::PROPERTY : \App\Models\AssignmentHistory::scopeForUnitKind($kind);
        $rows = app(\App\Services\Agents\AssignmentHistoryService::class)->history($complex->id, $scope, $kind === 'property' ? null : $unit);
        $name = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : null;

        return response()->json(['success' => true, 'history' => $rows->map(fn ($r) => [
            'assignee' => $r->assignee_type === 'company' ? 'The company' : ($name($r->agent) ?? 'Removed agent'),
            'role' => ['company' => 'Company', 'primary' => 'Primary manager', 'sub_agent' => 'Sub-agent'][$r->role] ?? $r->role,
            'from' => $r->starts_at?->format('j M Y, H:i'), 'to' => $r->ends_at?->format('j M Y, H:i'),
            'by' => $name($r->assignedBy), 'ended_by' => $name($r->endedBy), 'reason' => $r->reason,
        ])->values()]);
    }
}
