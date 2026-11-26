<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\CommercialUnit;
use App\Models\TenantInvoice;
use App\Models\TenantLease;
use App\Models\TenantManagement;
use App\Services\Tenancy\OverviewTenantStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The tenant workspace: dashboard and occupancy, the tenancy list, a tenancy's record, its update and removal. */
class TenantManagementController extends Controller
{
    public function __construct(private OverviewTenantStatsService $overviewTenantStatsService)
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user_id = business_owner_id();
    
        $property_types = DB::table('property_types')->get();
        
        // Get complexes with detailed statistics
        $complexes = Complex::forBusiness($user_id)
            ->withCount('listings')
            ->get();
    
        $tenants = TenantManagement::forBusiness($user_id)
            ->with('complex','commercialUnit','tenantProfile')
            ->orderBy('created_at', 'desc')
            ->get();
    
        // Calculate comprehensive statistics
        $stats = $this->overviewTenantStatsService->calculateTenantManagementStats($user_id);

        return view('pages.business.tenant.index', compact('property_types', 'complexes', 'tenants', 'stats'));
    }

    public function allTenants()
    {
        return view('pages.business.tenant.tenants');
    }

    /**
     * Edit an active tenancy's terms. The unit itself changes through Move/transfer.
     * Commercial area changes re-check the space's capacity; occupancy and the lease follow.
     */
    public function updateTenancy(Request $request, int $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $tenancy = TenantManagement::forBusiness()->lockForUpdate()->findOrFail($id);
            if ($tenancy->status !== 'Active') {
                throw ValidationException::withMessages(['tenancy' => 'Only active tenancies can be edited.']);
            }
            $data = $request->validate([
                'monthly_payment' => 'required|numeric|min:0',
                'security_deposit' => 'nullable|numeric|min:0',
                'agreement_type' => 'required|in:rental,lease,Rental,Lease',
                'lease_end_date' => 'nullable|date|after:' . ($tenancy->move_in_date ?? $tenancy->lease_start_date ?? '1900-01-01'),
                'payment_frequency' => 'required|in:Monthly,Quarterly,Annually',
                'grace_period_days' => 'nullable|integer|min:0|max:365',
                'late_fee_amount' => 'nullable|numeric|min:0',
                'number_of_occupants' => 'nullable|integer|min:1|max:100',
                'has_pets' => 'nullable|boolean',
                'pet_details' => 'nullable|required_if:has_pets,1|string|max:2000',
                'additional_notes' => 'nullable|string|max:5000',
                'assigned_agent_id' => ['nullable', 'integer', Rule::exists('agents', 'id')->where('created_by', business_owner_id())],
                'occupied_sq_area' => [$tenancy->commercial_unit_id ? 'required' : 'nullable', 'numeric', 'gt:0'],
            ]);

            $inventory = app(\App\Services\Properties\ComplexInventoryService::class);
            if ($tenancy->commercial_unit_id && (float) $data['occupied_sq_area'] !== (float) $tenancy->occupied_sq_area) {
                $unit = CommercialUnit::lockForUpdate()->findOrFail($tenancy->commercial_unit_id);
                $inventory->assertCommercialCapacity($unit, (float) $data['occupied_sq_area'], $tenancy->id);
            }

            $changes = [
                'monthly_payment' => $data['monthly_payment'],
                'security_deposit' => $data['security_deposit'] ?? 0,
                'agreement_type' => ucfirst(strtolower($data['agreement_type'])),
                'lease_end_date' => $data['lease_end_date'] ?? null,
                'agreement_end_date' => $data['lease_end_date'] ?? null,
                'payment_frequency' => $data['payment_frequency'],
                'grace_period_days' => $data['grace_period_days'] ?? 0,
                'late_fee_amount' => $data['late_fee_amount'] ?? null,
                'number_of_occupants' => $data['number_of_occupants'] ?? $tenancy->number_of_occupants,
                'has_pets' => !$tenancy->commercial_unit_id && !empty($data['has_pets']),
                'pet_details' => !$tenancy->commercial_unit_id && !empty($data['has_pets']) ? ($data['pet_details'] ?? null) : null,
                'additional_notes' => $data['additional_notes'] ?? null,
                'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
            ];
            if ($tenancy->commercial_unit_id) {
                $changes['occupied_sq_area'] = $data['occupied_sq_area'];
            }
            if (!empty($changes['lease_end_date']) && $tenancy->move_in_date) {
                $changes['lease_duration_months'] = (int) round(Carbon::parse($tenancy->move_in_date)->diffInMonths(Carbon::parse($changes['lease_end_date'])));
            }

            // Record what changed in the property history.
            $complex = Complex::find($tenancy->complex_id);
            $changed = [];
            foreach ($changes as $field => $value) {
                $before = $tenancy->getAttribute($field);
                if ((string) ($before instanceof \DateTimeInterface ? $before->format('Y-m-d') : $before) !== (string) $value) {
                    $changed[$field] = [$before, $value];
                }
            }
            $agentChanged = array_key_exists('assigned_agent_id', $changed);
            $tenancy->forceFill($changes + ['updated_by' => auth()->id()])->save();
            if ($complex) {
                foreach ($changed as $field => [$before, $after]) {
                    $inventory->audit($complex, 'tenancy ' . $tenancy->tenancy_ref . ': ' . $field, is_bool($before) ? (int) $before : $before, is_bool($after) ? (int) $after : $after);
                }
            }

            // Keep the active lease in step.
            TenantLease::where('tenant_management_id', $tenancy->id)->where('status', 'active')->latest()->first()?->update(array_filter([
                'rent_amount' => $changes['monthly_payment'],
                'security_deposit' => $changes['security_deposit'],
                'lease_end_date' => $changes['lease_end_date'],
                'payment_frequency' => strtolower($changes['payment_frequency']),
                'grace_period_days' => $changes['grace_period_days'],
                'late_fee_amount' => $changes['late_fee_amount'] ?? 0,
                'agreement_type' => $changes['agreement_type'],
            ], fn ($v) => $v !== null));

            $inventory->syncOccupancy($tenancy->residential_unit_id, $tenancy->commercial_unit_id);
            if ($agentChanged && $tenancy->assigned_agent_id) {
                app(\App\Services\Tenancy\TenancyNotificationService::class)->tenancyAssigned($tenancy);
            }

            return response()->json([
                'status' => 200,
                'message' => $changed ? 'Tenancy updated. New rent and terms apply to bills generated from now on.' : 'Nothing changed.',
                'changed' => array_keys($changed),
            ]);
        });
    }

    /**
     * Commercial spaces that can still take a tenancy, for the onboarding form. Occupancy comes
     * from ComplexInventoryService so it matches onboarding validation and the inventory view.
     */
    public function commercialUnitsForTenant(string $slug)
    {
        $complex = Complex::forBusiness()->where('slug', $slug)->firstOrFail();
        $inventory = app(\App\Services\Properties\ComplexInventoryService::class);
        $assignment = app(\App\Services\Agents\AgentAssignmentService::class);
        $units = CommercialUnit::with('assignedAgent')->where('complex_id', $complex->id)->orderBy('unit_number')->get()
            ->map(function (CommercialUnit $unit) use ($inventory, $assignment, $complex) {
                $o = $inventory->commercialOccupancy($unit);
                $agent = $assignment->defaultAgentFor($complex, $unit);
                return [
                    'id' => $unit->id, 'unit_number' => $unit->unit_number, 'status' => $unit->status,
                    'unit_sq_ft' => (float) $unit->unit_sq_ft, 'min_sq_ft' => (float) $unit->min_sq_ft,
                    'max_subdivisions' => $o['max_subdivisions'], 'effective_floor' => $unit->custom_floors ?: $unit->floors,
                    'rate_per_sq_ft' => $unit->monthly_rent && $unit->unit_sq_ft ? round((float) $unit->monthly_rent / (float) $unit->unit_sq_ft, 2) : 0,
                    'occupied_count' => $o['occupied_count'], 'total_occupied_sq_ft' => $o['occupied_sq_ft'],
                    'remaining_sq_ft' => $o['remaining_sq_ft'], 'remaining_subdivisions' => $o['remaining_subdivisions'],
                    'occupancy_percentage' => $o['occupancy_percentage'], 'has_space_available' => $o['has_space'],
                    'agent' => $agent ? ['id' => $agent->id, 'name' => $agent->full_name] : null,
                ];
            })->filter(fn ($unit) => $unit['has_space_available'])->values();

        return response()->json(['status' => 200, 'complex' => ['id' => $complex->id, 'slug' => $complex->slug, 'title' => $complex->title, 'commercial_units' => $units]]);
    }

    /**
     * Get complex occupancy data for DataTables
     */
    public function getComplexOccupancyData(Request $request)
    {
        try {
            $userId = business_owner_id();
            
            // Get complexes with basic counts for filtering
            $query = Complex::forBusiness($userId);

            // Get total count before filtering
            $totalRecords = $query->count();
    
            // Apply search functionality
            $searchTerm = $request->input('search.value', '');
            if (!empty($searchTerm)) {
                $query->where(function($q) use ($searchTerm) {
                    $q->whereLike('title', "%{$searchTerm}%")
                      ->orWhereLike('ref_code', "%{$searchTerm}%")
                      ->orWhereLike('type', "%{$searchTerm}%")
                      ->orWhereLike('location_description', "%{$searchTerm}%");
                });
            }
    
            // Get filtered count
            $filteredRecords = $query->count();
    
            // Apply pagination
            $query->skip($request->start)->take($request->length);
            
            // Get complexes for this page
            $complexes = $query->get();
    
            // Get all tenants for the user (needed for the calculation)
            $allTenants = TenantManagement::forBusiness($userId)
                ->with(['complex', 'commercialUnit', 'tenantProfile'])
                ->get();
    
            // Use the existing calculateComplexOccupancy method
            $allComplexOccupancy = $this->overviewTenantStatsService->calculateComplexOccupancy($complexes, $allTenants);

            // Handle sorting for calculated fields if needed
            if ($request->has('order') && count($request->order) > 0) {
                $orderColumnIndex = $request->order[0]['column'];
                $orderDirection = $request->order[0]['dir'];
                
                $sortFields = [
                    0 => 'title',
                    1 => 'type',
                    2 => 'total_capacity',
                    3 => 'total_occupied', 
                    4 => 'occupancy_percentage',
                    7 => 'monthly_revenue',
                ];
                
                if (isset($sortFields[$orderColumnIndex])) {
                    $sortField = $sortFields[$orderColumnIndex];
                    
                    // Sort the occupancy data
                    if (in_array($sortField, ['total_capacity', 'total_occupied', 'occupancy_percentage', 'monthly_revenue'])) {
                        usort($allComplexOccupancy, function($a, $b) use ($sortField, $orderDirection) {
                            $aValue = $a[$sortField];  
                            $bValue = $b[$sortField];
                            
                            if ($orderDirection === 'desc') {
                                return $bValue <=> $aValue;
                            }
                            return $aValue <=> $bValue;
                        });
                    } else {
                        // For non-calculated fields, sort by the field directly
                        usort($allComplexOccupancy, function($a, $b) use ($sortField, $orderDirection) {
                            $aValue = $a[$sortField];
                            $bValue = $b[$sortField];
                            
                            if ($orderDirection === 'desc') {
                                return strcmp($bValue, $aValue);
                            }
                            return strcmp($aValue, $bValue);
                        });
                    }
                }
            } else {
                // Default sorting by occupancy percentage descending
                usort($allComplexOccupancy, function($a, $b) {
                    return $b['occupancy_percentage'] <=> $a['occupancy_percentage'];
                });
            }
    
            // Return the DataTables response format
            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $allComplexOccupancy
            ]);
    
        } catch (\Exception $e) {
            Log::error('Failed to retrieve complex occupancy data: ' . $e->getMessage(), [
                'user_id' => business_owner_id(),
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'draw' => intval($request->draw ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to load complex occupancy data'
            ]);
        }
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    /**
     * Show tenant details
     */
    public function show($id)
    {
        try {
            $tenant = TenantManagement::where('id', $id)
                ->where('managed_by', business_owner_id())
                ->with(['complex', 'commercialUnit', 'payments', 'notices', 'tenantProfile'])
                ->firstOrFail();
    
            return response()->json([
                'status' => 200,
                'message' => 'Tenant details retrieved successfully',
                'tenant' => [
                    // Basic tenant info that your JS expects
                    'id' => $tenant->id,
                    'tenancy_ref' => $tenant->tenancy_ref,
                    'tenant_profile_id' => $tenant->tenant_profile_id,
                    
                    // Tenant Profile Information
                    'tenant_profile' => $tenant->tenantProfile ? [
                        'id' => $tenant->tenantProfile->id,
                        'tenant_ref' => $tenant->tenantProfile->tenant_ref,
                        'tenant_type' => $tenant->tenantProfile->tenant_type,
                        'profile_status' => $tenant->tenantProfile->profile_status,
                        'is_blacklisted' => $tenant->tenantProfile->profile_status === 'Blacklisted',
                        'blacklist_reason' => $tenant->tenantProfile->blacklist_reason,
                        'blacklisted_date' => $tenant->tenantProfile->blacklisted_date,
                        'created_at' => $tenant->tenantProfile->created_at,
                        'updated_at' => $tenant->tenantProfile->updated_at,
                        
                        // Individual tenant fields
                        'tenant_name' => $tenant->tenantProfile->tenant_name,
                        'tenant_email' => $tenant->tenantProfile->tenant_email,
                        'tenant_phone' => $tenant->tenantProfile->tenant_phone,
                        'tenant_id_number' => $tenant->tenantProfile->tenant_id_number,
                        'date_of_birth' => $tenant->tenantProfile->date_of_birth,
                        'gender' => $tenant->tenantProfile->gender,
                        'address' => $tenant->tenantProfile->address,
                        'nationality' => $tenant->tenantProfile->nationality,
                        'occupation' => $tenant->tenantProfile->occupation,
                        'employer' => $tenant->tenantProfile->employer,
                        'monthly_income' => $tenant->tenantProfile->monthly_income,
                        
                        // Business tenant fields
                        'business_name' => $tenant->tenantProfile->business_name,
                        'business_registration' => $tenant->tenantProfile->business_registration,
                        'business_contact_person' => $tenant->tenantProfile->business_contact_person,
                        'business_email' => $tenant->tenantProfile->business_email,
                        'business_phone' => $tenant->tenantProfile->business_phone,
                        'business_address' => $tenant->tenantProfile->business_address,
                        'business_type' => $tenant->tenantProfile->business_type,
                        'tax_id_number' => $tenant->tenantProfile->tax_id_number,
                        'annual_revenue' => $tenant->tenantProfile->annual_revenue,
                        'years_in_business' => $tenant->tenantProfile->years_in_business,
                        
                        // Emergency contact
                        'emergency_contact_name' => $tenant->tenantProfile->emergency_contact_name,
                        'emergency_contact_phone' => $tenant->tenantProfile->emergency_contact_phone,
                        'emergency_contact_relationship' => $tenant->tenantProfile->emergency_contact_relationship,
                        'emergency_contact_address' => $tenant->tenantProfile->emergency_contact_address,
                        
                        // Computed display fields
                        'display_name' => $tenant->tenantProfile->tenant_type === 'Business' 
                            ? $tenant->tenantProfile->business_name 
                            : $tenant->tenantProfile->tenant_name,
                        'primary_email' => $tenant->tenantProfile->tenant_type === 'Business'
                            ? $tenant->tenantProfile->business_email 
                            : $tenant->tenantProfile->tenant_email,
                        'primary_phone' => $tenant->tenantProfile->tenant_type === 'Business'
                            ? $tenant->tenantProfile->business_phone 
                            : $tenant->tenantProfile->tenant_phone,
                    ] : null,
                    
                    // Legacy fields for backward compatibility (from tenant profile)
                    'tenant_type' => $tenant->tenantProfile ? $tenant->tenantProfile->tenant_type : null,
                    'tenant_name' => $tenant->tenantProfile ? $tenant->tenantProfile->tenant_name : null,
                    'tenant_email' => $tenant->tenantProfile ? $tenant->tenantProfile->tenant_email : null,
                    'tenant_phone' => $tenant->tenantProfile ? $tenant->tenantProfile->tenant_phone : null,
                    'tenant_id_number' => $tenant->tenantProfile ? $tenant->tenantProfile->tenant_id_number : null,
                    'business_name' => $tenant->tenantProfile ? $tenant->tenantProfile->business_name : null,
                    'business_registration' => $tenant->tenantProfile ? $tenant->tenantProfile->business_registration : null,
                    'business_contact_person' => $tenant->tenantProfile ? $tenant->tenantProfile->business_contact_person : null,
                    'business_email' => $tenant->tenantProfile ? $tenant->tenantProfile->business_email : null,
                    'business_phone' => $tenant->tenantProfile ? $tenant->tenantProfile->business_phone : null,
                    'emergency_contact_name' => $tenant->tenantProfile ? $tenant->tenantProfile->emergency_contact_name : null,
                    'emergency_contact_phone' => $tenant->tenantProfile ? $tenant->tenantProfile->emergency_contact_phone : null,
                    
                    // Status and payment info
                    'status' => $tenant->status,
                    'monthly_payment' => $tenant->monthly_payment,
                    'security_deposit' => $tenant->security_deposit,
                    'grace_period_days' => $tenant->grace_period_days,
                    'late_fee_amount' => $tenant->late_fee_amount,
                    'penalty_amount' => $tenant->penalty_amount,
                    
                    // Property information
                    'complex' => $tenant->complex ? [
                        'id' => $tenant->complex->id,
                        'title' => $tenant->complex->title,
                        'slug' => $tenant->complex->slug,
                        'type' => $tenant->complex->type,
                        'ref_code' => $tenant->complex->ref_code,
                        'description' => $tenant->complex->description,
                        'email' => $tenant->complex->email,
                        'mobile' => $tenant->complex->mobile,
                        'county' => $tenant->complex->county,
                        'county_specific' => $tenant->complex->county_specific,
                        'location_description' => $tenant->complex->location_description,
                        'class' => $tenant->complex->class
                    ] : null,
                    'commercial_unit' => $tenant->commercialUnit ? [
                        'id' => $tenant->commercialUnit->id,
                        'unit_number' => $tenant->commercialUnit->unit_number,
                        'unit_sq_ft' => $tenant->commercialUnit->unit_sq_ft,
                        'min_sq_ft' => $tenant->commercialUnit->min_sq_ft,
                        'max_subdivisions' => $tenant->commercialUnit->max_subdivisions,
                        'floors' => $tenant->commercialUnit->floors,
                        'custom_floors' => $tenant->commercialUnit->custom_floors,
                        'monthly_rent' => $tenant->commercialUnit->monthly_rent,
                        'status' => $tenant->commercialUnit->status,
                        'description' => $tenant->commercialUnit->description
                    ] : null,
                    'unit_number' => $tenant->unit_number,
                    'specific_unit_number' => $tenant->specific_unit_number,
                    
                    // Property details
                    'listing_type' => $tenant->listing_type,
                    'furnishing' => $tenant->furnishing,
                    'bedrooms' => $tenant->bedrooms,
                    'bathrooms' => $tenant->bathrooms,
                    'sq_area' => $tenant->sq_area,
                    'occupied_sq_area' => $tenant->occupied_sq_area,
                    'rate_per_sq_ft' => $tenant->rate_per_sq_ft,
                    
                    // Agreement details
                    'agreement_type' => $tenant->agreement_type,
                    'lease_start_date' => $tenant->lease_start_date ? $tenant->lease_start_date->format('Y-m-d') : null,
                    'lease_end_date' => $tenant->lease_end_date ? $tenant->lease_end_date->format('Y-m-d') : null,
                    'agreement_start_date' => $tenant->agreement_start_date ? $tenant->agreement_start_date->format('Y-m-d') : null,
                    'agreement_end_date' => $tenant->agreement_end_date ? $tenant->agreement_end_date->format('Y-m-d') : null,
                    'lease_duration_months' => $tenant->lease_duration_months,
                    'move_in_date' => $tenant->move_in_date ? $tenant->move_in_date->format('Y-m-d') : null,
                    
                    // Payment configuration
                    'payment_frequency' => $tenant->payment_frequency,
                    'payment_due_day' => $tenant->payment_due_day,
                    
                    // Occupancy information
                    'number_of_occupants' => $tenant->number_of_occupants,
                    'has_pets' => $tenant->has_pets,
                    'pet_details' => $tenant->pet_details,
                    
                    // Additional information
                    'general_notes' => $tenant->general_notes,
                    'additional_notes' => $tenant->additional_notes,
                    'internal_notes' => $tenant->internal_notes,
                    'special_terms' => $tenant->special_terms,
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                    
                    // Computed fields that your JS helper functions expect
                    'display_name' => $tenant->tenantProfile ? 
                        ($tenant->tenantProfile->tenant_type === 'Business' 
                            ? $tenant->tenantProfile->business_name 
                            : $tenant->tenantProfile->tenant_name) : 'Unknown Tenant',
                    'primary_email' => $tenant->tenantProfile ?
                        ($tenant->tenantProfile->tenant_type === 'Business' 
                            ? $tenant->tenantProfile->business_email 
                            : $tenant->tenantProfile->tenant_email) : null,
                    'primary_phone' => $tenant->tenantProfile ?
                        ($tenant->tenantProfile->tenant_type === 'Business' 
                            ? $tenant->tenantProfile->business_phone 
                            : $tenant->tenantProfile->tenant_phone) : null,
                    
                    // Payment and notice counts
                    'payment_count' => $tenant->payments()->count(),
                    'notice_count' => $tenant->notices()->count(),
                    'latest_payment' => $tenant->payments()->latest('payment_date')->first(),
                    'latest_notice' => $tenant->notices()->latest('notice_date')->first(),
                    
                    // Document count
                    'document_count' => is_array($tenant->uploaded_documents) ? count($tenant->uploaded_documents) : 0,
                    'has_documents' => !empty($tenant->uploaded_documents),
                    'uploaded_documents' => $tenant->uploaded_documents,
                    
                    // Lease status calculations
                    'lease_status' => \App\Support\LeaseStatus::of($tenant),
                    'days_until_expiry' => \App\Support\LeaseStatus::daysLeft($tenant),
                    
                    // Profile status information
                    'profile_status' => $tenant->tenantProfile ? $tenant->tenantProfile->profile_status : 'Unknown',
                    'is_blacklisted' => $tenant->tenantProfile ? 
                        $tenant->tenantProfile->profile_status === 'Blacklisted' : false,
                    'blacklist_reason' => $tenant->tenantProfile ? $tenant->tenantProfile->blacklist_reason : null,
                    'blacklisted_date' => $tenant->tenantProfile && $tenant->tenantProfile->blacklisted_date ? 
                        $tenant->tenantProfile->blacklisted_date->format('Y-m-d') : null,
                ]
            ]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Tenant not found or you do not have permission to view this tenant'
            ], 404);
    
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tenant details: ' . $e->getMessage(), [
                'tenant_id' => $id,
                'user_id' => business_owner_id()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Failed to retrieve tenant details. Please try again.'
            ], 500);
        }
    }
    
    
 
   
    
    
    
    
    
    
    
    
    /**
     * Record a payment for a tenant
     */
    public function recordPayment(Request $request)
    {
        // Kept for older screens: the money goes through the tenant account (oldest invoice first, extra is credit).
        $request->validate([
            'tenant_id' => 'required|integer',
            'invoice_id' => 'nullable|string',
            'payment_date' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . now()->subYears(2)->format('Y-m-d')],
            'amount_paid' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,check,card,other',
            'transaction_reference' => 'nullable|string|max:255',
            'payment_status' => 'nullable|in:paid,partial,pending,confirmed',
            'payment_notes' => 'nullable|string',
            'payment_receipt' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf',
        ]);

        $tenant = TenantManagement::where('id', $request->tenant_id)->where('managed_by', business_owner_id())->firstOrFail();
        $first = $request->invoice_id
            ? TenantInvoice::where('tenancy_id', $tenant->id)->where(fn ($q) => $q->where('invoice_number', $request->invoice_id)->orWhere('id', (int) $request->invoice_id))->value('id')
            : null;
        $receipt = $request->hasFile('payment_receipt')
            ? $request->file('payment_receipt')->store('users/' . business_owner_id() . '/tenants/' . $tenant->tenancy_ref . '/payments/receipts', 'public')
            : null;

        $payment = app(\App\Services\Rent\TenantAccountService::class)->record($tenant, [
            'amount' => $request->amount_paid,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
            'payment_notes' => $request->payment_notes,
            'receipt_path' => $receipt,
            'pending' => $request->payment_status === 'pending',
            'first_invoice_id' => $first,
        ]);

        return response()->json([
            'status' => 200,
            'message' => $payment->payment_status === 'pending' ? 'Payment recorded as awaiting clearance.' : 'Payment recorded successfully',
            'payment' => [
                'id' => $payment->id,
                'amount_paid' => number_format((float) $payment->amount_paid, 2),
                'credit' => number_format((float) $payment->unallocated_amount, 2),
                'payment_date' => $payment->payment_date->format('Y-m-d'),
                'payment_status' => $payment->payment_status,
            ],
        ]);
    }
    
    
    

    /**
     * Delete tenant
     */
    public function destroy($id)
    {
        try {
            $tenant = TenantManagement::where('id', $id)
                ->where('managed_by', business_owner_id())
                ->firstOrFail();
    
            // Store tenant info for logging
            $tenantInfo = [
                'tenant_ref' => $tenant->tenant_ref,
                'tenant_name' => $tenant->tenant_type === 'Business' ? $tenant->business_name : $tenant->tenant_name
            ];
    
            // Delete associated files if any
            if (!empty($tenant->uploaded_documents)) {
                foreach ($tenant->uploaded_documents as $document) {
                    if (isset($document['path']) && Storage::disk('public')->exists($document['path'])) {
                        Storage::disk('public')->delete($document['path']);
                    }
                }
            }
    
            // Delete the tenant (this will cascade delete related records due to foreign key constraints)
            $tenant->delete();
    
            // Log the deletion
            Log::info("Tenant deleted: {$tenantInfo['tenant_ref']}", [
                'tenant_info' => $tenantInfo,
                'deleted_by' => auth()->id()
            ]);
    
            return response()->json([
                'status' => 200,
                'message' => 'Tenant deleted successfully'
            ]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Tenant not found or you do not have permission to delete this tenant'
            ], 404);
    
        } catch (\Exception $e) {
            Log::error('Failed to delete tenant: ' . $e->getMessage(), [
                'tenant_id' => $id,
                'user_id' => business_owner_id()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Failed to delete tenant. Please try again.'
            ], 500);
        }
    }
    
    
    
    
    
    
    
    
}
