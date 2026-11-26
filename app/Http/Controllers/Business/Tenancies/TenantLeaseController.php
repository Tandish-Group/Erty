<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Models\TenantLease;
use App\Models\TenantManagement;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TenantLeaseController extends Controller
{

    /**
     * Show the form for creating a new resource.
     */
    public function create($tenantManagementId)
    {
        $tenantManagement = TenantManagement::with('tenantProfile')
            ->where('id', $tenantManagementId)
            ->where('managed_by', business_owner_id())
            ->firstOrFail();

        // Check if lease already exists
        if ($tenantManagement->lease) {
            return redirect()->route('tenant_lease.edit', encrypt($tenantManagement->lease->id))
                ->with('info', 'Lease already exists. You can edit it here.');
        }

        return view('pages.business.tenant.lease.create', compact('tenantManagement'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_management_id' => 'required|exists:tenant_management,id',
            'lease_start_date' => 'required|date',
            'lease_end_date' => 'required|date|after:lease_start_date',
            'rent_amount' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:10',
            'payment_frequency' => 'required|in:monthly,quarterly,semi-annually,annually',
            'payment_day_of_month' => 'required|integer|min:1|max:31',
            'late_fee_amount' => 'nullable|numeric|min:0',
            'grace_period_days' => 'nullable|integer|min:0',
            'lease_type' => 'required|in:fixed-term,month-to-month,periodic',
            'auto_renewal' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:0',
            'renewal_rent_increase_percentage' => 'nullable|numeric|min:0|max:100',
            'special_terms' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'utilities_included' => 'nullable|array',
            'restrictions' => 'nullable|array',
        ]);

        // The tenancy must belong to this business (and, for limited roles, an assigned property).
        TenantManagement::forBusiness()->findOrFail($validated['tenant_management_id']);

        try {
            DB::beginTransaction();

            // Calculate lease duration in months
            $startDate = Carbon::parse($validated['lease_start_date']);
            $endDate = Carbon::parse($validated['lease_end_date']);
            $validated['lease_duration_months'] = (int) round($startDate->diffInMonths($endDate));

            // The business record of the signed-in owner or team member (users have no business_id column).
            $validated['business_id'] = auth()->user()->business->id;
            $validated['status'] = 'active';

            // Create the lease
            $lease = TenantLease::create($validated);

            // Update tenant management with lease dates
            $tenantManagement = TenantManagement::find($validated['tenant_management_id']);
            $tenantManagement->update([
                'lease_start_date' => $validated['lease_start_date'],
                'lease_end_date' => $validated['lease_end_date'],
                'monthly_payment' => $validated['rent_amount'],
                'security_deposit' => $validated['security_deposit'] ?? 0,
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Lease created successfully!',
                'lease_id' => $lease->id,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lease creation failed: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Failed to create lease. Please try again.',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($encryptedId)
    {
        $id = $this->leaseId($encryptedId);
        $property_types = DB::table('property_types')->get();
        $tenantLease = TenantLease::with(['tenantManagement.tenantProfile', 'tenantManagement.complex'])
            ->where('id', $id)
            ->where('business_id', auth()->user()->business->id)
            ->firstOrFail();
        
        return view('pages.business.tenant.lease.show', compact('tenantLease', 'property_types'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($encryptedId)
    {
        $id = $this->leaseId($encryptedId);
        $property_types = DB::table('property_types')->get();
        $lease = TenantLease::with(['tenantManagement.tenantProfile'])
            ->where('id', $id)
            ->where('business_id', auth()->user()->business->id)
            ->firstOrFail();

        return view('pages.business.tenant.lease.edit', compact('lease', 'property_types'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $lease = TenantLease::where('id', $id)
            ->where('business_id', auth()->user()->business->id)
            ->firstOrFail();

        $validated = $request->validate([
            'lease_start_date' => 'required|date',
            'lease_end_date' => 'required|date|after:lease_start_date',
            'rent_amount' => 'required|numeric|min:0',
            'security_deposit' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:10',
            'payment_frequency' => 'required|in:monthly,quarterly,semi-annually,annually',
            'payment_day_of_month' => 'required|integer|min:1|max:31',
            'late_fee_amount' => 'nullable|numeric|min:0',
            'grace_period_days' => 'nullable|integer|min:0',
            'lease_type' => 'required|in:fixed-term,month-to-month,periodic',
            'status' => 'required|in:active,pending,expired,terminated,renewed',
            'auto_renewal' => 'nullable|boolean',
            'renewal_notice_days' => 'nullable|integer|min:0',
            'renewal_rent_increase_percentage' => 'nullable|numeric|min:0|max:100',
            'special_terms' => 'nullable|string',
            'terms_and_conditions' => 'nullable|string',
            'utilities_included' => 'nullable|array',
            'restrictions' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            // Calculate lease duration in months
            $startDate = Carbon::parse($validated['lease_start_date']);
            $endDate = Carbon::parse($validated['lease_end_date']);
            $validated['lease_duration_months'] = (int) round($startDate->diffInMonths($endDate));

            // Update the lease
            $lease->update($validated);

            // Update tenant management with lease dates
            $lease->tenantManagement->update([
                'lease_start_date' => $validated['lease_start_date'],
                'lease_end_date' => $validated['lease_end_date'],
                'monthly_payment' => $validated['rent_amount'],
                'security_deposit' => $validated['security_deposit'] ?? 0,
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Lease updated successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lease update failed: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Failed to update lease. Please try again.',
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TenantLease $tenantLease)
    {
        try {
            $tenantLease->delete();

            return response()->json([
                'status' => 200,
                'message' => 'Lease deleted successfully!',
            ]);

        } catch (\Exception $e) {
            Log::error('Lease deletion failed: ' . $e->getMessage());

            return response()->json([
                'status' => 500,
                'message' => 'Failed to delete lease. Please try again.',
            ], 500);
        }
    }

    /** Lease links carry an encrypted id; anything that does not decrypt is simply not found. */
    private function leaseId(string $encryptedId): int
    {
        try {
            return (int) decrypt($encryptedId);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            abort(404);
        }
    }
}
