<?php

namespace App\Services\Tenancy;

use App\Models\Complex;
use App\Models\CommercialUnit;
use App\Models\ComplexParkingSlot;
use App\Models\ComplexResidentialUnit;
use App\Models\Legal;
use App\Models\TenantLease;
use App\Models\TenantLegalDocument;
use App\Models\TenantManagement;
use App\Models\TenantProfile;
use App\Services\Tenancy\TenantStoreService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Creating a tenancy from the onboarding form, in the caller's transaction: the tenant profile (new or existing),
 * the tenancy on a free unit, parking, documents, the lease, move-in invoices, lead conversion, notifications and
 * the welcome email (after commit).
 *
 * Throws TenancyOnboardingRejected for a request that cannot go through (with the message to show), and
 * ValidationException for field errors. Uploaded documents are removed when it fails.
 */
class TenancyOnboardingService
{
    public function __construct(private TenantStoreService $tenantStore) {}

    /** @return array the JSON the onboarding page expects on success */
    public function onboard(Request $request): array
    {
            // Converting a lead (Convert to tenant, or a lead picked on the form).
            $conversionLeadId = $request->input('lead_id') ?: ($request->tenant_type === 'Business' ? $request->input('business_lead_id') : $request->input('individual_lead_id'));
            $conversionLead = null;
            if ($conversionLeadId) {
                $conversionLead = \App\Models\Lead::forBusiness()->lockForUpdate()->find((int) $conversionLeadId);
                if (!$conversionLead) {
                    throw ValidationException::withMessages(['lead_id' => 'Choose a lead of this business.']);
                }
                if ($conversionLead->converted_tenant_id) {
                    throw ValidationException::withMessages(['lead_id' => 'This lead has already been converted to a tenant.']);
                }
                if (!$conversionLead->readyToConvert()) {
                    throw ValidationException::withMessages(['lead_id' => 'Before converting this lead, ' . implode(' and ', $conversionLead->missingForConversion()) . '.']);
                }
                // The lead's agent is credited unless another agent was chosen.
                if (!$request->filled('assigned_agent_id') && $conversionLead->assigned_agent_id) {
                    $request->merge(['assigned_agent_id' => $conversionLead->assigned_agent_id]);
                }
            }

            $data = $request->all();
            $tenantProfileId = null;
        
            try {
        
                // Handle tenant profile creation or selection
                if ($request->tenant_option === 'existing') {
                    // Verify the existing tenant profile belongs to this business user
                    $existingProfile = TenantProfile::where('id', $request->existing_tenant_id)
                        ->where('managed_by', business_owner_id())
                        ->firstOrFail();
                        
                    if ($existingProfile->profile_status === 'Blacklisted') {
                        throw ValidationException::withMessages(['existing_tenant_id' => 'This tenant profile is blacklisted. Review the profile before onboarding.']);
                    }
                    if ($existingProfile->tenant_type !== $request->tenant_type) {
                        throw ValidationException::withMessages(['existing_tenant_id' => 'Select a tenant that matches the chosen tenant type.']);
                    }
                    $tenantProfileId = $existingProfile->id;
                    
                    // Check for duplicate tenancy with same profile
                    $existingTenancy = TenantManagement::where('tenant_profile_id', $tenantProfileId)
                        ->where('managed_by', business_owner_id())
                        ->where('status', 'Active')
                        ->first();

                    Log::info('Existing tenancy check for tenant profile ID ' . $tenantProfileId . ': ' . ($existingTenancy ? 'Found' : 'Not Found'));
                    
                } else {
                    // Create new tenant profile
                    $profileData = [
                        'tenant_type' => $data['tenant_type'],
                        'managed_by' => business_owner_id(),
                        'created_by' => business_owner_id(),
                        'updated_by' => auth()->id(),
                    ];
        
                    // Handle individual or business fields
                    if ($data['tenant_type'] === 'Individual') {
                        $profileData = array_merge($profileData, [
                            'tenant_name' => $data['tenant_name'],
                            'tenant_email' => $data['tenant_email'],
                            'tenant_phone' => $data['tenant_phone'],
                            'tenant_id_number' => $data['tenant_id_number'],
                            'date_of_birth' => $data['date_of_birth'] ?? null,
                            'gender' => $data['gender'] ?? null,
                            'nationality' => $data['nationality'] ?? null,
                            'occupation' => $data['occupation'] ?? null,
                            'employer' => $data['employer'] ?? null,
                            'monthly_income' => $data['monthly_income'] ?? null,
                        ]);
                    } else {
                        $profileData = array_merge($profileData, [
                            'business_name' => $data['business_name'],
                            'business_registration' => $data['business_registration'],
                            'business_contact_person' => $data['business_contact_person'],
                            'business_email' => $data['business_email'],
                            'business_phone' => $data['business_phone'],
                            'business_type' => $data['business_type'] ?? null,
                            'tax_id_number' => $data['tax_id_number'] ?? null,
                            'annual_revenue' => $data['annual_revenue'] ?? null,
                            'years_in_business' => $data['years_in_business'] ?? null,
                        ]);
                    }
        
                    // Add emergency contact info
                    $profileData = array_merge($profileData, [
                        'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                        'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                        'emergency_contact_relationship' => $data['emergency_contact_relationship'] ?? null,
                    ]);
        
                    // Generate unique tenant reference for profile
                    $profileData['tenant_ref'] = $this->tenantStore->generateTenantProfileReference();

                    // Link lead if provided
                    $profileData['lead_id'] = $conversionLead?->id;

        
                    // Check for existing tenant profile with same email/ID to prevent duplicates
                    if ($data['tenant_type'] === 'Individual') {
                        $existingProfile = TenantProfile::forBusiness()
                            ->where(fn ($query) => $query->where('tenant_email', $data['tenant_email'])
                                ->orWhere('tenant_id_number', $data['tenant_id_number']))
                            ->first();
                    } else {
                        $existingProfile = TenantProfile::forBusiness()
                            ->where(fn ($query) => $query->where('business_email', $data['business_email'])
                                ->orWhere('business_registration', $data['business_registration']))
                            ->first();
                    }
        
                    if ($existingProfile) {
                        throw new TenancyOnboardingRejected('A tenant profile with this email or ID number already exists. Please use the existing tenant option instead.');
                    }
        
                    // Create the tenant profile
                    $tenantProfile = TenantProfile::create($profileData);
                    $tenantProfileId = $tenantProfile->id;
                }

                $user_id =business_owner_id();
        
                // Now create the tenant management record
                $tenantManagementData = [
                    'tenant_profile_id' => $tenantProfileId,
                    'tenancy_ref' => $this->tenantStore->generateTenancyReference(),
                    'managed_by' => business_owner_id(),
                    'created_by' => business_owner_id(),
                    'updated_by' => auth()->id(),
                ];
        
                // Convert complex slug to complex_id
                if (!empty($data['complex'])) {
                    $complex = Complex::where('slug', $data['complex'])
                        ->where('user_id', business_owner_id())
                        ->lockForUpdate()->first();
                    
                    if ($complex) {
                        if (($complex->setup_status ?? 'draft') !== 'ready' || !app(\App\Services\Properties\ComplexSetupService::class)->ready($complex)) {
                            throw new TenancyOnboardingRejected('Confirm the complex setup before onboarding a tenant.');
                        }

                        $tenantManagementData['complex_id'] = $complex->id;
                        $tenantManagementData['complex_name'] = $complex->title;
                    } else {
                        throw new TenancyOnboardingRejected('Invalid complex selected');
                    }
                }              

                $isCommercial = $data['listing_type'] === 'Commercial Unit';
                $propertyTypes = array_map('trim', explode(',', $complex->type ?? ''));
                if (!in_array($data['listing_type'], $propertyTypes, true)) {
                    throw ValidationException::withMessages(['listing_type' => 'Choose a property type that belongs to the selected property.']);
                }
                if (!$isCommercial && empty($data['residential_unit_id'])
                    && TenantManagement::where('complex_id', $complex->id)->whereNull('residential_unit_id')->whereNull('commercial_unit_id')->where('status', 'Active')->exists()) {
                    throw ValidationException::withMessages(['complex' => 'This whole property already has an active tenancy.']);
                }

                if (($isCommercial && !empty($data['residential_unit_id'])) || (!$isCommercial && !empty($data['commercial_unit_id']))) {
                    throw new TenancyOnboardingRejected('Choose a unit that matches the selected property type.');
                }
                if (!$isCommercial && empty($data['residential_unit_id'])
                    && ComplexResidentialUnit::where('complex_id', $complex->id)->exists()) {
                    throw new TenancyOnboardingRejected('Select an available residential unit before creating this tenancy.');
                }
                $parkingIds = collect($data['parking_slot'] ?? [])->map(fn ($id) => (int) $id);
                if ($parkingIds->isNotEmpty()) {
                    $parkingSlots = ComplexParkingSlot::where('complex_id', $complex->id)
                        ->whereIn('id', $parkingIds)->lockForUpdate()->get();
                    if ($parkingSlots->count() !== $parkingIds->count()
                        || $parkingSlots->contains(fn ($slot) => $slot->is_occupied || $slot->occupied_tenant_id !== null)) {
                        throw new TenancyOnboardingRejected('One or more selected parking spaces are no longer available. Please select available spaces at this property.');
                    }
                }

                if (!empty($data['residential_unit_id'])) {
                    $residentialUnit = ComplexResidentialUnit::where('id', $data['residential_unit_id'])
                        ->where('complex_id', $tenantManagementData['complex_id'] ?? null)
                        ->lockForUpdate()
                        ->first();

                    if (!$residentialUnit || $residentialUnit->status !== 'available' || $residentialUnit->activeTenancies()->exists()) {
                        throw new TenancyOnboardingRejected('The selected residential unit is no longer available.');
                    }
                    if (!$residentialUnit->forRent()) {
                        throw new TenancyOnboardingRejected('House ' . $residentialUnit->unit_number . ' is for sale only, so it cannot be let. Mark it "for rent or sale" first.');
                    }
                }

                if (!empty($data['commercial_unit_id'])) {
                    $commercialUnit = CommercialUnit::where('id', $data['commercial_unit_id'])
                        ->where('complex_id', $tenantManagementData['complex_id'] ?? null)
                        ->lockForUpdate()
                        ->first();

                    if (!$commercialUnit) {
                        throw new TenancyOnboardingRejected('The selected commercial unit is not available for onboarding.');
                    }
                    // Minimum area, free area and subdivisions, counted the same way everywhere.
                    app(\App\Services\Properties\ComplexInventoryService::class)->assertCommercialCapacity($commercialUnit, (float) ($data['occupied_sq_area'] ?? 0));
                }

                // The selected inventory record supplies physical specifications.
                if (isset($residentialUnit)) {
                    $data['unit_number'] = $residentialUnit->unit_number;
                    $data['bedrooms'] = $residentialUnit->bedrooms ?? $residentialUnit->complexUnit?->bedrooms;
                    $data['bathrooms'] = $residentialUnit->bathrooms ?? $residentialUnit->complexUnit?->bathrooms;
                    $data['sq_area'] = $residentialUnit->sq_ft ?? $residentialUnit->complexUnit?->sq_ft;
                } elseif (isset($commercialUnit)) {
                    $data['unit_number'] = $commercialUnit->unit_number;
                }

                // Property Information
                $tenantManagementData = array_merge($tenantManagementData, [
                    'listing_type' => $data['listing_type'],
                    'furnishing' => $data['furnishing'] ?? null,
                    'bedrooms' => $data['bedrooms'] ?? null,
                    'bathrooms' => $data['bathrooms'] ?? null,
                    'sq_area' => $data['sq_area'] ?? null,
                    'unit_number' => $data['unit_number'] ?? null,
                    'commercial_unit_id' => $data['commercial_unit_id'] ?? null,
                    'residential_unit_id' => $data['residential_unit_id'] ?? null,
                    'occupied_sq_area' => $data['occupied_sq_area'] ?? null,
                    'rate_per_sq_ft' => $data['rate_per_sq_ft'] ?? null,
                    'specific_unit_number' => $data['specific_unit_number'] ?? null,
                    'service_charge_per_sq_ft' => $data['service_charge_per_sq_ft'] ?? null,
                    'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
                ]);
        
                // Agreement Details
                $tenantManagementData = array_merge($tenantManagementData, [
                    'agreement_type' => ucfirst($data['agreement_type']), // Convert to proper case
                    'currency' => $data['currency'],
                    'monthly_payment' => $data['monthly_payment'],
                    'security_deposit' => $data['security_deposit'] ?? 0,
                    'move_in_date' => $data['start_date'],
                    'lease_start_date' => $data['start_date'],
                    'lease_end_date' => $data['end_date'] ?? null,
                    'agreement_start_date' => $data['start_date'],
                    'agreement_end_date' => $data['end_date'] ?? null,
                    'grace_period_days' => $data['grace_period'] ?? 0,
                    'build_out_period_days' => $data['build_out_period'] ?? null,
                    'rent_paid_up_to' => $data['rent_paid_up_to'] ?? null,
                    'late_fee_amount' => $data['late_fee_amount'] ?? null,
                    'penalty_amount' => $data['penalty_amount'] ?? null,
                ]);
        
                // Additional Information
                $tenantManagementData = array_merge($tenantManagementData, [
                    'number_of_occupants' => $data['number_of_occupants'] ?? 1,
                    'has_pets' => filter_var($data['has_pets'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'pet_details' => $data['pet_details'] ?? null,
                    'general_notes' => $data['notes'] ?? null,
                    'additional_notes' => $data['notes'] ?? null,
                    'status' => 'Active',
                    'payment_frequency' => $data['payment_interval'] ?? 'Monthly',
                    'tenancy_quarterly_payment_type' => $data['tenancy_quarterly_payment_type'] ?? null,
                    'payment_due_day' => 1,
                ]);
        
                // Calculate lease duration if end date is provided
                if (!empty($data['end_date'])) {
                    $startDate = Carbon::parse($data['start_date']);
                    $endDate = Carbon::parse($data['end_date']);
                    $tenantManagementData['lease_duration_months'] = (int) round($startDate->diffInMonths($endDate));
                }
        
                // Handle file uploads with tenant-specific folder structure
                if ($request->hasFile('documents')) {
                    // Get tenant name for folder structure
                    $tenantName = '';
                    if ($request->tenant_option === 'existing') {
                        $profile = TenantProfile::find($tenantProfileId);
                        $tenantName = $profile->display_name;
                    } else {
                        $tenantName = ($data['tenant_type'] === 'Business' && !empty($data['business_name'])) 
                            ? $data['business_name'] 
                            : $data['tenant_name'];
                    }
                    
                    $documentPaths = $this->tenantStore->handleTenantDocumentUpload(
                        $request->file('documents'), 
                        $tenantManagementData['tenancy_ref'], 
                        $tenantName
                    );
                    $storedPaths = collect($documentPaths)->pluck('path')->filter(fn ($path) => is_string($path) && $path !== '')->unique();
                    if ($storedPaths->count() !== count($request->file('documents'))) {
                        throw ValidationException::withMessages(['documents' => 'Some documents could not be uploaded. Check the file sizes and storage allowance, then retry.']);
                    }
                    $tenantManagementData['uploaded_documents'] = $documentPaths;
                }

                // Create the tenant management record
                $tenantManagement = TenantManagement::create($tenantManagementData);

                // Handling Parking Slots
                $parking_slot = $request->input('parking_slot', []) ?? [];
                foreach($parking_slot as $slot){
                    $id = (int)$slot;
                    ComplexParkingSlot::where('id', $id)->update([
                        'is_occupied' => true,
                        'occupied_tenant_id' => $tenantManagement->id,
                    ]);
                }

                app(\App\Services\Properties\ComplexInventoryService::class)->syncOccupancy($tenantManagement->residential_unit_id, $tenantManagement->commercial_unit_id);

                if ($conversionLead) {
                    app(\App\Services\Crm\LeadConversionService::class)->markConverted($conversionLead, $tenantManagement);
                }
                // Confirmation to the agent credited with the tenancy (and their lead agent).
                app(\App\Services\Tenancy\TenancyNotificationService::class)->tenancyAssigned($tenantManagement);

                // Move-in invoices: the deposit and the first rent period (billed in advance); periods already
                // paid before Erty ("rent paid up to") are recorded as settled. The email goes out after commit.
                $moveInInvoices = app(\App\Services\Rent\TenantChargesService::class)->startBilling($tenantManagement, notify: false);
                DB::afterCommit(fn () => app(\App\Services\Rent\TenantChargesService::class)->notify($tenantManagement->fresh(), $moveInInvoices));

                // Create Lease Record if lease dates are provided
                $lease_type = (!empty($data['start_date']) && !empty($data['end_date']) ? 'fixed-term' : 'month-to-month');
                $leaseData = [
                    'tenant_management_id' => $tenantManagement->id,
                    'business_id' => auth()->user()->business->id,
                    'lease_start_date' => $data['start_date'],
                    'lease_end_date' => $data['end_date'] ?? null,
                    'rent_amount' => $data['monthly_payment'],
                    'security_deposit' => $data['security_deposit'] ?? 0,
                    'currency' => $data['currency'] ?? default_currency(),
                    'payment_frequency' => strtolower($data['payment_interval']),
                    'payment_day_of_month' => $data['payment_due_day'] ?? 1,
                    'late_fee_amount' => $data['late_fee_amount'] ?? 0,
                    'grace_period_days' => $data['grace_period'] ?? 0,
                    'lease_type' => $lease_type,
                    'agreement_type' => ucfirst($data['agreement_type']),
                    'status' => 'active',
                    'auto_renewal' => $data['auto_renewal'] ?? false,
                    'special_terms' => $data['notes'] ?? null,
                ];

                // Calculate lease duration in months
                $startDate = Carbon::parse($data['start_date']);
                if (!empty($data['end_date'])) {
                    $endDate = Carbon::parse($data['end_date']);
                    $leaseData['lease_duration_months'] = (int) round($startDate->diffInMonths($endDate));
                }

                // Create the lease record
                $lease = TenantLease::create($leaseData);

                Log::info('Lease record created for tenancy', [
                    'tenant_management_id' => $tenantManagement->id,
                    'lease_start_date' => $data['start_date'],
                    'lease_end_date' => $data['end_date'] ?? null,
                ]);

                // Handling Legal Documents
                $legal_files = $request->legal_files;
                if (!empty($legal_files) && is_array($legal_files)) {
                    foreach ($legal_files as $file) {
                        $legal_document_id = Legal::where([['user_id', $user_id],['file_name', $file]] )->first();
                        TenantLegalDocument::create([
                            'tenant_management_id' => $tenantManagement->id,
                            'legal_document_id' => $legal_document_id->id,
                        ]);
                    }
                }

                // Send welcome email to the tenant
                $tenantDetails = TenantProfile::where('id', $tenantProfileId)->first();
                DB::afterCommit(function () use ($tenantManagement, $tenantDetails, $legal_files) {
                    try {
                        $this->tenantStore->sendWelcomeEmail($tenantManagement, $tenantDetails, $legal_files);
                    } catch (\Throwable $exception) {
                        Log::warning('Tenancy created but welcome email failed', ['tenancy_id' => $tenantManagement->id, 'error' => $exception->getMessage()]);
                    }
                });

                // Log the creation
                $logMessage = $request->tenant_option === 'existing' 
                    ? "New tenancy created for existing tenant profile ID: {$tenantProfileId}"
                    : "New tenant profile and tenancy created";
                
                Log::info($logMessage, [
                    'tenant_management_id' => $tenantManagement->id,
                    'tenant_profile_id' => $tenantProfileId,
                    'tenancy_ref' => $tenantManagement->tenancy_ref,
                    'tenant_option' => $request->tenant_option,
                    'created_by' => business_owner_id()
                ]);
                
                $responseMessage = $request->tenant_option === 'existing'
                    ? 'New tenancy created for existing tenant successfully!'
                    : 'New tenant profile and tenancy created successfully!';
                
                return [
                    'status' => 200,
                    'message' => $responseMessage,
                    'encrypted_lease_id' => encrypt($lease->id),
                    'encryped_lease_id' => encrypt($lease->id),
                    'tenant_management' => $tenantManagement->fresh()->load('tenantProfile'),
                    'tenancy_ref' => $tenantManagement->tenancy_ref,
                    'tenant_option' => $request->tenant_option
                ];
                
            } catch (\Exception $e) {
                foreach (collect($documentPaths ?? [])->pluck('path')->filter(fn ($path) => is_string($path) && $path !== '')->unique() as $path) {
                    Storage::disk('public')->delete($path);
                }
                throw $e;
            }
    }
}
