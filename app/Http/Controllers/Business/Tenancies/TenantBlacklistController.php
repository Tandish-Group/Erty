<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Models\TenantProfile;
use Illuminate\Http\Request;

class TenantBlacklistController extends Controller
{
    /**
     * Blacklist a tenant profile.
     *
     * Requirements:
     * - A reason must be provided.
     * - The tenant must not have any active tenancies.
     */
    public function blacklist(Request $request, $ref)
    {
        $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'A reason for blacklisting is required.',
            'reason.min'      => 'The reason must be at least 5 characters.',
            'reason.max'      => 'The reason may not exceed 500 characters.',
        ]);

        $profile = TenantProfile::where('tenant_ref', $ref)
            ->where('managed_by', business_owner_id())
            ->firstOrFail();

        // Guard: cannot blacklist a tenant with an active tenancy
        if ($profile->activeTenancies()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This tenant has an active tenancy and cannot be blacklisted. Please end or terminate the tenancy first.',
            ], 422);
        }

        // Already blacklisted
        if ($profile->is_blacklisted) {
            return response()->json([
                'success' => false,
                'message' => 'This tenant is already blacklisted.',
            ], 422);
        }

        $profile->update([
            'profile_status'  => 'Blacklisted',
            'blacklist_reason' => $request->reason,
            'blacklisted_date' => now(),
            'updated_by'      => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tenant has been blacklisted successfully.',
        ]);
    }

    /**
     * Remove a tenant profile from the blacklist.
     */
    public function unblacklist(Request $request, $ref)
    {
        $profile = TenantProfile::where('tenant_ref', $ref)
            ->where('managed_by', business_owner_id())
            ->firstOrFail();

        if (!$profile->is_blacklisted) {
            return response()->json([
                'success' => false,
                'message' => 'This tenant is not currently blacklisted.',
            ], 422);
        }

        $profile->update([
            'profile_status'  => 'Active',
            'blacklist_reason' => null,
            'blacklisted_date' => null,
            'updated_by'      => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tenant has been removed from the blacklist.',
        ]);
    }
}
