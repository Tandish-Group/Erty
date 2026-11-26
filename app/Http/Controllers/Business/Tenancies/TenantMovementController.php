<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\TenantManagement;
use App\Models\TenantMovement;
use App\Models\User;
use App\Services\Rent\TenantAccountService;
use App\Services\Tenancy\TenantMovementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Unit transfers: move a tenant to another unit in the same property.
 *
 * A transfer is requested (status pending), then approved. Approval carries it out at once when its date has
 * come, or holds it as "approved" until the nightly billing run on its date. Carrying it out ends the old
 * tenancy and opens a new one that keeps the account, agent and billing cycle (TenantMovementService::run).
 *
 * Listing and viewing need Tenants → View; requesting, approving and cancelling need Tenants → Transfers
 * (config/business_access.php). Tenancies and transfers follow the person's property assignments.
 */
class TenantMovementController extends Controller
{
    public const STATUSES = [
        'pending' => ['Waiting for approval', 'warning'],
        'approved' => ['Approved, scheduled', 'primary'],
        'completed' => ['Completed', 'success'],
        'cancelled' => ['Cancelled', 'secondary'],
    ];

    public const MOVE_TYPES = [
        'end_of_month' => 'At the start of the next billing period',
        'mid_month' => 'On a chosen date (rent is prorated)',
    ];

    public const FURNISHING = ['Unfurnished', 'Partially Furnished', 'Furnished']; // as at onboarding

    private const MOVABLE = ['Active', 'On Notice'];
    private const OPEN = ['pending', 'approved'];

    public function __construct(private TenantMovementService $service)
    {
    }

    // ─── List ──────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $filters = [
            'status' => array_key_exists($request->query('status'), self::STATUSES) ? $request->query('status') : '',
            'property' => (int) $request->query('property') ?: null,
            'q' => trim((string) $request->query('q')),
        ];
        $base = TenantMovement::query()->where('managed_by', business_owner_id())
            ->when($filters['property'], fn ($q, $id) => $q->where(fn ($w) => $w->where('old_complex_id', $id)->orWhere('new_complex_id', $id)))
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $like = '%' . mb_strtolower($filters['q']) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(ref) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(old_unit_number) LIKE ?', [$like])->orWhereRaw('LOWER(new_unit_number) LIKE ?', [$like])
                    ->orWhereHas('tenantProfile', fn ($p) => $p->whereRaw('LOWER(tenant_name) LIKE ?', [$like])->orWhereRaw('LOWER(business_name) LIKE ?', [$like])));
            });

        $counts = (clone $base)->groupBy('status')->selectRaw('status, COUNT(*) as n')->pluck('n', 'status');
        $movements = (clone $base)->when($filters['status'], fn ($q, $s) => $q->where('status', $s))
            ->with(['tenantProfile', 'oldTenancy:id,tenancy_ref,complex_name', 'newTenancy:id,tenancy_ref'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END")
            ->latest('transfer_date')->latest('id')
            ->paginate(20)->withQueryString();

        return view('pages.business.tenant.movement.index', [
            'movements' => $movements,
            'counts' => $counts,
            'filters' => $filters,
            'properties' => Complex::forBusiness()->orderBy('title')->pluck('title', 'id'),
            'dueSoon' => TenantMovement::query()->where('managed_by', business_owner_id())->where('status', 'approved')
                ->whereDate('transfer_date', '<=', now()->addDays(7))->count(),
        ]);
    }

    // ─── Request a transfer ────────────────────────────────────────────────

    /** Choose the tenancy (?q=, ?property=), or the transfer form for one (?tenancy_id=). */
    public function create(Request $request)
    {
        $tenancyId = (int) ($request->query('tenancy_id') ?: old('tenancy_id'));
        if ($tenancyId) {
            $tenancy = $this->movable()->with(['tenantProfile', 'complex:id,title'])->find($tenancyId);
            if (!$tenancy) {
                return redirect('/tenant_management/movement/create')->with('error', 'That tenancy cannot be transferred: it is not active, or not at a property you manage.');
            }
            if ($open = $this->openTransfer($tenancy)) {
                return redirect('/tenant_management/movement/' . $open->ref)->with('error', 'This tenancy already has a transfer in progress. Cancel it before requesting another.');
            }

            return view('pages.business.tenant.movement.create', $this->formData($tenancy));
        }

        $q = trim((string) $request->query('q'));
        $property = (int) $request->query('property') ?: null;
        $tenancies = $this->movable()->with(['tenantProfile', 'complex:id,title'])
            ->when($property, fn ($w) => $w->where('complex_id', $property))
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $w->where(fn ($x) => $x->whereRaw('LOWER(tenancy_ref) LIKE ?', [$like])->orWhereRaw('LOWER(unit_number) LIKE ?', [$like])
                    ->orWhereHas('tenantProfile', fn ($p) => $p->whereRaw('LOWER(tenant_name) LIKE ?', [$like])->orWhereRaw('LOWER(business_name) LIKE ?', [$like])));
            })
            ->orderBy('complex_name')->orderBy('unit_number')->paginate(25)->withQueryString();
        $open = TenantMovement::query()->whereIn('status', self::OPEN)->whereIn('old_tenancy_id', $tenancies->pluck('id'))->pluck('ref', 'old_tenancy_id');

        return view('pages.business.tenant.movement.choose', [
            'tenancies' => $tenancies,
            'open' => $open,
            'filters' => ['q' => $q, 'property' => $property],
            'properties' => Complex::forBusiness()->orderBy('title')->pluck('title', 'id'),
        ]);
    }

    /** What the transfer will do, for the form's summary (JSON). Nothing is saved. */
    public function preview(Request $request)
    {
        $tenancy = $this->movable()->with('tenantProfile')->find((int) $request->input('tenancy_id'));
        abort_unless($tenancy, 404);
        $data = $request->validate([
            'move_type' => ['required', Rule::in(array_keys(self::MOVE_TYPES))],
            'transfer_date' => 'nullable|date',
            'new_rent' => 'nullable|numeric|min:0',
            'occupied_sq_area' => 'nullable|numeric|min:0',
            'rate_per_sq_ft' => 'nullable|numeric|min:0',
            'new_deposit' => 'nullable|numeric|min:0',
        ]);

        return response()->json($this->summary($tenancy, $data));
    }

    public function initiate(Request $request)
    {
        $tenancy = $this->movable()->with('tenantProfile')->find((int) $request->input('tenancy_id'));
        if (!$tenancy) {
            return redirect('/tenant_management/movement/create')->with('error', 'That tenancy cannot be transferred: it is not active, or not at a property you manage.');
        }
        if ($open = $this->openTransfer($tenancy)) {
            return redirect('/tenant_management/movement/' . $open->ref)->with('error', 'This tenancy already has a transfer in progress.');
        }

        $commercial = (bool) $tenancy->commercial_unit_id;
        $units = collect($this->service->getVacantUnits($tenancy->complex_id, $commercial ? 'commercial' : 'residential', $tenancy->id))
            ->reject(fn ($u) => (int) $u['id'] === (int) ($tenancy->commercial_unit_id ?? $tenancy->residential_unit_id))->keyBy('id');
        $slot = $this->service->getCurrentParkingSlot($tenancy->id);
        $freeSlots = collect($this->service->getAvailableParkingSlots($tenancy->complex_id))->pluck('id')->all();
        $arrears = $this->service->checkArrears($tenancy->id);

        $data = $request->validate([
            'new_unit_id' => ['required', 'integer', Rule::in($units->keys()->all())],
            'move_type' => ['required', Rule::in(array_keys(self::MOVE_TYPES))],
            'transfer_date' => ['exclude_unless:move_type,mid_month', 'required', 'date', 'after_or_equal:today', 'before_or_equal:' . now()->addYear()->toDateString()],
            'new_rent' => [$commercial ? 'exclude' : 'required', 'numeric', 'min:0'],
            'occupied_sq_area' => [$commercial ? 'required' : 'exclude', 'numeric', 'gt:0'],
            'rate_per_sq_ft' => [$commercial ? 'required' : 'exclude', 'numeric', 'gt:0'],
            'service_charge_per_sq_ft' => [$commercial ? 'nullable' : 'exclude', 'numeric', 'min:0'],
            'new_deposit' => 'required|numeric|min:0',
            'lease_end_date' => 'nullable|date|after:today',
            'furnishing' => [$commercial ? 'exclude' : 'nullable', Rule::in(self::FURNISHING)],
            'number_of_occupants' => 'nullable|integer|min:1|max:100',
            'special_terms' => 'nullable|string|max:2000',
            'parking_action' => [$slot ? 'required' : 'exclude', Rule::in(['keep', 'release', 'replace'])],
            'new_parking_slot_id' => ['exclude_unless:parking_action,replace', 'required', 'integer', Rule::in($freeSlots)],
            'arrears_acknowledged' => [$arrears['type'] === 'warning' ? 'accepted' : 'nullable'],
            'notes' => 'nullable|string|max:1000',
        ], [
            'new_unit_id.in' => 'Choose one of the free units listed.',
            'transfer_date.after_or_equal' => 'The move date cannot be in the past.',
            'arrears_acknowledged.accepted' => 'Confirm you have seen the arrears before requesting the transfer.',
            'new_parking_slot_id.in' => 'Choose one of the free parking slots listed.',
        ], ['new_unit_id' => 'new unit', 'new_rent' => 'new rent', 'occupied_sq_area' => 'area', 'rate_per_sq_ft' => 'rent per sq ft', 'new_deposit' => 'deposit']);

        if ($arrears['type'] === 'block') {
            throw ValidationException::withMessages(['tenancy_id' => $arrears['message']]);
        }
        $unit = $units[$data['new_unit_id']];
        if ($commercial && $unit['remaining_sq_ft'] !== null && (float) $data['occupied_sq_area'] > (float) $unit['remaining_sq_ft']) {
            throw ValidationException::withMessages(['occupied_sq_area' => 'Unit ' . $unit['label'] . ' has ' . number_format((float) $unit['remaining_sq_ft']) . ' sq ft free.']);
        }

        $summary = $this->summary($tenancy, $data);
        $movement = TenantMovement::create([
            'ref' => TenantMovement::generateRef(),
            'managed_by' => business_owner_id(),
            'tenant_profile_id' => $tenancy->tenant_profile_id,
            'old_tenancy_id' => $tenancy->id,
            'old_complex_id' => $tenancy->complex_id,
            'old_unit_type' => $commercial ? 'commercial' : 'residential',
            'old_unit_id' => $tenancy->commercial_unit_id ?? $tenancy->residential_unit_id,
            'old_unit_number' => $tenancy->unit_number,
            'new_complex_id' => $tenancy->complex_id,
            'new_unit_type' => $commercial ? 'commercial' : 'residential',
            'new_unit_id' => $unit['id'],
            'new_unit_number' => $unit['label'],
            'move_type' => $data['move_type'],
            'requested_date' => today()->toDateString(),
            'transfer_date' => $summary['transfer_date'],
            'old_rent' => $tenancy->monthly_payment,
            'new_rent' => $summary['new_rent'],
            'old_deposit' => $tenancy->security_deposit,
            'new_deposit' => $data['new_deposit'],
            'deposit_adjustment' => abs($summary['deposit']['difference']),
            'deposit_adjustment_type' => $summary['deposit']['type'],
            'proration_amount' => $summary['proration']['net'] ?? 0,
            'currency' => $summary['currency'],
            'arrears_amount' => $arrears['amount'],
            'arrears_acknowledged' => $arrears['type'] === 'warning',
            'parking_action' => $slot ? $data['parking_action'] : 'none',
            'old_parking_slot_id' => $slot?->id,
            'new_parking_slot_id' => $data['new_parking_slot_id'] ?? null,
            'lease_data' => array_filter([
                'lease_end_date' => $data['lease_end_date'] ?? null,
                'special_terms' => $data['special_terms'] ?? null,
                'furnishing' => $data['furnishing'] ?? null,
                'number_of_occupants' => $data['number_of_occupants'] ?? null,
                'occupied_sq_area' => $data['occupied_sq_area'] ?? null,
                'rate_per_sq_ft' => $data['rate_per_sq_ft'] ?? null,
                'service_charge_per_sq_ft' => $data['service_charge_per_sq_ft'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'status' => 'pending',
            'initiated_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect('/tenant_management/movement/' . $movement->ref)
            ->with('success', 'Transfer ' . $movement->ref . ' requested. Approve it to ' . ($summary['transfer_date'] <= today()->toDateString() ? 'move the tenant now.' : 'schedule the move for ' . Carbon::parse($summary['transfer_date'])->format('j M Y') . '.'));
    }

    // ─── One transfer ──────────────────────────────────────────────────────

    public function show(string $ref)
    {
        $movement = $this->find($ref);
        $people = User::whereIn('id', array_filter([$movement->initiated_by, $movement->approved_by, $movement->cancelled_by]))
            ->get(['id', 'first_name', 'last_name', 'email'])->keyBy('id');
        $tenancy = $movement->oldTenancy;

        return view('pages.business.tenant.movement.show', [
            'movement' => $movement,
            'people' => $people,
            'account' => $tenancy && in_array($movement->status, self::OPEN, true) ? app(TenantAccountService::class)->summary($tenancy) : null,
            'arrears' => $tenancy && $movement->status === 'pending' ? $this->service->checkArrears($tenancy->id) : null,
            'parkingLabels' => DB::table('complex_parking_slots')->whereIn('id', array_filter([$movement->old_parking_slot_id, $movement->new_parking_slot_id]))->pluck('parking_number', 'id'),
        ]);
    }

    public function approve(string $ref)
    {
        $movement = $this->find($ref);
        if ($movement->status !== 'pending') {
            return back()->with('error', 'Only a transfer waiting for approval can be approved.');
        }

        try {
            $done = $this->service->executeMovement($movement);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            Log::error("Transfer {$ref} could not be approved: " . $e->getMessage());

            return back()->with('error', 'The transfer could not be carried out. Nothing was changed; check the tenancy and the new unit, then try again.');
        }

        if ($done->status === 'approved') {
            return back()->with('success', 'Transfer approved. The tenant moves on ' . $done->transfer_date->format('j M Y') . ' (the nightly billing run carries it out).');
        }
        $this->sendConfirmation($done);

        return back()->with('success', 'Transfer completed. The tenant is now in unit ' . $done->new_unit_number . ' under tenancy ' . ($done->newTenancy->tenancy_ref ?? '') . '.');
    }

    public function cancel(Request $request, string $ref)
    {
        $data = $request->validate(['reason' => 'required|string|max:500'], [], ['reason' => 'reason']);
        $movement = $this->find($ref);
        if (!in_array($movement->status, self::OPEN, true)) {
            return back()->with('error', 'This transfer is already ' . $movement->status . '.');
        }
        $movement->update(['status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancellation_reason' => $data['reason']]);

        return back()->with('success', 'Transfer cancelled. The tenant stays in unit ' . $movement->old_unit_number . '.');
    }

    // ─── Helpers ───────────────────────────────────────────────────────────

    /** Tenancies of this business that can move (scoped to the person's properties by the model). */
    private function movable()
    {
        return TenantManagement::forBusiness()->whereIn('status', self::MOVABLE);
    }

    private function openTransfer(TenantManagement $tenancy): ?TenantMovement
    {
        return TenantMovement::query()->where('old_tenancy_id', $tenancy->id)->whereIn('status', self::OPEN)->first();
    }

    private function find(string $ref): TenantMovement
    {
        return TenantMovement::where('ref', $ref)->where('managed_by', business_owner_id())
            ->with(['tenantProfile', 'oldTenancy.complex:id,title', 'newTenancy'])->firstOrFail();
    }

    /** Everything the transfer form shows for one tenancy. */
    private function formData(TenantManagement $tenancy): array
    {
        $commercial = (bool) $tenancy->commercial_unit_id;
        $currentUnit = (int) ($tenancy->commercial_unit_id ?? $tenancy->residential_unit_id);
        $period = $this->service->periodContaining($tenancy, today());

        return [
            'tenancy' => $tenancy,
            'commercial' => $commercial,
            'units' => collect($this->service->getVacantUnits($tenancy->complex_id, $commercial ? 'commercial' : 'residential', $tenancy->id))
                ->reject(fn ($u) => (int) $u['id'] === $currentUnit)->values(),
            'slot' => $this->service->getCurrentParkingSlot($tenancy->id),
            'freeSlots' => $this->service->getAvailableParkingSlots($tenancy->complex_id),
            'arrears' => $this->service->checkArrears($tenancy->id),
            'account' => app(TenantAccountService::class)->summary($tenancy),
            'nextPeriod' => $period['end']->copy()->addDay(),
            'currency' => $tenancy->currency ?: default_currency(),
            'lease' => \App\Models\TenantLease::where('tenant_management_id', $tenancy->id)->where('status', 'active')->latest()->first(),
        ];
    }

    /**
     * The money and dates of a transfer, worked out by the same rules that carry it out: when it happens,
     * the new rent, the mid-period adjustment and the deposit change.
     */
    private function summary(TenantManagement $tenancy, array $data): array
    {
        $moveType = $data['move_type'] ?? 'end_of_month';
        $date = $this->service->calculateTransferDates($moveType, $data['transfer_date'] ?? null, $tenancy)['transfer_date'];
        $newRent = $tenancy->commercial_unit_id && isset($data['occupied_sq_area'], $data['rate_per_sq_ft'])
            ? round((float) $data['occupied_sq_area'] * (float) $data['rate_per_sq_ft'], 2)
            : (float) ($data['new_rent'] ?? $tenancy->monthly_payment);
        $period = $this->service->periodContaining($tenancy, Carbon::parse($date));
        $midPeriod = Carbon::parse($date)->gt($period['start']);
        $proration = $midPeriod ? $this->service->calculateProration((float) $tenancy->monthly_payment, $newRent, $date, $tenancy) : null;
        $oldDeposit = (float) $tenancy->security_deposit;
        $newDeposit = (float) ($data['new_deposit'] ?? $oldDeposit);
        $difference = round($newDeposit - $oldDeposit, 2);

        return [
            'transfer_date' => $date,
            'transfer_date_label' => Carbon::parse($date)->format('j M Y'),
            'immediate' => $date <= today()->toDateString(),
            'currency' => $tenancy->currency ?: default_currency(),
            'old_rent' => (float) $tenancy->monthly_payment,
            'new_rent' => $newRent,
            'proration' => $proration,
            'deposit' => [
                'old' => $oldDeposit, 'new' => $newDeposit, 'difference' => $difference,
                'held' => app(TenantAccountService::class)->depositHeld($tenancy),
                'type' => $difference > 0 ? 'top_up' : ($difference < 0 ? 'refund' : 'none'),
            ],
        ];
    }

    private function sendConfirmation(TenantMovement $movement): void
    {
        $email = $movement->tenantProfile?->email;
        if (!$email) {
            return;
        }
        try {
            Mail::send('emails.tenant.movement_confirmed', [
                'movement' => $movement,
                'tenantName' => $movement->tenantProfile->display_name ?: 'Tenant',
                'currency' => $movement->currency,
            ], fn ($mail) => $mail->to($email)->subject('Your unit transfer is complete (' . $movement->ref . ')'));
        } catch (\Throwable $e) {
            Log::warning("Transfer confirmation e-mail for {$movement->ref} was not sent: " . $e->getMessage());
        }
    }
}
