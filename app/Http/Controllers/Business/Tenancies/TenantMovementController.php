<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Models\TenantLease;
use App\Models\TenantManagement;
use App\Models\TenantMovement;
use App\Services\Tenancy\TenantMovementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TenantMovementController extends Controller
{
    protected TenantMovementService $service;

    public function __construct(TenantMovementService $service)
    {
        $this->middleware('auth');
        $this->service = $service;
    }

    // ─── Pages ────────────────────────────────────────────────────────────

    /**
     * Movement overview dashboard.
     */
    public function index()
    {
        return view('pages.business.tenant.movement.index');
    }

    /**
     * Initiate-transfer create page.
     * Accepts optional ?tenancy_id=X to pre-fill from an existing tenancy.
     */
    public function create(Request $request)
    {
        $pageData = [];

        if ($request->filled('tenancy_id')) {
            $tenancy = TenantManagement::where('id', $request->tenancy_id)
                ->where('managed_by', business_owner_id())
                ->with(['tenantProfile', 'complex'])
                ->first();

            if ($tenancy) {
                $arrears   = $this->service->checkArrears($tenancy->id);
                $parking   = $this->service->getCurrentParkingSlot($tenancy->id);
                $activeLease = TenantLease::where('tenant_management_id', $tenancy->id)
                    ->where('status', 'active')
                    ->latest()
                    ->first();

                $pageData = [
                    'tenancy_id'      => $tenancy->id,
                    'complex_id'      => $tenancy->complex_id,
                    'unit_type'       => $this->resolveUnitType($tenancy),
                    'unit_number'     => $tenancy->unit_number,
                    'tenant_name'     => optional($tenancy->tenantProfile)->full_name ?? '',
                    'currency'        => $tenancy->currency ?? 'USD',
                    'monthly_payment' => (float) $tenancy->monthly_payment,
                    'arrears'         => $arrears,
                    'has_parking'     => (bool) $parking,
                    'parking_slot'    => $parking ? ['id' => $parking->id, 'label' => $parking->parking_number ?? 'Slot #' . $parking->id] : null,
                    'lease_data'      => $activeLease ? [
                        'lease_start_date'              => optional($activeLease->lease_start_date)->toDateString(),
                        'lease_end_date'                => optional($activeLease->lease_end_date)->toDateString(),
                        'lease_duration_months'         => $activeLease->lease_duration_months,
                        'rent_amount'                   => $activeLease->rent_amount,
                        'security_deposit'              => $activeLease->security_deposit,
                        'payment_frequency'             => $activeLease->payment_frequency,
                        'payment_day_of_month'          => $activeLease->payment_day_of_month,
                        'late_fee_amount'               => $activeLease->late_fee_amount,
                        'grace_period_days'             => $activeLease->grace_period_days,
                        'lease_type'                    => $activeLease->lease_type,
                        'special_terms'                 => $activeLease->special_terms,
                    ] : [],
                ];
            }
        }

        return view('pages.business.tenant.movement.create', compact('pageData'));
    }

    // ─── AJAX — List ──────────────────────────────────────────────────────

    /**
     * Return paginated movements list + stats for the overview dashboard.
     */
    public function list(Request $request)
    {
        try {
            $userId = business_owner_id();
            $query  = TenantMovement::where('managed_by', $userId)
                ->with(['tenantProfile']);

            // Search
            if ($request->filled('search')) {
                $s = $request->search;
                $query->where(function ($q) use ($s) {
                    $q->whereLike('ref', "%$s%")
                      ->orWhereHas('tenantProfile', fn($p) => $p->whereLike('first_name', "%$s%")->orWhereLike('last_name', "%$s%"));
                });
            }

            // Filter by status
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $total = (clone $query)->count();

            $movements = $query->orderBy('created_at', 'desc')
                ->skip((int) $request->input('start', 0))
                ->take((int) $request->input('length', 25))
                ->get();

            // Stats
            $stats = TenantMovement::where('managed_by', $userId)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            return response()->json([
                'status' => 200,
                'data'   => $movements->map(fn($m) => $this->formatMovementRow($m)),
                'total'  => $total,
                'stats'  => [
                    'pending'   => $stats['pending']   ?? 0,
                    'approved'  => $stats['approved']  ?? 0,
                    'completed' => $stats['completed'] ?? 0,
                    'cancelled' => $stats['cancelled'] ?? 0,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('TenantMovementController::list error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 500, 'message' => 'Failed to load movements'], 500);
        }
    }

    // ─── AJAX — Vacant Units ──────────────────────────────────────────────

    public function getVacantUnits(Request $request)
    {
        try {
            $request->validate(['complex_id' => 'required|integer', 'unit_type' => 'required|in:residential,commercial']);
            $units = $this->service->getVacantUnits(
                $request->integer('complex_id'),
                $request->unit_type,
                $request->integer('exclude_tenancy_id', 0)
            );
            return response()->json(['status' => 200, 'data' => $units]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── AJAX — Parking Slots ─────────────────────────────────────────────

    public function getParkingSlots(Request $request)
    {
        try {
            $request->validate(['complex_id' => 'required|integer']);
            $slots = $this->service->getAvailableParkingSlots(
                $request->integer('complex_id'),
                $request->integer('exclude_slot_id', 0)
            );
            return response()->json(['status' => 200, 'data' => $slots]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── AJAX — Preview ───────────────────────────────────────────────────

    public function preview(Request $request)
    {
        try {
            $request->validate(['tenancy_id' => 'required|integer']);
            $tenancy = TenantManagement::where('id', $request->tenancy_id)
                ->where('managed_by', business_owner_id())
                ->with('tenantProfile')
                ->firstOrFail();

            $preview = $this->service->buildPreview($request->all(), $tenancy);
            return response()->json(['status' => 200, 'data' => $preview]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── AJAX — Initiate ──────────────────────────────────────────────────

    public function initiate(Request $request)
    {
        $request->validate([
            'tenancy_id'          => 'required|exists:tenant_management,id',
            'new_unit_id'         => 'required|integer',
            'new_unit_type'       => 'required|in:residential,commercial',
            'new_unit_number'     => 'nullable|string',
            'move_type'           => 'required|in:end_of_month,mid_month',
            'transfer_date'       => 'required|date',
            'new_rent'            => 'required|numeric|min:0',
            'new_deposit'         => 'required|numeric|min:0',
            'parking_action'      => 'nullable|in:keep,release,replace,none',
            'new_parking_slot_id' => 'nullable|integer',
            'arrears_acknowledged'=> 'nullable|boolean',
            'notes'               => 'nullable|string|max:1000',
            'lease_data'          => 'nullable|array',
            'lease_data.occupied_sq_area' => 'nullable|numeric|min:0',
        ]);

        try {
            $tenancy = TenantManagement::where('id', $request->tenancy_id)
                ->where('managed_by', business_owner_id())
                ->firstOrFail();

            $arrears = $this->service->checkArrears($tenancy->id);
            if ($arrears['type'] === 'block') {
                return response()->json(['status' => 422, 'message' => $arrears['message']], 422);
            }

            // The target must be a unit of this business's property; end-of-period moves happen when the next period starts.
            $unitTable = $request->new_unit_type === 'commercial' ? 'commercial_units' : 'complex_residential_units';
            if (!DB::table($unitTable)->where('id', $request->new_unit_id)->where('complex_id', $tenancy->complex_id)->exists()) {
                return response()->json(['status' => 422, 'message' => 'Choose a unit in the same property.'], 422);
            }
            $transferDate = $this->service->calculateTransferDates($request->move_type, $request->transfer_date, $tenancy)['transfer_date'];
            $request->merge(['transfer_date' => $transferDate]);
            if ($request->new_unit_type === 'commercial') {
                $leaseData = $request->lease_data ?? [];
                $leaseData['occupied_sq_area'] = $leaseData['occupied_sq_area'] ?? $tenancy->occupied_sq_area;
                $request->merge(['lease_data' => $leaseData]);
            }

            $parking     = $this->service->getCurrentParkingSlot($tenancy->id);
            $depositAdj  = $this->service->calculateDepositAdjustment(
                (float) $tenancy->security_deposit,
                (float) $request->new_deposit
            );

            $proration = 0;
            if ($request->move_type === 'mid_month') {
                $prorObj   = $this->service->calculateProration((float) $tenancy->monthly_payment, (float) $request->new_rent, $request->transfer_date, $tenancy);
                $proration = $prorObj['net'];
            }

            // Resolve unit number
            $newUnitNumber = $request->new_unit_number;
            if (!$newUnitNumber) {
                if ($request->new_unit_type === 'commercial') {
                    $unit = DB::table('commercial_units')->find($request->new_unit_id);
                } else {
                    $unit = DB::table('complex_residential_units')->find($request->new_unit_id);
                }
                $newUnitNumber = $unit->unit_number ?? ('Unit ' . $request->new_unit_id);
            }

            $movement = TenantMovement::create([
                'ref'                  => TenantMovement::generateRef(),
                'managed_by'           => business_owner_id(),
                'tenant_profile_id'    => $tenancy->tenant_profile_id,
                'old_tenancy_id'       => $tenancy->id,
                'old_complex_id'       => $tenancy->complex_id,
                'old_unit_type'        => $this->resolveUnitType($tenancy),
                'old_unit_id'          => $tenancy->commercial_unit_id ?? $tenancy->residential_unit_id,
                'old_unit_number'      => $tenancy->unit_number,
                'new_complex_id'       => $tenancy->complex_id,
                'new_unit_type'        => $request->new_unit_type,
                'new_unit_id'          => $request->new_unit_id,
                'new_unit_number'      => $newUnitNumber,
                'move_type'            => $request->move_type,
                'requested_date'       => Carbon::today()->toDateString(),
                'transfer_date'        => $request->transfer_date,
                'old_rent'             => $tenancy->monthly_payment,
                'new_rent'             => $request->new_rent,
                'old_deposit'          => $tenancy->security_deposit,
                'new_deposit'          => $request->new_deposit,
                'deposit_adjustment'   => $depositAdj['adjustment'],
                'deposit_adjustment_type' => $depositAdj['type'],
                'proration_amount'     => $proration,
                'currency'             => $tenancy->currency ?? 'USD',
                'arrears_amount'       => $arrears['amount'],
                'arrears_acknowledged' => (bool) $request->arrears_acknowledged,
                'parking_action'       => $request->parking_action ?? ($parking ? 'keep' : 'none'),
                'old_parking_slot_id'  => $parking?->id,
                'new_parking_slot_id'  => $request->new_parking_slot_id,
                'lease_data'           => $request->lease_data ?? [],
                'status'               => 'pending',
                'initiated_by'         => auth()->id(),
                'notes'                => $request->notes,
            ]);

            Log::info("Tenant movement initiated: {$movement->ref}", ['initiated_by' => auth()->id()]);

            return response()->json([
                'status'  => 200,
                'message' => 'Transfer request submitted successfully.',
                'ref'     => $movement->ref,
            ]);
        } catch (\Throwable $e) {
            Log::error('TenantMovementController::initiate error', ['error' => $e->getMessage()]);
            return response()->json(['status' => 500, 'message' => 'Failed to submit transfer: ' . $e->getMessage()], 500);
        }
    }

    // ─── AJAX — Approve ───────────────────────────────────────────────────

    public function approve(Request $request, string $ref)
    {
        try {
            $movement = TenantMovement::where('ref', $ref)
                ->where('managed_by', business_owner_id())
                ->whereIn('status', ['pending'])
                ->firstOrFail();

            $completed = $this->service->executeMovement($movement);

            Log::info("Tenant movement approved: {$ref}", ['approved_by' => auth()->id(), 'status' => $completed->status]);

            if ($completed->status === 'approved') {
                return response()->json([
                    'status'  => 200,
                    'message' => 'Transfer approved. It will be carried out on ' . Carbon::parse($completed->transfer_date)->format('j M Y') . '.',
                ]);
            }

            $this->sendMovementEmail($completed);
            return response()->json([
                'status'  => 200,
                'message' => 'Transfer carried out. The new tenancy is ' . ($completed->newTenancy->tenancy_ref ?? '') . '.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['status' => 422, 'message' => collect($e->errors())->flatten()->first()], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'message' => 'Movement not found or cannot be approved.'], 404);
        } catch (\Throwable $e) {
            Log::error("TenantMovementController::approve error for {$ref}", ['error' => $e->getMessage()]);
            return response()->json(['status' => 500, 'message' => 'Failed to approve transfer: ' . $e->getMessage()], 500);
        }
    }

    // ─── AJAX — Cancel ────────────────────────────────────────────────────

    public function cancel(Request $request, string $ref)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        try {
            $movement = TenantMovement::where('ref', $ref)
                ->where('managed_by', business_owner_id())
                ->whereIn('status', ['pending', 'approved'])
                ->firstOrFail();

            $movement->update([
                'status'              => 'cancelled',
                'cancelled_by'        => auth()->id(),
                'cancellation_reason' => $request->reason,
            ]);

            return response()->json(['status' => 200, 'message' => 'Transfer cancelled.']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['status' => 404, 'message' => 'Movement not found or cannot be cancelled.'], 404);
        } catch (\Throwable $e) {
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── AJAX — Active Tenancy for Tenant ─────────────────────────────────

    public function getActiveTenancyForTenant(int $tenantId)
    {
        try {
            $tenancy = TenantManagement::where('tenant_profile_id', $tenantId)
                ->where('managed_by', business_owner_id())
                ->where('status', 'Active')
                ->with(['tenantProfile'])
                ->latest()
                ->first();

            if (!$tenancy) {
                return response()->json(['status' => 404, 'message' => 'No active tenancy found for this tenant.'], 404);
            }

            $arrears = $this->service->checkArrears($tenancy->id);
            $parking = $this->service->getCurrentParkingSlot($tenancy->id);
            $activeLease = TenantLease::where('tenant_management_id', $tenancy->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            return response()->json([
                'status'  => 200,
                'data'    => [
                    'tenancy_id'      => $tenancy->id,
                    'complex_id'      => $tenancy->complex_id,
                    'unit_type'       => $this->resolveUnitType($tenancy),
                    'unit_number'     => $tenancy->unit_number,
                    'currency'        => $tenancy->currency ?? 'USD',
                    'monthly_payment' => (float) $tenancy->monthly_payment,
                    'arrears'         => $arrears,
                    'has_parking'     => (bool) $parking,
                    'parking_slot'    => $parking ? ['id' => $parking->id, 'label' => $parking->parking_number ?? 'Slot #' . $parking->id] : null,
                    'lease_data'      => $activeLease ? [
                        'lease_start_date'      => optional($activeLease->lease_start_date)->toDateString(),
                        'lease_end_date'        => optional($activeLease->lease_end_date)->toDateString(),
                        'lease_duration_months' => $activeLease->lease_duration_months,
                        'rent_amount'           => $activeLease->rent_amount,
                        'security_deposit'      => $activeLease->security_deposit,
                        'payment_frequency'     => $activeLease->payment_frequency,
                        'payment_day_of_month'  => $activeLease->payment_day_of_month,
                        'late_fee_amount'       => $activeLease->late_fee_amount,
                        'grace_period_days'     => $activeLease->grace_period_days,
                        'lease_type'            => $activeLease->lease_type,
                        'special_terms'         => $activeLease->special_terms,
                    ] : [],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Individual Movement Show ─────────────────────────────────────────

    public function show(string $ref)
    {
        $movement = TenantMovement::where('ref', $ref)
            ->where('managed_by', business_owner_id())
            ->with(['tenantProfile', 'oldTenancy', 'newTenancy'])
            ->firstOrFail();

        return view('pages.business.tenant.movement.show', compact('movement'));
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    private function resolveUnitType(TenantManagement $tenancy): string
    {
        return $tenancy->commercial_unit_id ? 'commercial' : 'residential';
    }

    private function formatMovementRow(TenantMovement $m): array
    {
        return [
            'id'            => $m->id,
            'ref'           => $m->ref,
            'tenant_name'   => optional($m->tenantProfile)->full_name ?? 'N/A',
            'from_unit'     => $m->from_unit_label,
            'to_unit'       => $m->to_unit_label,
            'move_type'     => $m->move_type === 'end_of_month' ? 'End of billing period' : 'Mid-period',
            'transfer_date' => Carbon::parse($m->transfer_date)->format('d M Y'),
            'status'        => $m->status,
            'status_badge'  => $m->status_badge,
            'can_approve'   => $m->status === 'pending',
            'can_cancel'    => in_array($m->status, ['pending', 'approved']),
        ];
    }

    private function sendMovementEmail(TenantMovement $movement): void
    {
        try {
            $tenantProfile = $movement->tenantProfile;
            if (!$tenantProfile || !$tenantProfile->email) {
                return;
            }

            Mail::send('emails.tenant.movement_confirmed', [
                'movement'    => $movement,
                'tenantName'  => $tenantProfile->full_name ?? 'Tenant',
                'currency'    => $movement->currency,
            ], function ($mail) use ($tenantProfile, $movement) {
                $mail->to($tenantProfile->email)
                     ->subject("Unit Transfer Confirmed — {$movement->ref}");
            });
        } catch (\Throwable $e) {
            Log::warning("Failed to send movement email for {$movement->ref}: " . $e->getMessage());
        }
    }
}
