<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\ComplexParkingSlot;
use App\Models\TenantBilling;
use App\Models\TenantInvoice;
use App\Models\TenantLease;
use App\Models\TenantLegalDocument;
use App\Models\TenantManagement;
use App\Models\TenantNotice;
use App\Models\TenantPayment;
use App\Models\TenantProfile;
use App\Models\TenantTermination;
use App\Services\Tenancy\TenancyDetailsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** The tenancy page and the tenant profile: viewing, editing, and payment and notice history. */
class TenantProfileController extends Controller
{
    public function __construct(
        private TenancyDetailsService $tenancyDetailsService,
    ) {
        $this->middleware('auth');
    }

    /**
     * Display the specified tenant with all related details.
     */
    public function tenancyView($ref)
    {
        try {
            $user_id = business_owner_id();
    
            // Update to use tenancy_ref instead of tenant_ref
            $tenant = TenantManagement::where('tenant_management.tenancy_ref', $ref)
            ->where('tenant_management.managed_by', $user_id)
            ->leftJoin('tenant_profiles', 'tenant_management.tenant_profile_id', '=', 'tenant_profiles.id')
            ->leftJoin('complexes', 'tenant_management.complex_id', '=', 'complexes.id')
            ->leftJoin('commercial_units', 'tenant_management.commercial_unit_id', '=', 'commercial_units.id')
            ->leftJoin('users as managers', 'tenant_management.managed_by', '=', 'managers.id')
            ->select([
                // Tenant Management fields - select all
                'tenant_management.*',
                
                // Tenant Profile fields
                'tenant_profiles.id as profile_id',
                'tenant_profiles.tenant_ref as tenant_profile_ref',
                'tenant_profiles.tenant_type',
                'tenant_profiles.tenant_name',
                'tenant_profiles.tenant_email',
                'tenant_profiles.tenant_phone',
                'tenant_profiles.tenant_id_number',
                'tenant_profiles.date_of_birth',
                'tenant_profiles.gender',
                'tenant_profiles.address',
                'tenant_profiles.nationality',
                'tenant_profiles.occupation',
                'tenant_profiles.employer',
                'tenant_profiles.monthly_income',
                'tenant_profiles.business_name',
                'tenant_profiles.business_registration',
                'tenant_profiles.business_contact_person',
                'tenant_profiles.business_email',
                'tenant_profiles.business_phone',
                'tenant_profiles.business_address',
                'tenant_profiles.business_type',
                'tenant_profiles.tax_id_number',
                'tenant_profiles.annual_revenue',
                'tenant_profiles.years_in_business',
                'tenant_profiles.emergency_contact_name',
                'tenant_profiles.emergency_contact_phone',
                'tenant_profiles.emergency_contact_relationship',
                'tenant_profiles.emergency_contact_address',
                'tenant_profiles.profile_status',
                'tenant_profiles.blacklist_reason',
                'tenant_profiles.blacklisted_date',
                
                // Complex fields
                'complexes.id as complex_id',
                'complexes.title as complex_title',
                'complexes.type as complex_type',
                'complexes.slug as complex_slug',
                'complexes.ref_code as complex_ref_code',
                'complexes.description as complex_description',
                'complexes.email as complex_email',
                'complexes.mobile as complex_mobile',
                'complexes.county as complex_county',
                'complexes.county_specific as complex_county_specific',
                'complexes.location_description as complex_location_description',
                'complexes.longitude as complex_longitude',
                'complexes.latitude as complex_latitude',
                'complexes.class as complex_class',
                
                // Commercial Unit fields
                'commercial_units.id as unit_id',
                'commercial_units.unit_number as unit_unit_number',
                'commercial_units.unit_sq_ft',
                'commercial_units.min_sq_ft',
                'commercial_units.max_subdivisions',
                'commercial_units.floors as unit_floors',
                'commercial_units.custom_floors',
                'commercial_units.monthly_rent as unit_monthly_rent',
                'commercial_units.status as unit_status',
                'commercial_units.description as unit_description'
                
                
            ])
            ->firstOrFail();
            
            
            // Construct tenant profile object
            $tenant->tenantProfile = null;
            if ($tenant->profile_id) {
                $tenant->tenantProfile = (object) [
                    'id' => $tenant->profile_id,
                    'tenant_ref' => $tenant->tenant_profile_ref,
                    'tenant_type' => $tenant->tenant_type,
                    'tenant_name' => $tenant->tenant_name,
                    'tenant_email' => $tenant->tenant_email,
                    'tenant_phone' => $tenant->tenant_phone,
                    'tenant_id_number' => $tenant->tenant_id_number,
                    'date_of_birth' => $tenant->date_of_birth,
                    'gender' => $tenant->gender,
                    'address' => $tenant->address,
                    'nationality' => $tenant->nationality,
                    'occupation' => $tenant->occupation,
                    'employer' => $tenant->employer,
                    'monthly_income' => $tenant->monthly_income,
                    'business_name' => $tenant->business_name,
                    'business_registration' => $tenant->business_registration,
                    'business_contact_person' => $tenant->business_contact_person,
                    'business_email' => $tenant->business_email,
                    'business_phone' => $tenant->business_phone,
                    'business_address' => $tenant->business_address,
                    'business_type' => $tenant->business_type,
                    'tax_id_number' => $tenant->tax_id_number,
                    'annual_revenue' => $tenant->annual_revenue,
                    'years_in_business' => $tenant->years_in_business,
                    'emergency_contact_name' => $tenant->emergency_contact_name,
                    'emergency_contact_phone' => $tenant->emergency_contact_phone,
                    'emergency_contact_relationship' => $tenant->emergency_contact_relationship,
                    'emergency_contact_address' => $tenant->emergency_contact_address,
                    'profile_status' => $tenant->profile_status,
                    'blacklist_reason' => $tenant->blacklist_reason,
                    'blacklisted_date' => $tenant->blacklisted_date,
                    // Computed fields
                    'display_name' => $tenant->tenant_type === 'Business' ? $tenant->business_name : $tenant->tenant_name,
                    'primary_email' => $tenant->tenant_type === 'Business' ? $tenant->business_email : $tenant->tenant_email,
                    'primary_phone' => $tenant->tenant_type === 'Business' ? $tenant->business_phone : $tenant->tenant_phone,
                ];
            }

            // Construct tenancy lease object
            $tenant->lease = null;
            $leaseData = TenantLease::where('tenant_management_id', $tenant->id)->first();
            if ($leaseData) {
                $tenant->lease = (object) [
                    'id' => $leaseData->id,
                    'encrypted_lease_id' => encrypt($leaseData->id),
                    'lease_start_date' => $leaseData->lease_start_date,
                    'lease_end_date' => $leaseData->lease_end_date,
                    'lease_duration_months' => $leaseData->lease_duration_months,
                    'rent_amount' => $leaseData->rent_amount,
                    'security_deposit' => $leaseData->security_deposit,
                    'currency' => $leaseData->currency,
                    'payment_frequency' => $leaseData->payment_frequency,
                    'payment_day_of_month' => $leaseData->payment_day_of_month,
                    'late_fee_amount' => $leaseData->late_fee_amount,
                    'grace_period_days' => $leaseData->grace_period_days,
                    'lease_type' => $leaseData->lease_type,
                    'status' => $leaseData->status,
                    'auto_renewal' => $leaseData->auto_renewal,
                    'renewal_notice_days' => $leaseData->renewal_notice_days,
                    'renewal_rent_increase_percentage' => $leaseData->renewal_rent_increase_percentage,
                    'special_terms' => $leaseData->special_terms,
                    'terms_and_conditions' => $leaseData->terms_and_conditions,
                    'utilities_included' => $leaseData->utilities_included,
                    'restrictions' => $leaseData->restrictions,
                    'created_at' => $leaseData->created_at,
                    'updated_at' => $leaseData->updated_at,
                ];
            }

            // Construct complex object
            $tenant->complex = null;
            if ($tenant->complex_id) {
                $tenant->complex = (object) [
                    'id' => $tenant->complex_id,
                    'title' => $tenant->complex_title,
                    'type' => $tenant->complex_type,
                    'slug' => $tenant->complex_slug,
                    'ref_code' => $tenant->complex_ref_code,
                    'description' => $tenant->complex_description,
                    'email' => $tenant->complex_email,
                    'mobile' => $tenant->complex_mobile,
                    'county' => $tenant->complex_county,
                    'county_specific' => $tenant->complex_county_specific,
                    'location_description' => $tenant->complex_location_description,
                    'longitude' => $tenant->complex_longitude,
                    'latitude' => $tenant->complex_latitude,
                    'class' => $tenant->complex_class,
                ];
            }
            
            // Construct commercial unit object
            $tenant->commercialUnit = null;
            if ($tenant->unit_id) {
                $tenant->commercialUnit = (object) [
                    'id' => $tenant->unit_id,
                    'unit_number' => $tenant->unit_unit_number,
                    'unit_sq_ft' => $tenant->unit_sq_ft,
                    'min_sq_ft' => $tenant->min_sq_ft,
                    'max_subdivisions' => $tenant->max_subdivisions,
                    'floors' => $tenant->unit_floors,
                    'custom_floors' => $tenant->custom_floors,
                    'monthly_rent' => $tenant->unit_monthly_rent,
                    'status' => $tenant->unit_status,
                    'description' => $tenant->unit_description,
                ];
            }
            
            // Construct manager object
            $tenant->manager = null;
            if ($tenant->manager_name) {
                $tenant->manager = (object) [
                    'id' => $tenant->managed_by,
                    'name' => $tenant->manager_name,
                    'email' => $tenant->manager_email,
                ];
            }
            
            // Clean up the extra joined fields from the main object to avoid confusion
            unset(
                $tenant->profile_id, $tenant->tenant_profile_ref, $tenant->tenant_type,
                $tenant->tenant_name, $tenant->tenant_email, $tenant->tenant_phone,
                $tenant->tenant_id_number, $tenant->date_of_birth, $tenant->gender,
                $tenant->address, $tenant->nationality, $tenant->occupation,
                $tenant->employer, $tenant->monthly_income, $tenant->business_name,
                $tenant->business_registration, $tenant->business_contact_person,
                $tenant->business_email, $tenant->business_phone, $tenant->business_address,
                $tenant->business_type, $tenant->tax_id_number, $tenant->annual_revenue,
                $tenant->years_in_business, $tenant->emergency_contact_name,
                $tenant->emergency_contact_phone, $tenant->emergency_contact_relationship,
                $tenant->emergency_contact_address, $tenant->profile_status,
                $tenant->blacklist_reason, $tenant->blacklisted_date, $tenant->complex_title, $tenant->complex_type, 
                $tenant->complex_slug, $tenant->complex_ref_code, $tenant->complex_description,
                $tenant->complex_email, $tenant->complex_mobile, $tenant->complex_county,
                $tenant->complex_county_specific, $tenant->complex_location_description,
                $tenant->complex_longitude, $tenant->complex_latitude, $tenant->complex_class,
                $tenant->unit_id, $tenant->unit_unit_number, $tenant->unit_sq_ft,
                $tenant->min_sq_ft, $tenant->max_subdivisions, $tenant->unit_floors,
                $tenant->custom_floors, $tenant->unit_monthly_rent, $tenant->unit_status,
                $tenant->unit_description, $tenant->manager_name, $tenant->manager_email
            );

    
            // Get notice statistics with error handling
            $noticeStats = [];
            try {
                $noticeStats = $this->tenancyDetailsService->getNoticeStatistics($tenant->id);
            } catch (\Exception $e) {
                Log::info('Notice statistics failed: ' . $e->getMessage());
                $noticeStats = [
                    'total_notices' => 0,
                    'notices_this_year' => 0,
                    'notice_types' => [],
                    'last_notice_date' => null,
                    'pending_notices' => 0,
                    'sent_notices' => 0,
                ];
            }
    
            // Calculate lease information
            $leaseInfo = $this->tenancyDetailsService->calculateLeaseInformation($tenant);
    
            // Get commercial unit occupancy details if applicable
            $unitOccupancyDetails = null;
            if ($tenant->commercial_unit_id) {
                try {
                    $unitOccupancyDetails = $this->tenancyDetailsService->getCommercialUnitOccupancyDetails($tenant->commercial_unit_id, $user_id);
                } catch (\Exception $e) {
                    Log::info('Commercial unit occupancy details failed: ' . $e->getMessage());
                    $unitOccupancyDetails = null;
                }
            }
    
            // Get recent activity with error handling
            $recentActivity = [];
            try {
                $recentActivity = $this->tenancyDetailsService->getRecentActivity($tenant->id);
            } catch (\Exception $e) {
                Log::info('Recent activity failed: ' . $e->getMessage());
                $recentActivity = [];
            }
    
            // Calculate financial summary with error handling
            $financialSummary = [];
            try {
                $financialSummary = $this->tenancyDetailsService->calculateFinancialSummary($tenant->id);
            } catch (\Exception $e) {
                Log::info('Financial summary failed: ' . $e->getMessage());
                $financialSummary = [
                    'total_revenue' => 0,
                    'revenue_this_year' => 0,
                    'revenue_this_month' => 0,
                    'total_late_fees' => 0,
                    'outstanding_balance' => 0,
                    'average_monthly_payment' => $tenant->monthly_payment ?? 0,
                ];
            }
    
            // Transform uploaded documents for display
            $documents = $this->tenancyDetailsService->transformDocumentsForDisplay($tenant->uploaded_documents ?? []);

            // Get all notices for this tenant
            $notices = [];
            try {
                $notices = TenantNotice::where('tenant_id', $tenant->id)
                    ->with(['issuedBy'])
                    ->orderBy('notice_date', 'desc')
                    ->get()
                    ->map(function($notice) {
                        return [
                            'id' => $notice->id,
                            'notice_type' => $notice->notice_type,
                            'notice_date' => $notice->notice_date,
                            'effective_date' => $notice->effective_date,
                            'notice_period' => $notice->notice_period,
                            'notice_subject' => $notice->notice_subject,
                            'notice_details' => $notice->notice_details,
                            'delivery_methods' => $notice->delivery_methods ?? [],
                            'delivery_status' => $notice->delivery_status,
                            'sent_at' => $notice->sent_at,
                            'priority_level' => $notice->priority_level ?? 'medium',
                            'response_required' => $notice->response_required ?? false,
                            'response_deadline' => $notice->response_deadline,
                            'follow_up_required' => $notice->follow_up_required ?? false,
                            'follow_up_date' => $notice->follow_up_date,
                            'escalation_level' => $notice->escalation_level,
                            'legal_reference' => $notice->legal_reference,
                            'amount_involved' => $notice->amount_involved,
                            'contact_person' => $notice->contact_person,
                            'contact_phone' => $notice->contact_phone,
                            'internal_notes' => $notice->internal_notes,
                            'issued_by' => $notice->issuedBy ? $notice->issuedBy->name : null,
                            'created_at' => $notice->created_at,
                            'updated_at' => $notice->updated_at,
                            'notice_type_label' => ucwords(str_replace('_', ' ', $notice->notice_type ?? '')),
                            'delivery_status_label' => ucfirst($notice->delivery_status ?? 'pending'),
                            'priority_badge_class' => match($notice->priority_level ?? 'medium') {
                                'low' => 'badge-light-info',
                                'medium' => 'badge-light-primary',
                                'high' => 'badge-light-warning',
                                'urgent' => 'badge-light-danger',
                                default => 'badge-light-secondary'
                            },
                            'delivery_methods_list' => is_array($notice->delivery_methods) 
                                ? implode(', ', array_map('ucfirst', $notice->delivery_methods))
                                : '',
                        ];
                    });
            } catch (\Exception $e) {
                Log::info('Failed to load notices: ' . $e->getMessage());
                $notices = collect();
            }

            // Get legal documents assigned and sent to this tenant
            $tenant_assigned_legal_documents = TenantLegalDocument::join('legals', 'tenant_legal_documents.legal_document_id', 'legals.id')
                ->join('legal_types', 'legals.legal_type_id', 'legal_types.id')
                ->join('legal_folders', 'legals.legal_folder_id', 'legal_folders.id')
                ->select('legals.file_name', 'legal_types.legal_type', 'legal_types.description', 'legal_folders.name', 'legal_folders.slug')
                ->where('tenant_legal_documents.tenant_management_id', $tenant->id)
                ->get();


             // Get parking slot info if applicable
            $tenant_assigned_parking_info = ComplexParkingSlot::where('complex_id', $tenant->complex_id)
                ->where('is_occupied', true)
                ->where('occupied_tenant_id', $tenant->id)
                ->get();

            // Fetch service charges for the assigned amenities
            $parking_count = $tenant_assigned_parking_info->count();


            // Conversion multipliers
            $conversion_rates = [
                'Monthly' => ['Monthly' => 1, 'Quarterly' => 3, 'Annually' => 12],
                'Quarterly' => ['Monthly' => 1/3, 'Quarterly' => 1, 'Annually' => 4],
                'Annually' => ['Monthly' => 1/12, 'Quarterly' => 1/4, 'Annually' => 1],
            ];

            // Build service charges with proper quantities and converted amounts
            $service_charges = [];
            $total_service_charge = 0;


            // Prepare tenant data for view
            $tenantData = [
                'basic_info' => [
                    'id' => $tenant->id,
                    'tenancy_ref' => $tenant->tenancy_ref,
                    'tenant_profile_id' => $tenant->tenant_profile_id,
                    'status' => $tenant->status,
                    'created_at' => $tenant->created_at,
                    'updated_at' => $tenant->updated_at,
                    'encrypted_lease_id' => $tenant->lease->encrypted_lease_id,
                ],
                
                'tenant_profile' => $tenant->tenantProfile ? [
                    'id' => $tenant->tenantProfile->id,
                    'tenant_ref' => $tenant->tenantProfile->tenant_ref,
                    'tenant_type' => $tenant->tenantProfile->tenant_type,
                    'profile_status' => $tenant->tenantProfile->profile_status,
                    'is_blacklisted' => $tenant->tenantProfile->profile_status === 'Blacklisted',
                    'blacklist_reason' => $tenant->tenantProfile->blacklist_reason,
                    'blacklisted_date' => $tenant->tenantProfile->blacklisted_date,
                    'display_name' => $tenant->tenantProfile->display_name,
                    'primary_email' => $tenant->tenantProfile->primary_email,
                    'primary_phone' => $tenant->tenantProfile->primary_phone,
                ] : null,
                
                'personal_details' => $tenant->tenantProfile ? [
                    // Individual tenant info
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
                    
                    // Business tenant info
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
                ] : [],
                
                'property_details' => [
                    'listing_type' => $tenant->listing_type,
                    'unit_number' => $tenant->unit_number,
                    'specific_unit_number' => $tenant->specific_unit_number,
                    'furnishing' => $tenant->furnishing,
                    'bedrooms' => $tenant->bedrooms,
                    'bathrooms' => $tenant->bathrooms,
                    'sq_area' => $tenant->sq_area,
                    'occupied_sq_area' => $tenant->occupied_sq_area,
                    'rate_per_sq_ft' => $tenant->rate_per_sq_ft,
                    'number_of_occupants' => $tenant->number_of_occupants,
                    'has_pets' => $tenant->has_pets,
                    'pet_details' => $tenant->pet_details,
                    'assigned_parking' => $tenant_assigned_parking_info,
                    'assigned_legal_documents' => $tenant_assigned_legal_documents,
                ],
                
                'agreement_details' => [
                    'agreement_type' => $tenant->agreement_type,
                    'monthly_payment' => $tenant->monthly_payment,
                    'security_deposit' => $tenant->security_deposit,
                    'move_in_date' => $tenant->move_in_date,
                    'lease_start_date' => $tenant->lease_start_date,
                    'lease_end_date' => $tenant->lease_end_date,
                    'agreement_start_date' => $tenant->agreement_start_date,
                    'agreement_end_date' => $tenant->agreement_end_date,
                    'lease_duration_months' => $tenant->lease_duration_months,
                    'payment_frequency' => $tenant->payment_frequency ?? 'Monthly',
                    'payment_due_day' => $tenant->payment_due_day ?? 1,
                    'grace_period_days' => $tenant->grace_period_days,
                    'late_fee_amount' => $tenant->late_fee_amount,
                    'penalty_amount' => $tenant->penalty_amount,
                    'special_terms' => $tenant->special_terms,
                    'general_notes' => $tenant->general_notes,
                    'internal_notes' => $tenant->internal_notes,
                    'additional_notes' => $tenant->additional_notes,
                    'total_service_charge' => $total_service_charge,
                    'currency' => $tenant->currency,
                    'service_charge_payment_interval' => $tenant->service_charge_payment_interval ?? 'Monthly',
                ],
                
                'complex' => $tenant->complex,
                'commercial_unit' => $tenant->commercialUnit,
                'unit_occupancy_details' => $unitOccupancyDetails,
                'lease_info' => $leaseInfo,
                // 'payment_stats' => $paymentStats,
                'notice_stats' => $noticeStats,
                'financial_summary' => $financialSummary,
                'recent_activity' => $recentActivity,
                'documents' => $documents,
                // 'payments' => $payments,
                'notices' => $notices, 
            ];

            
            // Add missing variables for the view if needed
            $property_types = DB::table('property_types')->get();
            $complexes = Complex::forBusiness($user_id)->get();

    
            return view('pages.business.tenant.tenancy.show', compact(
                'tenant', 
                'tenantData', 
                'property_types', 
                'complexes'
            ));
    
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            Log::warning('Invalid encrypted tenant reference', [
                'encrypted_ref' => $ref,
                'user_id' => business_owner_id(),
                'error' => $e->getMessage()
            ]);
            abort(404, 'Tenancy not found.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Also reached for tenancies outside the member's assigned properties and units.
            Log::warning('Tenant not found', [
                'encrypted_ref' => $ref,
                'user_id' => business_owner_id()
            ]);
            abort(404, 'Tenancy not found.');
        } catch (\Exception $e) {
            Log::error('Failed to load tenant view: ' . $e->getMessage(), [
                'encrypted_ref' => $ref,
                'user_id' => business_owner_id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    /**
     * Display comprehensive tenant profile view
     * Shows all information about a tenant including all their tenancies, payments, notices, etc.
     */
    public function tenantView($ref)
    {
        try {
            $user_id = business_owner_id();
    
            // Get the tenant profile with all related data
            $tenantProfile = TenantProfile::where('tenant_ref', $ref)
                ->where('managed_by', $user_id)
                ->with([
                    'tenancyAgreements' => function($query) use ($user_id) {
                        $query->where('managed_by', $user_id)
                              ->with([
                                  'complex',
                                  'commercialUnit',
                                  'parkingSlots',
                                  'additionalParkingSlots',
                                  'agent',
                              ])
                              ->orderBy('created_at', 'desc');
                    },
                    'manager',
                    'creator',
                    'updater'
                ])
                ->firstOrFail();
    
            // Gather comprehensive financial data across all tenancies
            $financialSummary = $this->calculateTenantFinancialSummary($tenantProfile);
    
            // Get all payments across all tenancies
            $allPayments = TenantPayment::whereIn('tenant_id', $tenantProfile->tenancyAgreements->pluck('id'))
                ->with(['invoice', 'recordedBy', 'verifiedBy', 'invoiceAllocations'])
                ->orderBy('payment_date', 'desc')
                ->get();
    
            // Get all invoices across all tenancies
            $allInvoices = TenantInvoice::whereIn('tenancy_id', $tenantProfile->tenancyAgreements->pluck('id'))
                ->with(['billing', 'payments', 'paymentAllocations'])
                ->orderBy('due_date', 'desc')
                ->get();
    
            // Get all billings across all tenancies
            $allBillings = TenantBilling::whereIn('tenancy_id', $tenantProfile->tenancyAgreements->pluck('id'))
                ->with(['tenancyBilling', 'invoices'])
                ->orderBy('billing_date', 'desc')
                ->get();
    
            // Get all notices across all tenancies
            $allNotices = TenantNotice::whereIn('tenant_id', $tenantProfile->tenancyAgreements->pluck('id'))
                ->with(['issuedBy'])
                ->orderBy('notice_date', 'desc')
                ->get();
    
            // Get termination records if any
            $terminations = TenantTermination::whereIn('tenant_management_id', $tenantProfile->tenancyAgreements->pluck('id'))
                ->with(['tenantManagement', 'terminatedBy'])
                ->orderBy('termination_date', 'desc')
                ->get();
    
            // Calculate statistics for each tenancy
            $tenanciesWithStats = $tenantProfile->tenancyAgreements->map(function($tenancy) {
                return [
                    'tenancy' => $tenancy,
                    'lease_status' => $tenancy->lease_status,
                    'days_until_expiry' => $tenancy->days_until_expiry,
                    'payment_history' => TenantPayment::where('tenant_id', $tenancy->id)
                        ->with(['invoice'])
                        ->orderBy('payment_date', 'desc')
                        ->limit(5)
                        ->get(),
                    'outstanding_invoices' => TenantInvoice::where('tenancy_id', $tenancy->id)
                        ->whereRaw('amount > COALESCE((SELECT SUM(allocated_amount) FROM tenant_invoice_payment_allocations WHERE invoice_id = tenant_invoices.id), 0)')
                        ->orderBy('due_date', 'asc')
                        ->get(),
                    'active_notices' => TenantNotice::where('tenant_id', $tenancy->id)
                        ->where('effective_date', '>=', now())
                        ->where('tenant_response', '!=', 'complied')
                        ->get(),
                    'assigned_parking' => ComplexParkingSlot::where('complex_id', $tenancy->complex_id)
                        ->where('is_occupied', true)
                        ->where('occupied_tenant_id', $tenancy->id)
                        ->get(),
                    'assigned_legal_documents' => TenantLegalDocument::join('legals', 'tenant_legal_documents.legal_document_id', 'legals.id')
                        ->join('legal_types', 'legals.legal_type_id', 'legal_types.id')
                        ->join('legal_folders', 'legals.legal_folder_id', 'legal_folders.id')
                        ->select('legals.*', 'legal_types.legal_type', 'legal_types.description', 
                                 'legal_folders.name as folder_name', 'legal_folders.slug as folder_slug')
                        ->where('tenant_legal_documents.tenant_management_id', $tenancy->id)
                        ->get(),
                    'termination' => $tenancy->termination,
                    'is_terminated' => $tenancy->is_terminated,
                    'total_paid' => TenantPayment::where('tenant_id', $tenancy->id)
                        ->where('payment_status', '!=', 'failed')
                        ->sum('amount_paid'),
                    'total_invoiced' => TenantInvoice::where('tenancy_id', $tenancy->id)->sum('amount'),
                    'payment_count' => TenantPayment::where('tenant_id', $tenancy->id)->count(),
                    'invoice_count' => TenantInvoice::where('tenancy_id', $tenancy->id)->count(),
                    'documents' => $this->tenancyDetailsService->transformDocumentsForDisplay($tenancy->uploaded_documents ?? []),
                ];
            });
    
            // Organize profile data
            $profileData = [
                'basic_info' => [
                    'id' => $tenantProfile->id,
                    'tenant_ref' => $tenantProfile->tenant_ref,
                    'tenant_type' => $tenantProfile->tenant_type,
                    'display_name' => $tenantProfile->display_name,
                    'profile_status' => $tenantProfile->profile_status,
                    'is_blacklisted' => $tenantProfile->is_blacklisted,
                    'blacklist_reason' => $tenantProfile->blacklist_reason,
                    'blacklisted_date' => $tenantProfile->blacklisted_date,
                    'created_at' => $tenantProfile->created_at,
                    'updated_at' => $tenantProfile->updated_at,
                ],
    
                'contact_info' => [
                    'primary_email' => $tenantProfile->primary_email,
                    'primary_phone' => $tenantProfile->primary_phone,
                    'tenant_email' => $tenantProfile->tenant_email,
                    'tenant_phone' => $tenantProfile->tenant_phone,
                    'business_email' => $tenantProfile->business_email,
                    'business_phone' => $tenantProfile->business_phone,
                    'address' => $tenantProfile->address,
                    'business_address' => $tenantProfile->business_address,
                ],
    
                'personal_details' => $tenantProfile->tenant_type === 'Individual' ? [
                    'tenant_name' => $tenantProfile->tenant_name,
                    'tenant_id_number' => $tenantProfile->tenant_id_number,
                    'date_of_birth' => $tenantProfile->date_of_birth,
                    'gender' => $tenantProfile->gender,
                    'nationality' => $tenantProfile->nationality,
                    'occupation' => $tenantProfile->occupation,
                    'employer' => $tenantProfile->employer,
                    'monthly_income' => $tenantProfile->monthly_income,
                ] : null,
    
                'business_details' => $tenantProfile->tenant_type === 'Business' ? [
                    'business_name' => $tenantProfile->business_name,
                    'business_registration' => $tenantProfile->business_registration,
                    'business_contact_person' => $tenantProfile->business_contact_person,
                    'business_type' => $tenantProfile->business_type,
                    'tax_id_number' => $tenantProfile->tax_id_number,
                    'annual_revenue' => $tenantProfile->annual_revenue,
                    'years_in_business' => $tenantProfile->years_in_business,
                ] : null,
    
                'emergency_contact' => [
                    'emergency_contact_name' => $tenantProfile->emergency_contact_name,
                    'emergency_contact_phone' => $tenantProfile->emergency_contact_phone,
                    'emergency_contact_relationship' => $tenantProfile->emergency_contact_relationship,
                    'emergency_contact_address' => $tenantProfile->emergency_contact_address,
                ],
    
                'documents' => [
                    'identification_documents' => $tenantProfile->identification_documents,
                    'references' => $tenantProfile->references,
                ],
    
                'additional_info' => [
                    'additional_notes' => $tenantProfile->additional_notes,
                    'managed_by' => $tenantProfile->manager,
                    'created_by' => $tenantProfile->creator,
                    'updated_by' => $tenantProfile->updater,
                ],
            ];
    
            // Activity timeline across all tenancies
            $activityTimeline = $this->buildTenantActivityTimeline($tenantProfile);
    
            // Prepare statistics
            $statistics = [
                'total_tenancies' => $tenantProfile->tenancyAgreements->count(),
                'active_tenancies' => $tenantProfile->tenancyAgreements->where('status', 'Active')->count(),
                'terminated_tenancies' => $tenantProfile->tenancyAgreements->where('status', 'Terminated')->count(),
                'pending_tenancies' => $tenantProfile->tenancyAgreements->where('status', 'Pending')->count(),
                'total_payments' => $allPayments->count(),
                'total_amount_paid' => $allPayments->where('payment_status', '!=', 'failed')->sum('amount_paid'),
                'total_invoices' => $allInvoices->count(),
                'outstanding_invoices' => $allInvoices->filter(function($invoice) {
                    $allocated = $invoice->paymentAllocations->sum('allocated_amount');
                    return ($invoice->amount - $allocated) > 0;
                })->count(),
                'total_outstanding_amount' => $allInvoices->sum(function($invoice) {
                    $allocated = $invoice->paymentAllocations->sum('allocated_amount');
                    return $invoice->amount - $allocated;
                }),
                'total_notices_issued' => $allNotices->count(),
                'active_notices' => $allNotices->where('effective_date', '>=', now())
                    ->where('tenant_response', '!=', 'complied')
                    ->count(),
                'earliest_tenancy_date' => $tenantProfile->tenancyAgreements->min('lease_start_date'),
                'latest_payment_date' => $allPayments->max('payment_date'),
            ];
    
            // Get complexes for dropdowns (if needed for actions)
            $complexes = Complex::forBusiness($user_id)->get();
            $property_types = DB::table('property_types')->get();
    
            return view('pages.business.tenant.profile.index', compact(
                'tenantProfile',
                'profileData',
                'tenanciesWithStats',
                'financialSummary',
                'allPayments',
                'allInvoices',
                'allBillings',
                'allNotices',
                'terminations',
                'activityTimeline',
                'statistics',
                'complexes',
                'property_types'
            ));
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Also reached for tenants outside the member's assigned properties and units.
            Log::warning('Tenant profile not found', [
                'tenant_ref' => $ref,
                'user_id' => business_owner_id()
            ]);
            abort(404, 'Tenant not found.');
        } catch (\Exception $e) {
            Log::error('Failed to load tenant profile view: ' . $e->getMessage(), [
                'tenant_ref' => $ref,
                'user_id' => business_owner_id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    /**
     * Show form to edit tenant details
     */
    public function edit($ref)
    {
        try {
            $user_id = business_owner_id();

            $tenant = TenantProfile::where('tenant_ref', $ref)
                ->where('managed_by', $user_id)
                ->firstOrFail();
    
            $property_types = DB::table('property_types')->get();
            $complexes = Complex::forBusiness($user_id)->get();
    
            return view('pages.business.tenant.profile.edit', compact('tenant', 'property_types', 'complexes'));
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('tenant_management.index')->with('error', 'Tenant not found');
        } catch (\Exception $e) {
            Log::error('Failed to load tenant edit page: ' . $e->getMessage());
            return redirect()->route('tenant_management.index')->with('error', 'Failed to load tenant edit page');
        }
    }
    public function getEditData($id)
    {
        try {
            $tenant = TenantManagement::where('id', $id)
                ->where('managed_by', business_owner_id())
                ->with([
                    'tenantProfile', 
                    'complex', 
                    'commercialUnit',
                    'amenities',            
                    'parkingSlots',           
                    'additionalParkingSlots' 
                ])
                ->firstOrFail();
    
            return response()->json([
                'status' => 200,
                'message' => 'Tenant data retrieved successfully',
                'tenant' => [
                    'id' => $tenant->id,
                    'tenancy_ref' => $tenant->tenancy_ref,
                    'listing_type' => $tenant->listing_type,
                    'complex_id' => $tenant->complex_id,
                    'unit_number' => $tenant->unit_number,
                    'specific_unit_number' => $tenant->specific_unit_number,
                    'agreement_type' => strtolower($tenant->agreement_type),
                    'currency' => $tenant->currency ?? default_currency(),                    
                    'monthly_payment' => $tenant->monthly_payment,
                    'payment_frequency' => $tenant->payment_frequency ?? 'Monthly', // Move this up
                    'service_charge_payment_interval' => $tenant->service_charge_payment_interval ?? 'Monthly', 
                    'security_deposit' => $tenant->security_deposit,
                    'late_fee_amount' => $tenant->late_fee_amount,
                    'penalty_amount' => $tenant->penalty_amount,
                    'lease_start_date' => $tenant->lease_start_date ? $tenant->lease_start_date->format('Y-m-d') : null,
                    'lease_end_date' => $tenant->lease_end_date ? $tenant->lease_end_date->format('Y-m-d') : null,
                    'lease_duration_months' => $tenant->lease_duration_months,
                    'bedrooms' => $tenant->bedrooms,
                    'bathrooms' => $tenant->bathrooms,
                    'furnishing' => $tenant->furnishing,
                    'sq_area' => $tenant->sq_area,
                    'occupied_sq_area' => $tenant->occupied_sq_area,
                    'rate_per_sq_ft' => $tenant->rate_per_sq_ft,
                    'grace_period_days' => $tenant->grace_period_days,
                    'status' => $tenant->status,
                    'general_notes' => $tenant->general_notes,
                    'commercial_unit_id' => $tenant->commercial_unit_id,
                    'has_pets' => $tenant->has_pets,
                    'pet_details' => $tenant->pet_details,
                    'number_of_occupants' => $tenant->number_of_occupants,
                    'payment_due_day' => $tenant->payment_due_day ?? 1,
                ],
                'tenant_profile' => $tenant->tenantProfile ? [
                    'id' => $tenant->tenantProfile->id,
                    'tenant_type' => $tenant->tenantProfile->tenant_type,
                    'tenant_name' => $tenant->tenantProfile->tenant_name,
                    'tenant_email' => $tenant->tenantProfile->tenant_email,
                    'tenant_phone' => $tenant->tenantProfile->tenant_phone,
                    'tenant_id_number' => $tenant->tenantProfile->tenant_id_number,
                    'date_of_birth' => $tenant->tenantProfile->date_of_birth ? $tenant->tenantProfile->date_of_birth->format('Y-m-d') : null,
                    'gender' => $tenant->tenantProfile->gender,
                    'nationality' => $tenant->tenantProfile->nationality,
                    'occupation' => $tenant->tenantProfile->occupation,
                    'employer' => $tenant->tenantProfile->employer,
                    'monthly_income' => $tenant->tenantProfile->monthly_income,
                    'business_name' => $tenant->tenantProfile->business_name,
                    'business_registration' => $tenant->tenantProfile->business_registration,
                    'business_contact_person' => $tenant->tenantProfile->business_contact_person,
                    'business_email' => $tenant->tenantProfile->business_email,
                    'business_phone' => $tenant->tenantProfile->business_phone,
                    'business_type' => $tenant->tenantProfile->business_type,
                    'tax_id_number' => $tenant->tenantProfile->tax_id_number,
                    'annual_revenue' => $tenant->tenantProfile->annual_revenue,
                    'years_in_business' => $tenant->tenantProfile->years_in_business,
                    'emergency_contact_name' => $tenant->tenantProfile->emergency_contact_name,
                    'emergency_contact_phone' => $tenant->tenantProfile->emergency_contact_phone,
                    'emergency_contact_relationship' => $tenant->tenantProfile->emergency_contact_relationship,
                    'emergency_contact_address' => $tenant->tenantProfile->emergency_contact_address,
                    'profile_status' => $tenant->tenantProfile->profile_status,
                    'profile_notes' => $tenant->tenantProfile->profile_notes,
                ] : null,
                'complex' => $tenant->complex ? [
                    'id' => $tenant->complex->id,
                    'title' => $tenant->complex->title,
                    'slug' => $tenant->complex->slug,
                ] : null,
                'commercial_unit' => $tenant->commercialUnit ? [
                    'id' => $tenant->commercialUnit->id,
                    'complex_id' => $tenant->commercialUnit->complex_id,
                    'unit_number' => $tenant->commercialUnit->unit_number,
                    'unit_sq_ft' => $tenant->commercialUnit->unit_sq_ft,
                    'max_subdivisions' => $tenant->commercialUnit->max_subdivisions,
                    'min_sq_ft' => $tenant->commercialUnit->min_sq_ft,
                    'floors' => $tenant->commercialUnit->floors,
                    'custom_floors' => $tenant->commercialUnit->custom_floors,
                    'monthly_rent' => $tenant->commercialUnit->monthly_rent,
                    'status' => $tenant->commercialUnit->status,
                    'description' => $tenant->commercialUnit->description,
                ] : null,
                // Add new relationship data
                'amenities' => $tenant->amenities ? $tenant->amenities->map(function($amenity) {
                    return [
                        'id' => $amenity->id,
                        'amenity_name' => $amenity->amenity_name,
                        'amenity_type' => $amenity->amenity_type,
                        'unit_charge' => $amenity->unit_charge,
                        'charge_period' => $amenity->charge_period,
                        'currency' => $amenity->currency,
                    ];
                }) : [],
                'parking_slots' => $tenant->parkingSlots ? $tenant->parkingSlots->map(function($slot) {
                    return [
                        'id' => $slot->id,
                        'slot_identifier' => $slot->slot_identifier,
                        'parking_type' => $slot->parking_type,
                        'is_occupied' => $slot->is_occupied,
                    ];
                }) : [],
                'additional_parking_slots' => $tenant->additionalParkingSlots ? $tenant->additionalParkingSlots->map(function($slot) {
                    return [
                        'id' => $slot->id,
                        'slot_identifier' => $slot->slot_identifier,
                        'additional_slot_charge' => $slot->additional_slot_charge,
                    ];
                }) : [],
            ]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Tenant not found or you do not have permission to edit this tenant'
            ], 404);
    
        } catch (\Exception $e) {
            Log::error('Failed to get tenant edit data: ' . $e->getMessage(), [
                'tenant_id' => $id,
                'user_id' => business_owner_id()
            ]);
    
            return response()->json([
                'status' => 500,
                'message' => 'Failed to load tenant data. Please try again.'
            ], 500);
        }
    }
    /**
     * Update tenant profile details
     */
    public function updateTenantProfile(Request $request, $tenant_ref)
    {
        try {
            $user_id = business_owner_id();

            // Find the tenant profile
            $tenant = TenantProfile::where('tenant_ref', $tenant_ref)
                ->where('managed_by', $user_id)
                ->firstOrFail();

            // Base validation rules
            $rules = [
                'profile_status' => 'required|in:Active,Inactive,Blacklisted',
                
                // Emergency Contact
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:20',
                'emergency_contact_relationship' => 'nullable|string|max:100',
                'emergency_contact_address' => 'nullable|string|max:500',
                
                // Additional Information
                'additional_notes' => 'nullable|string',
                
                // Blacklist fields (conditional)
                'blacklisted_date' => 'nullable|required_if:profile_status,Blacklisted|date',
                'blacklist_reason' => 'nullable|required_if:profile_status,Blacklisted|string|max:1000',
            ];

            // Add conditional validation based on tenant type
            if ($tenant->tenant_type === 'Individual') {
                $rules = array_merge($rules, [
                    'tenant_name' => 'required|string|max:255',
                    'tenant_email' => 'required|email|max:255',
                    'tenant_phone' => 'required|string|max:20',
                    // Field names match the tenant_profiles columns and the onboarding form.
                    'tenant_id_number' => 'required|string|max:50',
                    'date_of_birth' => 'nullable|date|before:today',
                    'gender' => 'nullable|in:Male,Female,Other,Prefer not to say',
                    'nationality' => 'nullable|string|max:100',
                    'address' => 'nullable|string|max:500',
                    'occupation' => 'nullable|string|max:255',
                    'employer' => 'nullable|string|max:255',
                    'monthly_income' => 'nullable|numeric|min:0',
                ]);
            } elseif ($tenant->tenant_type === 'Business') {
                $rules = array_merge($rules, [
                    'business_name' => 'required|string|max:255',
                    'business_registration' => 'required|string|max:100',
                    'business_contact_person' => 'required|string|max:255',
                    'business_email' => 'required|email|max:255',
                    'business_phone' => 'required|string|max:20',
                    'business_type' => 'nullable|string|max:100',
                    'business_address' => 'nullable|string|max:500',
                    'tax_id_number' => 'nullable|string|max:100',
                    'years_in_business' => 'nullable|integer|min:0',
                    'annual_revenue' => 'nullable|numeric|min:0',
                ]);
            }

            // Validate the request
            $validatedData = $request->validate($rules);

            // Check for duplicate email (exclude current tenant)
            if ($tenant->tenant_type === 'Individual' && isset($validatedData['tenant_email'])) {
                $duplicateCheck = TenantProfile::where(fn ($q) => $q->where('tenant_email', $validatedData['tenant_email'])->orWhere('tenant_id_number', $validatedData['tenant_id_number']))
                    ->where('managed_by', $user_id)
                    ->where('id', '!=', $tenant->id)
                    ->first();
                    
                if ($duplicateCheck) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Another tenant already uses this email or ID number.'
                    ], 422);
                }
            } elseif ($tenant->tenant_type === 'Business' && isset($validatedData['business_email'])) {
                $duplicateCheck = TenantProfile::where(fn ($q) => $q->where('business_email', $validatedData['business_email'])->orWhere('business_registration', $validatedData['business_registration']))
                    ->where('managed_by', $user_id)
                    ->where('id', '!=', $tenant->id)
                    ->first();
                    
                if ($duplicateCheck) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Another business tenant already uses this email or registration number.'
                    ], 422);
                }
            }

            // Handle blacklist status change
            if ($validatedData['profile_status'] === 'Blacklisted') {
                // If being blacklisted, ensure date and reason are provided
                if (empty($validatedData['blacklisted_date'])) {
                    $validatedData['blacklisted_date'] = now();
                }
                if (empty($validatedData['blacklist_reason'])) {
                    return response()->json([
                        'status' => 422,
                        'message' => 'Blacklist reason is required when blacklisting a tenant.'
                    ], 422);
                }
            } else {
                // If not blacklisted, clear blacklist fields
                $validatedData['blacklisted_date'] = null;
                $validatedData['blacklist_reason'] = null;
            }

            // Update the tenant profile
            $validatedData['updated_by'] = $user_id;
            $tenant->update($validatedData);

            // Log the update
            Log::info("Tenant profile updated: {$tenant->tenant_ref}", [
                'tenant_ref' => $tenant->tenant_ref,
                'profile_id' => $tenant->id,
                'tenant_type' => $tenant->tenant_type,
                'profile_status' => $validatedData['profile_status'],
                'updated_by' => $user_id
            ]);

            return response()->json([
                'status' => 200,
                'message' => 'Tenant profile updated successfully!',
                'tenant' => [
                    'tenant_ref' => $tenant->tenant_ref,
                    'display_name' => $tenant->display_name,
                    'profile_status' => $tenant->profile_status,
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Tenant profile not found or you do not have permission to edit this profile.'
            ], 404);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => 'Validation failed. Please check your input.',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('Failed to update tenant profile: ' . $e->getMessage(), [
                'tenant_ref' => $tenant_ref,
                'user_id' => $user_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Failed to update tenant profile. Please try again.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    /**
     * Get tenant payment history
     */
    public function paymentHistory($tenantId)
    {
        try {
            $tenant = TenantManagement::where('id', $tenantId)
                ->where('managed_by', business_owner_id())
                ->firstOrFail();
    
            $payments = TenantPayment::where('tenant_id', $tenantId)
                ->with(['recordedBy', 'verifiedBy'])
                ->orderBy('payment_date', 'desc')
                ->paginate(10);
    
            return response()->json([
                'status' => 200,
                'message' => 'Payment history retrieved successfully',
                'payments' => $payments
            ]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Tenant not found'
            ], 404);
        }
    }
    /**
     * Get tenant notice history
     */
    public function noticeHistory($tenantId)
    {
        try {
            $tenant = TenantManagement::where('id', $tenantId)
                ->where('managed_by', business_owner_id())
                ->firstOrFail();
    
            $notices = TenantNotice::where('tenant_id', $tenantId)
                ->with(['issuedBy'])
                ->orderBy('notice_date', 'desc')
                ->paginate(10);
    
            return response()->json([
                'status' => 200,
                'message' => 'Notice history retrieved successfully',
                'notices' => $notices
            ]);
    
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Tenant not found'
            ], 404);
        }
    }
    private function calculateTenantFinancialSummary($tenantProfile)
    {
        $tenancyIds = $tenantProfile->tenancyAgreements->pluck('id');
        
        // Get all invoices with allocations
        $invoices = TenantInvoice::whereIn('tenancy_id', $tenancyIds)
            ->with('paymentAllocations')
            ->get();
        
        // Get all payments
        $payments = TenantPayment::whereIn('tenant_id', $tenancyIds)
            ->where('payment_status', '!=', 'failed')
            ->get();
        
        $totalInvoiced = $invoices->sum('amount');
        $totalPaid = $payments->sum('amount_paid');
        $totalAllocated = $invoices->sum(function($invoice) {
            return $invoice->paymentAllocations->sum('allocated_amount');
        });
        $totalOutstanding = $invoices->sum(function($invoice) {
            $allocated = $invoice->paymentAllocations->sum('allocated_amount');
            return $invoice->amount - $allocated;
        });
        
        // Calculate by currency
        $invoicesByCurrency = $invoices->groupBy('currency')->map(function($group) {
            return [
                'total_invoiced' => $group->sum('amount'),
                'total_allocated' => $group->sum(function($invoice) {
                    return $invoice->paymentAllocations->sum('allocated_amount');
                }),
                'total_outstanding' => $group->sum(function($invoice) {
                    $allocated = $invoice->paymentAllocations->sum('allocated_amount');
                    return $invoice->amount - $allocated;
                }),
            ];
        });
        
        // Payment trends (last 12 months)
        $paymentTrends = $payments->filter(function($payment) {
            return $payment->payment_date >= now()->subMonths(12);
        })->groupBy(function($payment) {
            return $payment->payment_date->format('Y-m');
        })->map(function($group) {
            return $group->sum('amount_paid');
        });
        
        return [
            'total_invoiced' => $totalInvoiced,
            'total_paid' => $totalPaid,
            'total_allocated' => $totalAllocated,
            'total_outstanding' => $totalOutstanding,
            'payment_percentage' => $totalInvoiced > 0 ? ($totalAllocated / $totalInvoiced) * 100 : 0,
            'invoices_by_currency' => $invoicesByCurrency,
            'payment_trends' => $paymentTrends,
            'average_monthly_payment' => $payments->count() > 0 ? $totalPaid / $payments->count() : 0,
            'total_payments_count' => $payments->count(),
            'verified_payments_count' => $payments->where('is_verified', true)->count(),
            'pending_payments_count' => $payments->where('payment_status', 'pending')->count(),
        ];
    }
    private function buildTenantActivityTimeline($tenantProfile)
    {
        $timeline = collect();
        
        $tenancyIds = $tenantProfile->tenancyAgreements->pluck('id');
        
        // Add tenancy events
        foreach ($tenantProfile->tenancyAgreements as $tenancy) {
            $timeline->push([
                'date' => $tenancy->created_at,
                'type' => 'tenancy_created',
                'title' => 'Tenancy Created',
                'description' => "Tenancy {$tenancy->tenancy_ref} was created for {$tenancy->complex->title}",
                'icon' => 'fas fa-home',
                'color' => 'primary',
                'data' => $tenancy,
            ]);
            
            if ($tenancy->move_in_date) {
                $timeline->push([
                    'date' => $tenancy->move_in_date,
                    'type' => 'move_in',
                    'title' => 'Move In',
                    'description' => "Tenant moved in to {$tenancy->unit_number}",
                    'icon' => 'fas fa-sign-in-alt',
                    'color' => 'success',
                    'data' => $tenancy,
                ]);
            }
            
            if ($tenancy->is_terminated && $tenancy->termination) {
                $timeline->push([
                    'date' => $tenancy->termination->termination_date,
                    'type' => 'termination',
                    'title' => 'Tenancy Terminated',
                    'description' => "Reason: {$tenancy->termination->termination_reason_label}",
                    'icon' => 'fas fa-stop-circle',
                    'color' => 'danger',
                    'data' => $tenancy->termination,
                ]);
            }
        }
        
        // Add payment events
        $payments = TenantPayment::whereIn('tenant_id', $tenancyIds)
            ->orderBy('payment_date', 'desc')
            ->get();
            
        foreach ($payments as $payment) {
            $timeline->push([
                'date' => $payment->payment_date,
                'type' => 'payment',
                'title' => ['refund' => 'Refund paid', 'credit' => 'Credit added', 'deposit_release' => 'Deposit applied'][$payment->entry_type ?? 'payment'] ?? 'Payment recorded',
                'description' => "{$payment->formatted_amount}" . (in_array($payment->entry_type ?? 'payment', ['payment', 'refund'], true) ? " by {$payment->payment_method_label}" : '') . ($payment->payment_status === 'reversed' ? ' (reversed)' : ($payment->payment_status === 'pending' ? ' (awaiting clearance)' : '')),
                'icon' => 'fas fa-money-bill-wave',
                'color' => 'success',
                'data' => $payment,
            ]);
        }
        
        // Add notice events
        $notices = TenantNotice::whereIn('tenant_id', $tenancyIds)->get();
        foreach ($notices as $notice) {
            $timeline->push([
                'date' => $notice->notice_date,
                'type' => 'notice',
                'title' => 'Notice Issued',
                'description' => "{$notice->notice_type_label}: {$notice->notice_subject}",
                'icon' => 'fas fa-exclamation-triangle',
                'color' => 'warning',
                'data' => $notice,
            ]);
        }
        
        // Sort by date descending
        return $timeline->sortByDesc('date')->values();
    }
}
