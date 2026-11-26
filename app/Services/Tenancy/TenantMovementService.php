<?php

namespace App\Services\Tenancy;

use App\Models\CommercialUnit;
use App\Models\ComplexParkingSlot;
use App\Models\ComplexResidentialUnit;
use App\Models\TenantLease;
use App\Models\TenantManagement;
use App\Models\TenantMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Properties\ComplexInventoryService;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use App\Services\Tenancy\TenantStoreService;

class TenantMovementService
{
    // ─── Vacant Units ──────────────────────────────────────────────────────

    /**
     * Return vacant units of the same type in the given complex.
     */
    public function getVacantUnits(int $complexId, string $unitType, int $excludeTenancyId = 0): array
    {
        Log::info("Fetching vacant units for complex_id={$complexId}, unit_type={$unitType}, exclude_tenancy_id={$excludeTenancyId}");
        if ($unitType === 'commercial') {
            // Same rule as onboarding: a space can take another tenant while it has free area and subdivisions.
            $inventory = app(ComplexInventoryService::class);
            $units = CommercialUnit::where('complex_id', $complexId)->get()->filter(function ($unit) use ($inventory, $excludeTenancyId) {
                $o = $inventory->commercialOccupancy($unit, $excludeTenancyId ?: null);
                return $o['leasable'] && $o['remaining_sq_ft'] > 0 && $o['remaining_subdivisions'] >= 1;
            })->each(function ($unit) use ($inventory, $excludeTenancyId) {
                $unit->remaining_sq_ft = $inventory->commercialOccupancy($unit, $excludeTenancyId ?: null)['remaining_sq_ft'];
            });

        } else {
            $occupied = TenantManagement::where('complex_id', $complexId)
                ->where('status', 'Active')
                ->whereNotNull('residential_unit_id')
                ->when($excludeTenancyId, fn($q) => $q->where('id', '!=', $excludeTenancyId))
                ->pluck('residential_unit_id')
                ->toArray();

            // Free units only: not let, and not held back for maintenance or reserved. Rent is set on the transfer form.
            $units = ComplexResidentialUnit::where('complex_id', $complexId)
                ->whereNotIn('id', $occupied)
                ->where(fn ($q) => $q->whereNull('status')->orWhereNotIn(DB::raw('LOWER(status)'), ['maintenance', 'reserved', 'unavailable', 'occupied', 'under_offer', 'sold']))
                // Houses offered for sale only are not let.
                ->where(fn ($q) => $q->whereNull('unit_category')->orWhere('unit_category', '!=', 'house')->orWhereNull('offer')->orWhereIn('offer', ['rent', 'both']))
                ->orderBy('unit_number')
                ->select('id', 'unit_number', 'bedrooms', 'bathrooms', 'sq_ft')
                ->get();
        }

        return $units->map(function ($unit) use ($unitType) {
            if ($unitType === 'commercial') {
                return [
                    'id'               => $unit->id,
                    'label'            => $unit->unit_number ?? 'Unit ' . $unit->id,
                    'unit_type'        => $unitType,
                    'rent'             => $unit->monthly_rent ?? null,
                    'unit_sq_ft'       => $unit->unit_sq_ft ?? null,
                    'min_sq_ft'        => $unit->min_sq_ft ?? null,
                    'max_subdivisions' => $unit->max_subdivisions ?? null,
                    'remaining_sq_ft'  => $unit->remaining_sq_ft ?? null,
                ];
            }

            return [
                'id'         => $unit->id,
                'label'      => $unit->unit_number ?? 'Unit ' . $unit->id,
                'unit_type'  => $unitType,
                'rent'       => null,
                'bedrooms'   => $unit->bedrooms ?? null,
                'bathrooms'  => $unit->bathrooms ?? null,
                'sq_area'    => $unit->sq_ft ?? null,
            ];
        })->toArray();
    }

    // ─── Arrears ───────────────────────────────────────────────────────────

    /**
     * Check tenancy arrears.
     *
     * Returns:
     *   type: 'none' | 'warning' | 'block'
     *   amount: float
     *   threshold: float (2 months rent)
     *   message: string
     */
    public function checkArrears(int $tenancyId): array
    {
        $tenancy = TenantManagement::findOrFail($tenancyId);
        $monthlyRent = (float) $tenancy->monthly_payment;

        // Overdue amounts on the tenant's account (invoices past their due date, less nothing: credit is shown separately).
        $arrears = app(TenantAccountService::class)->overdue($tenancy);

        $threshold = $monthlyRent * 2;

        if ($arrears <= 0) {
            return ['type' => 'none', 'amount' => 0, 'threshold' => $threshold, 'message' => ''];
        }

        if ($arrears >= $threshold) {
            return [
                'type'      => 'block',
                'amount'    => $arrears,
                'threshold' => $threshold,
                'message'   => "This tenant has outstanding arrears of {$tenancy->currency} " . number_format($arrears, 2) .
                               ", which is ≥ 2 months rent. Transfer cannot proceed until arrears are resolved.",
            ];
        }

        return [
            'type'      => 'warning',
            'amount'    => $arrears,
            'threshold' => $threshold,
            'message'   => "This tenant has outstanding arrears of {$tenancy->currency} " . number_format($arrears, 2) .
                           ". You must acknowledge this before proceeding.",
        ];
    }

    // ─── Transfer Dates ────────────────────────────────────────────────────

    /**
     * Calculate transfer date and effective date based on move type.
     *
     * end_of_month → transfer_date = 1st of next month
     * mid_month    → transfer_date = chosen date
     */
    public function calculateTransferDates(string $moveType, ?string $requestedDate = null, ?TenantManagement $tenancy = null): array
    {
        $today = Carbon::today();

        if ($moveType === 'mid_month') {
            $transferDate = $requestedDate ? Carbon::parse($requestedDate) : $today;
        } elseif ($tenancy) {
            // End of the current billing period: the move happens when the next period starts (no proration).
            $transferDate = $this->periodContaining($tenancy, $today)['end']->copy()->addDay();
        } else {
            $transferDate = $today->copy()->startOfMonth()->addMonth();
        }

        return [
            'transfer_date'   => $transferDate->toDateString(),
            'requested_date'  => $today->toDateString(),
        ];
    }

    /** The billing period of this tenancy that contains $date. */
    public function periodContaining(TenantManagement $tenancy, Carbon $date): array
    {
        $charges = app(TenantChargesService::class);
        $n = 0;
        $p = $charges->period($tenancy, 0);
        while ($p['end']->lt($date) && $n < 600) {
            $p = $charges->period($tenancy, ++$n);
        }
        return $p;
    }

    // ─── Proration ─────────────────────────────────────────────────────────

    /**
     * Mid-period move: rent for the current period was billed in advance on the old unit.
     *   old_credit → unused days of the old rent, returned
     *   new_charge → the new rent for those same days
     *   net        → positive = the tenant owes (adjustment invoice), negative = credit on the account
     */
    public function calculateProration(float $oldRent, float $newRent, string $moveDate, ?TenantManagement $tenancy = null): array
    {
        $date = Carbon::parse($moveDate)->startOfDay();
        if ($tenancy) {
            $p = $this->periodContaining($tenancy, $date);
            $months = TenantChargesService::monthsPerPeriod($tenancy->payment_frequency) ?? 12 / 52;
            [$start, $end] = [$p['start'], $p['end']];
        } else {
            $months = 1;
            [$start, $end] = [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()->startOfDay()];
        }
        $periodDays = (int) $start->diffInDays($end) + 1;
        $unused = $date->lte($start) ? $periodDays : max(0, (int) $date->diffInDays($end) + 1);
        $used = $periodDays - $unused;

        $oldCredit = round($oldRent * $months * $unused / $periodDays, 2);
        $newCharge = round($newRent * $months * $unused / $periodDays, 2);
        $net = round($newCharge - $oldCredit, 2);

        return [
            'period_start'    => $start->toDateString(),
            'period_end'      => $end->toDateString(),
            'period_days'     => $periodDays,
            'old_days_used'   => $used,
            'old_days_unused' => $unused,
            'old_credit'      => $oldCredit,
            'new_days_used'   => $unused,
            'new_charge'      => $newCharge,
            'net'             => $net,
            'net_type'        => $net > 0 ? 'invoice' : ($net < 0 ? 'credit_note' : 'nil'),
        ];
    }

    // ─── Deposit Adjustment ────────────────────────────────────────────────

    public function calculateDepositAdjustment(float $oldDeposit, float $newDeposit): array
    {
        $diff = $newDeposit - $oldDeposit;

        return [
            'old_deposit'  => $oldDeposit,
            'new_deposit'  => $newDeposit,
            'adjustment'   => abs($diff),
            'type'         => $diff > 0 ? 'top_up' : ($diff < 0 ? 'refund' : 'none'),
            'label'        => $diff > 0
                ? 'Tenant owes top-up of ' . number_format(abs($diff), 2)
                : ($diff < 0
                    ? 'Refund owed to tenant: ' . number_format(abs($diff), 2)
                    : 'No deposit change'),
        ];
    }

    // ─── Parking ───────────────────────────────────────────────────────────

    public function getCurrentParkingSlot(int $tenancyId): ?object
    {
        return ComplexParkingSlot::where('occupied_tenant_id', $tenancyId)->first();
    }

    public function getAvailableParkingSlots(int $complexId, int $excludeSlotId = 0): array
    {
        return ComplexParkingSlot::where('complex_id', $complexId)
            ->whereNull('occupied_tenant_id')
            ->when($excludeSlotId, fn($q) => $q->where('id', '!=', $excludeSlotId))
            ->get()
            ->map(fn($s) => ['id' => $s->id, 'label' => $s->parking_number ?? ('Slot #' . $s->id), 'type' => $s->parking_type])
            ->toArray();
    }

    // ─── Preview ───────────────────────────────────────────────────────────

    /**
     * Build the full preview object from form data.
     */
    public function buildPreview(array $data, TenantManagement $tenancy): array
    {
        $dates        = $this->calculateTransferDates($data['move_type'] ?? 'end_of_month', $data['transfer_date'] ?? null, $tenancy);
        $arrears      = $this->checkArrears($tenancy->id);
        $depositAdj   = $this->calculateDepositAdjustment(
            (float) ($data['old_deposit'] ?? $tenancy->security_deposit),
            (float) ($data['new_deposit'] ?? $tenancy->security_deposit)
        );

        $proration = null;
        if (($data['move_type'] ?? '') === 'mid_month') {
            $proration = $this->calculateProration(
                (float) $tenancy->monthly_payment,
                (float) ($data['new_rent'] ?? $tenancy->monthly_payment),
                $dates['transfer_date'],
                $tenancy
            );
        }

        $parking = null;
        $currentSlot = $this->getCurrentParkingSlot($tenancy->id);
        if ($currentSlot) {
            $parking = [
                'current_slot' => $currentSlot,
                'action'       => $data['parking_action'] ?? 'keep',
                'new_slot_id'  => $data['new_parking_slot_id'] ?? null,
            ];
        }

        return [
            'tenant_name'    => optional($tenancy->tenantProfile)->full_name ?? 'Tenant',
            'old_unit'       => $tenancy->unit_number ?? 'Unit ' . $tenancy->id,
            'new_unit'       => $data['new_unit_number'] ?? 'Selected Unit',
            'move_type'      => $data['move_type'] ?? 'end_of_month',
            'transfer_date'  => $dates['transfer_date'],
            'currency'       => $tenancy->currency ?? default_currency(),
            'old_rent'       => $tenancy->monthly_payment,
            'new_rent'       => $data['new_rent'] ?? $tenancy->monthly_payment,
            'arrears'        => $arrears,
            'deposit'        => $depositAdj + ['held' => app(TenantAccountService::class)->depositHeld($tenancy)],
            'account'        => app(TenantAccountService::class)->summary($tenancy),
            'proration'      => $proration,
            'parking'        => $parking,
            'lease_summary'  => $data['lease_data'] ?? [],
        ];
    }

    /** A tenancy's payment frequency as tenant_leases stores it (monthly, quarterly, semi-annually, annually). */
    public static function leaseFrequency(?string $frequency): string
    {
        return match (TenantChargesService::monthsPerPeriod($frequency)) {
            3 => 'quarterly',
            6 => 'semi-annually',
            12 => 'annually',
            default => 'monthly',
        };
    }

    // ─── Execute Movement ──────────────────────────────────────────────────

    /**
     * Approve a transfer. A transfer dated in the future is held as "approved" and carried out
     * by the daily run on its date; one dated today or earlier is carried out now.
     */
    public function executeMovement(TenantMovement $movement): TenantMovement
    {
        if (Carbon::parse($movement->transfer_date)->startOfDay()->gt(Carbon::today())) {
            $this->assertTargetAvailable($movement);
            $movement->update(['status' => 'approved', 'approved_by' => auth()->id()]);
            return $movement->fresh(['tenantProfile', 'oldTenancy', 'newTenancy']);
        }
        return $this->run($movement);
    }

    /** Carry out approved transfers whose date has arrived (daily run). */
    public function executeDue(Carbon $date): int
    {
        $count = 0;
        TenantMovement::where('status', 'approved')->whereDate('transfer_date', '<=', $date->toDateString())->orderBy('transfer_date')
            ->get()->each(function (TenantMovement $movement) use (&$count) {
                try {
                    $this->run($movement);
                    $count++;
                } catch (\Throwable $e) {
                    Log::error("Scheduled transfer {$movement->ref} failed: " . $e->getMessage());
                }
            });
        return $count;
    }

    /** The target unit must still be free (residential) or have room (commercial), checked under a lock. */
    public function assertTargetAvailable(TenantMovement $movement): void
    {
        $old = TenantManagement::findOrFail($movement->old_tenancy_id);
        if ($movement->new_unit_type === 'commercial') {
            $unit = CommercialUnit::where('complex_id', $movement->new_complex_id)->lockForUpdate()->findOrFail($movement->new_unit_id);
            $area = (float) (($movement->lease_data ?? [])['occupied_sq_area'] ?? $old->occupied_sq_area ?? 0);
            app(ComplexInventoryService::class)->assertCommercialCapacity($unit, $area, $old->commercial_unit_id === $unit->id ? $old->id : null);
            return;
        }
        $unit = ComplexResidentialUnit::where('complex_id', $movement->new_complex_id)->lockForUpdate()->findOrFail($movement->new_unit_id);
        $taken = TenantManagement::where('residential_unit_id', $unit->id)->where('status', 'Active')->where('id', '!=', $old->id)->exists();
        if ($taken || in_array(strtolower((string) $unit->status), ['maintenance', 'reserved', 'unavailable', 'under_offer', 'sold'], true) || ($unit instanceof \App\Models\ComplexResidentialUnit && !$unit->forRent())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['new_unit_id' => "Unit {$unit->unit_number} is no longer available."]);
        }
    }

    /**
     * Carry out a transfer:
     *  - the old tenancy ends on the transfer date; a new tenancy (new TM reference) takes over the account,
     *    the agent and the billing terms, continuing on the same billing cycle;
     *  - a mid-period move nets the unused old rent against the new rent for the rest of the period
     *    (an adjustment invoice if the tenant owes, credit if they are owed);
     *  - the deposit moves with the tenant: a higher deposit is billed as a top-up, a lower one becomes credit;
     *  - unit occupancy and parking follow.
     */
    public function run(TenantMovement $movement): TenantMovement
    {
        $issued = collect();
        $charges = app(TenantChargesService::class);
        $account = app(TenantAccountService::class);
        $inventory = app(ComplexInventoryService::class);

        $newTenancy = DB::transaction(function () use ($movement, $charges, $account, $inventory, &$issued) {
            $movement = TenantMovement::lockForUpdate()->findOrFail($movement->id);
            if (!in_array($movement->status, ['pending', 'approved'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['movement' => 'This transfer is no longer pending.']);
            }
            $oldTenancy = TenantManagement::lockForUpdate()->findOrFail($movement->old_tenancy_id);
            if (!in_array($oldTenancy->status, ['Active', 'On Notice'], true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['movement' => 'The tenancy being transferred is no longer active.']);
            }
            $this->assertTargetAvailable($movement);
            $leaseData = $movement->lease_data ?? [];
            $date = Carbon::parse($movement->transfer_date)->startOfDay();
            $actor = auth()->id() ?? $movement->approved_by ?? $movement->initiated_by ?? $movement->managed_by;

            // Billing cycle: the period containing the transfer date is already billed on the old unit.
            $period = $this->periodContaining($oldTenancy, $date);
            $midPeriod = $date->gt($period['start']);
            $nextPeriodStart = $midPeriod ? $period['end']->copy()->addDay() : $date->copy();

            // 1. Close the old tenancy and lease
            $oldTenancy->update(['status' => 'Transferred', 'expected_move_out_date' => $date->toDateString()]);
            TenantLease::where('tenant_management_id', $oldTenancy->id)->where('status', 'active')->latest()->first()?->update([
                'status'             => 'transferred',
                'termination_date'   => $date->toDateString(),
                'termination_reason' => 'Unit transfer to ' . ($movement->new_unit_number ?? ''),
            ]);

            // 2. The new tenancy carries the account (previous_tenancy_id), agent and terms
            $unit = $movement->new_unit_type === 'commercial'
                ? CommercialUnit::find($movement->new_unit_id)
                : ComplexResidentialUnit::find($movement->new_unit_id);
            $newTenancy = TenantManagement::create(array_merge(
                $oldTenancy->only([
                    'tenant_profile_id', 'managed_by', 'complex_id', 'complex_name', 'listing_type', 'currency',
                    'number_of_occupants', 'has_pets', 'pet_details', 'agreement_type', 'assigned_agent_id',
                    'payment_frequency', 'payment_due_day', 'grace_period_days', 'late_fee_amount', 'penalty_amount',
                    'tenancy_quarterly_payment_type', 'furnishing',
                ]),
                [
                    'tenancy_ref'           => app(TenantStoreService::class)->generateTenancyReference(),
                    'previous_tenancy_id'   => $oldTenancy->id,
                    'commercial_unit_id'    => $movement->new_unit_type === 'commercial' ? $movement->new_unit_id : null,
                    'residential_unit_id'   => $movement->new_unit_type === 'residential' ? $movement->new_unit_id : null,
                    'unit_number'           => $movement->new_unit_number,
                    'bedrooms'              => $unit->bedrooms ?? null,
                    'bathrooms'             => $unit->bathrooms ?? null,
                    'sq_area'               => $unit->sq_ft ?? $unit->unit_sq_ft ?? null,
                    'occupied_sq_area'      => $movement->new_unit_type === 'commercial' ? ($leaseData['occupied_sq_area'] ?? $oldTenancy->occupied_sq_area) : null,
                    'service_charge_per_sq_ft' => $movement->new_unit_type === 'commercial' ? ($leaseData['service_charge_per_sq_ft'] ?? $oldTenancy->service_charge_per_sq_ft) : null,
                    'monthly_payment'       => $movement->new_rent,
                    'security_deposit'      => $movement->new_deposit,
                    'move_in_date'          => $date->toDateString(),
                    'lease_start_date'      => $date->toDateString(),
                    'agreement_start_date'  => $date->toDateString(),
                    'lease_end_date'        => $leaseData['lease_end_date'] ?? null,
                    'agreement_end_date'    => $leaseData['lease_end_date'] ?? null,
                    'lease_duration_months' => $leaseData['lease_duration_months'] ?? null,
                    'billing_start_date'    => $nextPeriodStart->toDateString(),
                    'status'                => 'Active',
                    'special_terms'         => $leaseData['special_terms'] ?? null,
                    // What changed with the move (the transfer form), otherwise as before.
                    'furnishing'            => $movement->new_unit_type === 'residential' ? ($leaseData['furnishing'] ?? $oldTenancy->furnishing) : null,
                    'number_of_occupants'   => $leaseData['number_of_occupants'] ?? $oldTenancy->number_of_occupants,
                    'rate_per_sq_ft'        => $movement->new_unit_type === 'commercial' ? ($leaseData['rate_per_sq_ft'] ?? $oldTenancy->rate_per_sq_ft) : null,
                    'created_by'            => $actor,
                    'updated_by'            => $actor,
                ]
            ));

            // 3. New lease (business resolved the same way as onboarding)
            $owner = \App\Models\User::find($oldTenancy->managed_by);
            TenantLease::create([
                'tenant_management_id'          => $newTenancy->id,
                'business_id'                   => $owner?->business?->id ?? $oldTenancy->managed_by,
                'lease_start_date'              => $date->toDateString(),
                'lease_end_date'                => $leaseData['lease_end_date'] ?? null,
                'lease_duration_months'         => $leaseData['lease_duration_months'] ?? null,
                'rent_amount'                   => $movement->new_rent,
                'security_deposit'              => $movement->new_deposit,
                'currency'                      => $movement->currency,
                // Billing continues on the tenancy's own cycle, so the lease records the same frequency and due day.
                'payment_frequency'             => self::leaseFrequency($oldTenancy->payment_frequency),
                'payment_day_of_month'          => $oldTenancy->payment_due_day ?: 1,
                'late_fee_amount'               => $leaseData['late_fee_amount'] ?? $oldTenancy->late_fee_amount ?? 0,
                'grace_period_days'             => $leaseData['grace_period_days'] ?? $oldTenancy->grace_period_days ?? 0,
                'lease_type'                    => empty($leaseData['lease_end_date']) ? 'month-to-month' : 'fixed-term',
                'status'                        => 'active',
                'special_terms'                 => $leaseData['special_terms'] ?? null,
            ]);

            // 4. Money at the transfer
            $label = 'unit ' . ($movement->new_unit_number ?? '');
            $proration = null;
            if ($midPeriod) {
                $proration = $this->calculateProration((float) $oldTenancy->monthly_payment, (float) $movement->new_rent, $date->toDateString(), $oldTenancy);
                if ($proration['net'] > 0) {
                    $issued->push($charges->charge($newTenancy, 'adjustment', $proration['net'],
                        "Transfer to {$label} on {$date->format('j M Y')}: new rent for {$proration['old_days_unused']} days less unused rent on the old unit", $date));
                } elseif ($proration['net'] < 0) {
                    $account->addCredit($newTenancy, abs($proration['net']), $date->toDateString(),
                        "Transfer to {$label}: unused rent on the old unit for {$proration['old_days_unused']} days less the new rent");
                }
            }
            $depositDiff = round((float) $movement->new_deposit - (float) $oldTenancy->security_deposit, 2);
            if ($depositDiff > 0) {
                $issued->push($charges->charge($newTenancy, 'deposit', $depositDiff, "Deposit top-up for {$label}", $date));
            } elseif ($depositDiff < 0) {
                $release = min(abs($depositDiff), $account->depositHeld($newTenancy));
                if ($release > 0) {
                    $account->releaseDeposit($newTenancy, $release, $date->toDateString(), "Deposit reduced on transfer to {$label}");
                }
            }

            // 5. Rent from the next period on the new unit
            $issued = $issued->merge($charges->billUpTo($newTenancy, Carbon::today(), notify: false));

            // 6. Parking follows the chosen option
            $inventory->releaseParkingExcept($oldTenancy->id, $movement->parking_action === 'keep' ? $movement->old_parking_slot_id : null);
            if ($movement->parking_action === 'keep' && $movement->old_parking_slot_id) {
                ComplexParkingSlot::withoutGlobalScopes()->whereKey($movement->old_parking_slot_id)->update(['occupied_tenant_id' => $newTenancy->id, 'is_occupied' => true]);
            } elseif ($movement->parking_action === 'replace' && $movement->new_parking_slot_id) {
                $slot = ComplexParkingSlot::withoutGlobalScopes()->lockForUpdate()->find($movement->new_parking_slot_id);
                if ($slot && !$slot->occupied_tenant_id && (int) $slot->complex_id === (int) $newTenancy->complex_id) {
                    $slot->update(['occupied_tenant_id' => $newTenancy->id, 'is_occupied' => true]);
                }
            }

            // 7. Occupancy of both units
            $inventory->syncOccupancy($oldTenancy->residential_unit_id, $oldTenancy->commercial_unit_id);
            $inventory->syncOccupancy($newTenancy->residential_unit_id, $newTenancy->commercial_unit_id);

            $movement->update([
                'new_tenancy_id'   => $newTenancy->id,
                'proration_amount' => $proration['net'] ?? 0,
                'status'           => 'completed',
                'approved_by'      => $movement->approved_by ?? auth()->id(),
                'executed_at'      => now(),
            ]);
            return $newTenancy;
        });

        $charges->notify($newTenancy, $issued);
        return $movement->fresh(['tenantProfile', 'oldTenancy', 'newTenancy']);
    }
}
