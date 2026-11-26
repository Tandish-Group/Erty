<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Requests\StoreTenancyRequest;
use App\Services\Tenancy\TenancyOnboardingRejected;
use App\Services\Tenancy\TenancyOnboardingService;

use App\Http\Controllers\Controller;
use App\Models\Legal;
use App\Models\TenantProfile;
use App\Services\Tenancy\TenantStoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Tenancy onboarding: the onboarding page, saving a new tenancy and finding existing tenants to re-use. */
class TenancyOnboardingController extends Controller
{
    public function __construct(
        private TenantStoreService $tenantStoreService,
    ) {
        $this->middleware('auth');
    }

    public function onboardTenant()
    {
        $user_id = business_owner_id();
        
        $property_types = DB::table('property_types')->get();
         // Fetch legal files associated with the user
        $legalFiles = Legal::join('legal_folders', 'legals.legal_folder_id', '=', 'legal_folders.id')
            ->join('legal_types', 'legals.legal_type_id', '=', 'legal_types.id')
            ->where('legals.user_id', $user_id)
            ->select(
                'legals.file_name', 
                'legal_folders.name as folder_name', 
                'legal_types.legal_type as type_name', 
                'legal_types.description as type_description')
            ->get();
            
        // Converting a lead: prefill the tenant from the lead and credit its agent.
        $conversionLead = null;
        if (request()->filled('lead')) {
            try {
                $leadId = (int) decrypt((string) request('lead'));
            } catch (\Throwable $e) {
                abort(404);
            }
            $conversionLead = \App\Models\Lead::with(['profile', 'complex', 'assignedAgent'])->where('user_id', $user_id)->findOrFail($leadId);
            if ($conversionLead->converted_tenant_id) {
                return redirect()->route('lead_management.show', encrypt($conversionLead->id))->with('info', 'This lead has already been converted to a tenant.');
            }
            if (!$conversionLead->readyToConvert()) {
                return redirect()->route('lead_management.show', encrypt($conversionLead->id))->with('warning', 'Before converting this lead, ' . implode(' and ', $conversionLead->missingForConversion()) . '.');
            }
        }

        return view('pages.business.tenant.onboard', compact('property_types', 'legalFiles', 'conversionLead'));
    }
    /**
     * Create the tenancy (StoreTenancyRequest validates the form; TenancyOnboardingService does the work).
     */
    public function store(StoreTenancyRequest $request, TenancyOnboardingService $onboarding)
    {
        try {
            return response()->json(DB::transaction(fn () => $onboarding->onboard($request)));
        } catch (TenancyOnboardingRejected $e) {
            return response()->json(['status' => 422, 'message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json(['status' => 422, 'message' => 'Check the onboarding details.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Tenant creation failed: ' . $e->getMessage(), [
                'tenant_option' => $request->tenant_option,
                'existing_tenant_id' => $request->existing_tenant_id ?? null,
                'user_id' => business_owner_id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Failed to create tenant. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get existing tenants for selection in new tenant form
     */
    public function getExistingTenants(Request $request)
    {
        $userId = business_owner_id();
        
        $query = TenantProfile::forBusiness($userId);
    
        // Apply search filter - now searching in tenant profile fields
        if ($request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('tenant_name', 'LIKE', "%{$search}%")
                ->orWhere('business_name', 'LIKE', "%{$search}%")
                ->orWhere('tenant_email', 'LIKE', "%{$search}%")
                ->orWhere('business_email', 'LIKE', "%{$search}%")
                ->orWhere('tenant_phone', 'LIKE', "%{$search}%")
                ->orWhere('business_phone', 'LIKE', "%{$search}%")
                ->orWhere('tenant_ref', 'LIKE', "%{$search}%")
                ->orWhere('tenant_id_number', 'LIKE', "%{$search}%")
                ->orWhere('business_registration', 'LIKE', "%{$search}%");
            });
        }
    
        // Apply tenant type filter - now filtering by tenant profile type
        if ($request->tenant_type) {
            $query->where('tenant_type', $request->tenant_type);
        }
    
        // Apply profile status filter if needed
        if ($request->profile_status) {
            $query->where('profile_status', $request->profile_status);
        }
    
        // Order by most recent first
        $tenants = $query->orderBy('updated_at', 'desc')
                        ->limit(20) // Limit to prevent too many results
                        ->get();
    
        // Transform the data
        $transformedTenants = $tenants->map(function($tenant) {
            return [
                'id' => $tenant->id,
                'tenant_profile_id' => $tenant->id,
                'tenant_profile_ref' => $tenant->tenant_ref,
                
                // Profile information
                'tenant_type' => $tenant->tenant_type,
                'profile_status' => $tenant->profile_status,
                
                // Individual tenant info
                'tenant_name' => $tenant->tenant_name,
                'tenant_email' => $tenant->tenant_email,
                'tenant_phone' => $tenant->tenant_phone,
                'tenant_id_number' => $tenant->tenant_id_number,
                
                // Business tenant info
                'business_name' => $tenant->business_name,
                'business_registration' => $tenant->business_registration,
                'business_contact_person' => $tenant->business_contact_person,
                'business_email' => $tenant->business_email,
                'business_phone' => $tenant->business_phone,
                
                // Emergency contact
                'emergency_contact_name' => $tenant->emergency_contact_name,
                'emergency_contact_phone' => $tenant->emergency_contact_phone,
                
                // Computed display fields
                'display_name' => $tenant->tenant_type === 'Business' 
                    ? ($tenant->business_name ?? 'Business Name N/A')
                    : ($tenant->tenant_name ?? 'Name N/A'),
                'primary_email' => $tenant->tenant_type === 'Business'
                    ? ($tenant->business_email ?? 'Email N/A')
                    : ($tenant->tenant_email ?? 'Email N/A'),
                'primary_phone' => $tenant->tenant_type === 'Business'
                    ? ($tenant->business_phone ?? 'Phone N/A')
                    : ($tenant->tenant_phone ?? 'Phone N/A'),
                
                // Management status
                'status' => $tenant->status,
                'is_active' => $tenant->status === 'Active',
                'is_blacklisted' => $tenant->profile_status === 'Blacklisted',
                
                // Last complex info
                'last_complex' => $tenant->last_complex_title ? [
                    'title' => $tenant->last_complex_title
                ] : null,
                
                // Additional info for frontend
                'can_create_new_tenancy' => $tenant->status !== 'Active', // Can only create new tenancy if not currently active
                'status_badge_class' => $this->getStatusBadgeClass($tenant->status, $tenant->profile_status),
            ];
        });
    
        return response()->json([
            'status' => 200,
            'tenants' => $transformedTenants,
            'total' => $transformedTenants->count(),
            'filters_applied' => [
                'search' => $request->search,
                'tenant_type' => $request->tenant_type,
                'status' => $request->status,
                'profile_status' => $request->profile_status,
            ]
        ]);
    }
    /**
     * Search tenants by name or email for autocomplete
     */
    public function searchTenants(Request $request)
    {
        $search = $request->input('search', '');
        $page = $request->input('page', 1);
        $perPage = 10; // Number of results per page
    
        // Query the TenantProfile model (this business only; limited members only see their tenants)
        $query = TenantProfile::query()
            ->where('managed_by', business_owner_id())
            ->where(function ($query) use ($search) {
                $query->where('tenant_name', 'LIKE', "%{$search}%")
                    ->orWhere('business_name', 'LIKE', "%{$search}%")
                    ->orWhere('tenant_email', 'LIKE', "%{$search}%")
                    ->orWhere('business_email', 'LIKE', "%{$search}%")
                    ->orWhere('tenant_phone', 'LIKE', "%{$search}%")
                    ->orWhere('business_phone', 'LIKE', "%{$search}%")
                    ->orWhere('tenant_ref', 'LIKE', "%{$search}%")
                    ->orWhere('tenant_id_number', 'LIKE', "%{$search}%")
                    ->orWhere('business_registration', 'LIKE', "%{$search}%")
                    ->orWhere('emergency_contact_name', 'LIKE', "%{$search}%")
                    ->orWhere('emergency_contact_phone', 'LIKE', "%{$search}%")
                    ->orWhere('tenant_ref', 'LIKE', "%{$search}%");
            });
    
        // Paginate the results
        $tenants = $query->paginate($perPage, ['*'], 'page', $page);
    
        // Format the response for select2
        $results = $tenants->items();
        $formattedResults = array_map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'tenant_ref' => $tenant->tenant_ref,
                'text' => $tenant->tenant_name 
                    ? "{$tenant->tenant_name} ({$tenant->tenant_email})"
                    : "{$tenant->business_name} ({$tenant->business_email})"
            ];
        }, $results);
    
        return response()->json([
            'data' => $formattedResults,
            'pagination' => [
                'more' => $tenants->hasMorePages()
            ]
        ]);
    }
    private function getStatusBadgeClass($managementStatus, $profileStatus)
    {
        if ($profileStatus === 'Blacklisted') {
            return 'badge-danger';
        }
        
        switch ($managementStatus) {
            case 'Active':
                return 'badge-success';
            case 'Inactive':
            case 'Terminated':
                return 'badge-secondary';
            case 'Pending':
                return 'badge-warning';
            default:
                return 'badge-light';
        }
    }
}
