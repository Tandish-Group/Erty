<?php

namespace App\Http\Controllers\Business\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CommercialUnit;
use App\Models\Complex;
use App\Models\ComplexResidentialUnit;
use App\Models\MaintenanceRequest;
use App\Models\TenantManagement;
use App\Models\User;
use App\Services\Properties\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Maintenance requests. Who can do what (existing permissions, no new ones):
 * - see requests: anyone who can view properties (team members limited to assigned properties see those,
 *   plus any request assigned to them);
 * - log a request: property managers (edit properties) and agents (create tenants);
 * - assign, close, cancel, reopen: whoever can edit properties;
 * - start, add notes, complete: the assignee, or whoever can edit properties.
 */
class MaintenanceRequestController extends Controller
{
    public function __construct(private MaintenanceService $service) {}

    public function index(Request $request)
    {
        $filter = $request->query('show', 'open');
        $query = MaintenanceRequest::with(['complex:id,title,slug', 'residentialUnit:id,unit_number', 'commercialUnit:id,unit_number', 'tenancy.tenantProfile', 'assignee:id,first_name,last_name'])
            ->where('managed_by', business_owner_id());
        match ($filter) {
            'mine' => $query->where('assigned_user_id', auth()->id())->whereIn('status', MaintenanceRequest::OPEN_STATUSES),
            'completed' => $query->where('status', 'completed'),
            'closed' => $query->whereIn('status', ['closed', 'cancelled']),
            'all' => null,
            default => $query->whereIn('status', MaintenanceRequest::OPEN_STATUSES),
        };
        if ($request->filled('complex_id')) {
            $query->where('complex_id', (int) $request->query('complex_id'));
        }
        $requests = $query->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->latest('reported_at')->paginate(25)->withQueryString();

        $base = MaintenanceRequest::where('managed_by', business_owner_id());
        $counts = [
            'open' => (clone $base)->whereIn('status', MaintenanceRequest::OPEN_STATUSES)->count(),
            'mine' => (clone $base)->whereIn('status', MaintenanceRequest::OPEN_STATUSES)->where('assigned_user_id', auth()->id())->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'closed' => (clone $base)->whereIn('status', ['closed', 'cancelled'])->count(),
        ];
        $complexes = Complex::forBusiness()->orderBy('title')->get(['id', 'title']);
        return view('pages.business.maintenance.index', compact('requests', 'filter', 'counts', 'complexes') + ['canLog' => $this->canLog(), 'complexFilter' => $request->query('complex_id')]);
    }

    public function create(Request $request)
    {
        abort_unless($this->canLog(), 403);
        $tenancy = $request->filled('tenancy_id') ? TenantManagement::forBusiness()->find($request->query('tenancy_id')) : null;
        $complexes = Complex::forBusiness()->orderBy('title')->get(['id', 'title']);
        $selected = (int) ($tenancy?->complex_id ?? $request->query('complex_id') ?? ($complexes->count() === 1 ? $complexes->first()->id : 0));
        return view('pages.business.maintenance.create', [
            'complexes' => $complexes,
            'selectedComplex' => $selected,
            'tenancy' => $tenancy,
            'units' => $selected ? $this->unitOptions($selected) : [],
            'assignees' => $this->assignees(),
            'canAssign' => $this->canManage(),
        ]);
    }

    /** Units of a property with who lives there (for the request form). */
    public function units(int $complex)
    {
        Complex::forBusiness()->findOrFail($complex);
        return response()->json(['status' => 200, 'units' => $this->unitOptions($complex)]);
    }

    public function store(Request $request)
    {
        abort_unless($this->canLog(), 403);
        $data = $request->validate([
            'complex_id' => ['required', 'integer', Rule::exists('complexes', 'id')->where('user_id', business_owner_id())],
            'unit' => ['nullable', 'string', 'max:40'],
            'tenancy_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['required', Rule::in(array_keys(MaintenanceRequest::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(MaintenanceRequest::PRIORITIES))],
            'reported_by' => ['nullable', 'string', 'max:120'],
            'reported_via' => ['nullable', Rule::in(['phone', 'email', 'visit', 'inspection', 'other'])],
            'reported_at' => ['nullable', 'date', 'before_or_equal:now'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);
        // Only properties, units and tenancies the member can see (the exists rule above is not limited).
        app(\App\Services\Access\PropertyAccess::class)->assertComplex((int) $data['complex_id']);
        if (!empty($data['tenancy_id'])) {
            abort_unless(TenantManagement::where('complex_id', $data['complex_id'])->whereKey((int) $data['tenancy_id'])->exists(), 422, 'That tenancy is not at this property.');
        }
        // unit is "r:<id>" (flat) or "c:<id>" (commercial space)
        if (!empty($data['unit'])) {
            [$kind, $id] = array_pad(explode(':', $data['unit'], 2), 2, null);
            $model = $kind === 'c' ? CommercialUnit::class : ComplexResidentialUnit::class;
            abort_unless($model::where('complex_id', $data['complex_id'])->whereKey((int) $id)->exists(), 422);
            $data[$kind === 'c' ? 'commercial_unit_id' : 'residential_unit_id'] = (int) $id;
            // The tenancy currently in that unit, unless one was given.
            $data['tenancy_id'] = $data['tenancy_id'] ?? TenantManagement::forBusiness()->whereIn('status', ['Active', 'On Notice'])
                ->where($kind === 'c' ? 'commercial_unit_id' : 'residential_unit_id', (int) $id)->latest('id')->value('id');
        }
        if (!empty($data['assigned_user_id'])) {
            abort_unless($this->canManage() && $this->assignees()->contains('id', (int) $data['assigned_user_id']), 403);
        }
        $maintenance = $this->service->log($data, business_owner_id());
        return redirect()->route('maintenance.show', $maintenance->reference)->with('success', 'Request ' . $maintenance->reference . ' logged.');
    }

    public function show(string $reference)
    {
        $maintenance = $this->find($reference);
        $maintenance->load(['notes.user:id,first_name,last_name', 'tenancy.tenantProfile', 'expense', 'invoice', 'logger:id,first_name,last_name']);
        $isAssignee = $maintenance->assigned_user_id === auth()->id();
        return view('pages.business.maintenance.show', [
            'maintenance' => $maintenance,
            'assignees' => $this->assignees(),
            'canManage' => $this->canManage(),
            'canWork' => $this->canManage() || $isAssignee,
            'isAssignee' => $isAssignee,
        ]);
    }

    public function act(Request $request, string $reference)
    {
        $maintenance = $this->find($reference);
        $action = $request->validate(['action' => ['required', Rule::in(['assign', 'start', 'note', 'complete', 'close', 'cancel', 'reopen'])]])['action'];
        $manager = in_array($action, ['assign', 'close', 'cancel', 'reopen'], true);
        abort_unless($manager ? $this->canManage() : ($this->canManage() || $maintenance->assigned_user_id === auth()->id()), 403);

        $message = match ($action) {
            'assign' => (function () use ($request, $maintenance) {
                $data = $request->validate(['assigned_user_id' => ['required', 'integer'], 'note' => ['nullable', 'string', 'max:1000']]);
                abort_unless($this->assignees()->contains('id', (int) $data['assigned_user_id']), 422);
                $this->service->assign($maintenance, (int) $data['assigned_user_id'], $data['note'] ?? null);
                return 'Assigned.';
            })(),
            'start' => (function () use ($request, $maintenance) {
                $this->service->start($maintenance, $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null);
                return 'Marked as in progress.';
            })(),
            'note' => (function () use ($request, $maintenance) {
                $this->service->addNote($maintenance, $request->validate(['note' => ['required', 'string', 'max:2000']])['note']);
                return 'Note added.';
            })(),
            'complete' => (function () use ($request, $maintenance) {
                $data = $request->validate(['resolution' => ['required', 'string', 'max:2000'], 'cost' => ['nullable', 'numeric', 'min:0'], 'cost_borne_by' => ['nullable', Rule::in(['landlord', 'tenant'])]]);
                $this->service->complete($maintenance, $data['resolution'], isset($data['cost']) ? (float) $data['cost'] : null, $data['cost_borne_by'] ?? null);
                return 'Marked as completed. A property manager can now close it.';
            })(),
            'close' => (function () use ($request, $maintenance) {
                if ($maintenance->cost_borne_by === 'tenant' && $maintenance->cost > 0) {
                    abort_unless(auth()->user()->hasBusinessPermission('tenant_management', 'billing'), 403, 'Charging the tenant needs billing permission.');
                }
                $this->service->close($maintenance, $request->validate(['note' => ['nullable', 'string', 'max:1000']])['note'] ?? null);
                return 'Closed.';
            })(),
            'cancel' => (function () use ($request, $maintenance) {
                $this->service->cancel($maintenance, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);
                return 'Cancelled.';
            })(),
            'reopen' => (function () use ($request, $maintenance) {
                $this->service->reopen($maintenance, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason']);
                return 'Reopened.';
            })(),
        };
        return redirect()->route('maintenance.show', $maintenance->reference)->with('success', $message);
    }

    private function find(string $reference): MaintenanceRequest
    {
        return MaintenanceRequest::with(['complex', 'residentialUnit', 'commercialUnit', 'tenancy', 'assignee'])
            ->where('managed_by', business_owner_id())->where('reference', $reference)->firstOrFail();
    }

    private function canManage(): bool
    {
        $user = auth()->user();
        return $user->hasBusinessPermission('maintenance_management', 'manage');
    }

    private function canLog(): bool
    {
        $user = auth()->user();
        return $this->canManage() || $user->hasBusinessPermission('maintenance_management', 'log');
    }

    /** The owner and active team members, agents first (only people who can sign in can be assigned). */
    private function assignees()
    {
        $owner = business_owner_id();
        $agentUserIds = Agent::where('created_by', $owner)->whereNotNull('user_id')->pluck('user_id')->all();
        return User::where(fn ($q) => $q->where('id', $owner)->orWhere('main_account_user_id', $owner))
            ->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'email'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => trim($u->first_name . ' ' . $u->last_name) ?: $u->email, 'is_agent' => in_array($u->id, $agentUserIds, true)])
            ->sortByDesc('is_agent')->values();
    }

    private function unitOptions(int $complexId): array
    {
        $tenants = TenantManagement::with('tenantProfile')->where('complex_id', $complexId)->whereIn('status', ['Active', 'On Notice'])->get();
        $who = fn ($col, $id) => optional($tenants->firstWhere($col, $id))->tenantProfile;
        $res = ComplexResidentialUnit::where('complex_id', $complexId)->orderBy('unit_number')->get(['id', 'unit_number'])
            ->map(fn ($u) => ['value' => 'r:' . $u->id, 'label' => $u->unit_number . (($p = $who('residential_unit_id', $u->id)) ? ' · ' . ($p->tenant_type === 'Business' ? $p->business_name : $p->tenant_name) : ' · vacant')]);
        $com = CommercialUnit::where('complex_id', $complexId)->orderBy('unit_number')->get(['id', 'unit_number'])
            ->map(fn ($u) => ['value' => 'c:' . $u->id, 'label' => $u->unit_number . ' (commercial)' . (($p = $who('commercial_unit_id', $u->id)) ? ' · ' . ($p->tenant_type === 'Business' ? $p->business_name : $p->tenant_name) : '')]);
        return $res->concat($com)->values()->all();
    }
}
