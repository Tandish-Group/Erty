<?php

namespace App\Http\Controllers\Business\Tenancies;

use App\Http\Controllers\Controller;
use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Data for the tenant workspace tables (DataTables): tenancies, leases, tenant profiles, billings and invoices. */
class TenancyTableController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Fetch tenants data for DataTables with search, ordering, and pagination.
     */
    public function getTenantsData(Request $request)
    {
        $user_id = business_owner_id();
        
        // Get search term
        $searchTerm = $request->input('search_term', '');
        
        // Start query for tenant profiles with their tenancies
        $query = TenantProfile::forBusiness($user_id)
            ->with(['tenancyAgreements' => function($query) use ($user_id) {
                $query->where('managed_by', $user_id)
                      ->with(['complex', 'commercialUnit'])
                      ->orderBy('created_at', 'desc');
            }]);
        
        // Get total count before filtering
        $totalRecords = TenantProfile::forBusiness($user_id)->count();
        
        // Apply search functionality
        if (!empty($searchTerm)) {
            $query->where(function($q) use ($searchTerm) {
                $q->whereLike('tenant_ref', "%{$searchTerm}%")
                  ->orWhereLike('tenant_name', "%{$searchTerm}%")
                  ->orWhereLike('business_name', "%{$searchTerm}%")
                  ->orWhereLike('tenant_email', "%{$searchTerm}%")
                  ->orWhereLike('business_email', "%{$searchTerm}%")
                  ->orWhereLike('tenant_phone', "%{$searchTerm}%")
                  ->orWhereLike('business_phone', "%{$searchTerm}%")
                  ->orWhereLike('tenant_id_number', "%{$searchTerm}%")
                  ->orWhereLike('business_registration', "%{$searchTerm}%")
                  // Search in related tenancies
                  ->orWhereHas('tenancyAgreements', function($tenancyQuery) use ($searchTerm) {
                      $tenancyQuery->whereLike('tenancy_ref', "%{$searchTerm}%")
                                   ->orWhereLike('unit_number', "%{$searchTerm}%")
                                   ->orWhereLike('specific_unit_number', "%{$searchTerm}%");
                  });
            });
        }
        
        // Get filtered count
        $filteredRecords = $query->count();
        
        // Apply ordering
        if ($request->has('order') && count($request->order) > 0) {
            $orderColumnIndex = $request->order[0]['column'];
            $orderDirection = $request->order[0]['dir'];
            
            $columns = [
                0 => 'tenant_name',
                1 => 'created_at',
            ];
            
            if (isset($columns[$orderColumnIndex])) {
                $orderColumn = $columns[$orderColumnIndex];
                $query->orderBy($orderColumn, $orderDirection);
            }
        } else {
            $query->orderBy('updated_at', 'desc');
        }
        
        // Apply pagination
        $query->skip($request->start)->take($request->length);
        
        // Get the results
        $tenantProfiles = $query->get();
        
        // Transform the data
        $data = $tenantProfiles->map(function($profile) {
            return [
                'id' => $profile->id,
                'tenant_ref' => $profile->tenant_ref,
                'tenant_type' => $profile->tenant_type,
                'profile_status' => $profile->profile_status,
                'display_name' => $profile->tenant_type === 'Business' 
                    ? $profile->business_name 
                    : $profile->tenant_name,
                'primary_email' => $profile->tenant_type === 'Business'
                    ? $profile->business_email 
                    : $profile->tenant_email,
                'primary_phone' => $profile->tenant_type === 'Business'
                    ? $profile->business_phone 
                    : $profile->tenant_phone,
                'tenant_id_number' => $profile->tenant_id_number,
                'business_registration' => $profile->business_registration,
                'is_blacklisted' => $profile->profile_status === 'Blacklisted',
                'blacklist_reason' => $profile->blacklist_reason,
                'tenancies' => $profile->tenancyAgreements->map(function($tenancy) {
                    return [
                        'id' => $tenancy->id,
                        'tenancy_ref' => $tenancy->tenancy_ref,
                        'status' => $tenancy->status,
                        'agreement_type' => $tenancy->agreement_type,
                        'listing_type' => $tenancy->listing_type,
                        'unit_number' => $tenancy->unit_number,
                        'specific_unit_number' => $tenancy->specific_unit_number,
                        'currency' => $tenancy->currency,
                        'monthly_payment' => $tenancy->monthly_payment,
                        'payment_frequency' => $tenancy->payment_frequency,
                        'security_deposit' => $tenancy->security_deposit,
                        'late_fee_amount' => $tenancy->late_fee_amount,
                        'lease_start_date' => $tenancy->lease_start_date,
                        'lease_end_date' => $tenancy->lease_end_date,
                        'lease_duration_months' => $tenancy->lease_duration_months,
                        'lease_status' => \App\Support\LeaseStatus::of($tenancy->lease),
                        'days_until_expiry' => \App\Support\LeaseStatus::daysLeft($tenancy),
                        'complex' => $tenancy->complex ? [
                            'id' => $tenancy->complex->id,
                            'title' => $tenancy->complex->title,
                            'ref_code' => $tenancy->complex->ref_code,
                        ] : null,
                        'commercialUnit' => $tenancy->commercialUnit ? [
                            'id' => $tenancy->commercialUnit->id,
                            'unit_number' => $tenancy->commercialUnit->unit_number,
                        ] : null,
                        'is_commercial' => $tenancy->listing_type === 'Commercial Unit',
                        'bedrooms' => $tenancy->bedrooms,
                        'bathrooms' => $tenancy->bathrooms,
                        'furnishing' => $tenancy->furnishing,
                        'sq_area' => $tenancy->sq_area,
                        'occupied_sq_area' => $tenancy->occupied_sq_area,
                        'rate_per_sq_ft' => $tenancy->rate_per_sq_ft,
                    ];
                }),
                'tenancies_count' => $profile->tenancyAgreements->count(),
                'active_tenancies' => $profile->tenancyAgreements->where('status', 'Active')->count(),
                'created_at' => $profile->created_at,
                'updated_at' => $profile->updated_at,
            ];
        });
        
        // Return the DataTables response format
        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        ]);
    }
    /**
     * Get leases data for DataTables
     */
    public function getLeasesData(Request $request)
    {
        try {
            $user_id = business_owner_id();
            
            // Base query
            $query = TenantManagement::forBusiness($user_id)
                ->with(['tenantProfile', 'complex', 'commercialUnit', 'lease'])
                ->whereNotNull('lease_start_date');
            
            // Search functionality
            $searchTerm = $request->input('search.value', '');
            if (!empty($searchTerm)) {
                $query->where(function($q) use ($searchTerm) {
                    $q->whereHas('tenantProfile', function($q) use ($searchTerm) {
                        $q->whereLike('tenant_name', "%{$searchTerm}%")
                          ->orWhereLike('business_name', "%{$searchTerm}%")
                          ->orWhereLike('tenant_email', "%{$searchTerm}%")
                          ->orWhereLike('business_email', "%{$searchTerm}%");
                    })
                    ->orWhereLike('complex_name', "%{$searchTerm}%")
                    ->orWhereLike('unit_number', "%{$searchTerm}%")
                    ->orWhereLike('tenancy_ref', "%{$searchTerm}%");
                });
            }
            
            // Total records
            $totalRecords = TenantManagement::forBusiness($user_id)
                ->whereNotNull('lease_start_date')
                ->count();
            
            // Filtered records
            $filteredRecords = $query->count();
            
            // Ordering
            $orderColumnIndex = $request->input('order.0.column', 0);
            $orderDirection = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
            $columns = ['tenancy_ref', 'complex_name', 'lease_start_date', 'monthly_payment', 'status', 'lease_end_date'];
            
            if (isset($columns[$orderColumnIndex])) {
                $query->orderBy($columns[$orderColumnIndex], $orderDirection);
            }
            
            // Pagination
            $start = max(0, (int) $request->input('start', 0));
            $length = min(100, max(1, (int) $request->input('length', 10)));
            $query->skip($start)->take($length);
            
            // Get results
            $tenancies = $query->get();
            
            // Transform data
            $data = $tenancies->map(function($tenancy) {
                $tenantProfile = $tenancy->tenantProfile;
                $lease = $tenancy->lease;
                
                // Calculate lease status
                $leaseStatus = 'active';
                $statusClass = 'success';
                $daysUntilExpiry = null;
                
                if ($tenancy->lease_end_date) {
                    $now = \Carbon\Carbon::today();
                    $endDate = \Carbon\Carbon::parse($tenancy->lease_end_date);
                    $daysUntilExpiry = (int) $now->diffInDays($endDate->startOfDay(), false);
                    
                    if ($daysUntilExpiry < 0) {
                        $leaseStatus = 'expired';
                        $statusClass = 'danger';
                    } elseif ($daysUntilExpiry <= 30) {
                        $leaseStatus = 'expiring-soon';
                        $statusClass = 'warning';
                    }
                }
                
                if ($tenancy->status !== 'Active') {
                    $leaseStatus = strtolower($tenancy->status ?? 'inactive');
                    $statusClass = 'secondary';
                } elseif ($lease && $lease->status !== 'active') {
                    $leaseStatus = $lease->status;
                    $statusClass = 'secondary';
                }

                // Property info
                $propertyInfo = $tenancy->complex_name;
                if ($tenancy->unit_number) {
                    $propertyInfo .= ' - Unit ' . $tenancy->unit_number;
                }
                if ($tenancy->commercial_unit_id && $tenancy->specific_unit_number) {
                    $propertyInfo .= ' / ' . $tenancy->specific_unit_number;
                }
                
                // Lease period
                $leasePeriod = \Carbon\Carbon::parse($tenancy->lease_start_date)->format('M d, Y') . ' - ' . 
                              ($tenancy->lease_end_date ? \Carbon\Carbon::parse($tenancy->lease_end_date)->format('M d, Y') : 'Open-ended');
                
                // Expiry info
                $expiryInfo = 'No end date';
                if ($daysUntilExpiry !== null) {
                    if ($daysUntilExpiry < 0) {
                        $expiryInfo = 'Expired ' . abs($daysUntilExpiry) . ' days ago';
                    } elseif ($daysUntilExpiry == 0) {
                        $expiryInfo = 'Expires today';
                    } else {
                        $expiryInfo = $daysUntilExpiry . ' days remaining';
                    }
                }
                
                return [
                    'tenant' => [
                        'name' => $tenantProfile?->display_name ?? 'Tenant profile unavailable',
                        'type' => $tenantProfile?->tenant_type,
                        'email' => $tenantProfile?->primary_email ?? '',
                        'phone' => $tenantProfile?->primary_phone ?? '',
                        'ref' => $tenancy->tenancy_ref,
                    ],
                    'property' => $propertyInfo,
                    'lease_period' => $leasePeriod,
                    'rent_amount' => $tenancy->currency . ' ' . number_format($tenancy->monthly_payment, 2),
                    'status' => [
                        'label' => ucwords(str_replace('-', ' ', $leaseStatus)),
                        'class' => $statusClass,
                    ],
                    'expiry' => $expiryInfo,
                    'has_lease' => $lease !== null,
                    'lease_id' => $lease ? $lease->id : null,
                    'encrypted_lease_id' => encrypt($lease ? $lease->id : null),
                    'tenancy_id' => $tenancy->id,
                    'encrypted_tenancy_id' => encrypt($tenancy->id),
                ];
            });
            
            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching leases data: ' . $e->getMessage());
            return response()->json([
                'draw' => intval($request->input('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to fetch leases data'
            ], 500);
        }
    }
    /**
     * Get tenant profiles data for DataTables with tenancy counts
     */
    public function getTenantProfilesData(Request $request)
    {
        try {
            $userId = business_owner_id();

            
            // Start the query builder for tenant profiles
            $query = TenantProfile::forBusiness($userId)
                ->withCount(['tenancyAgreements' => function($query) use ($userId) {
                    $query->where('managed_by', $userId);
                }])
                ->with(['latestTenancy' => function($query) use ($userId) {
                    $query->where('managed_by', $userId)
                          ->with(['complex', 'commercialUnit'])
                          ->latest('created_at');
                }]);
    
            
            // Get total count before filtering
            $totalRecords = TenantProfile::forBusiness($userId)->count();
    
            $searchTerm = $request->input('search_term', '');
            
            // Apply search functionality
            if (!empty($searchTerm)) {
                $query->where(function($q) use ($searchTerm) {
                    $q->whereLike('tenant_ref', "%{$searchTerm}%")
                      ->orWhereLike('tenant_name', "%{$searchTerm}%")
                      ->orWhereLike('business_name', "%{$searchTerm}%")
                      ->orWhereLike('tenant_email', "%{$searchTerm}%")
                      ->orWhereLike('business_email', "%{$searchTerm}%")
                      ->orWhereLike('tenant_phone', "%{$searchTerm}%")
                      ->orWhereLike('business_phone', "%{$searchTerm}%")
                      ->orWhereLike('tenant_id_number', "%{$searchTerm}%")
                      ->orWhereLike('business_registration', "%{$searchTerm}%");
                });
            }


            // Filter by tenant type if provided
            if ($request->filled('tenant_type')) {
                $query->where('tenant_type', $request->tenant_type);
            }
    
            // Filter by profile status if provided
            if ($request->filled('profile_status')) {
                $query->where('profile_status', $request->profile_status);
            }
    
            // Get filtered count
            $filteredRecords = $query->count();


            // Apply ordering
            if ($request->has('order') && count($request->order) > 0) {
                $orderColumnIndex = $request->order[0]['column'];
                $orderDirection = $request->order[0]['dir'];
                
                // Define column mapping
                $columns = [
                    0 => 'tenant_ref',
                    1 => 'tenant_name', 
                    2 => 'tenant_type',
                    3 => 'profile_status',
                    4 => 'tenancy_agreements_count', // Changed from 'tenancies_count' to match the relationship name
                    5 => 'created_at',
                ];
                
                if (isset($columns[$orderColumnIndex])) {
                    $orderColumn = $columns[$orderColumnIndex];
                    
                    // Handle special ordering for computed fields
                    if ($orderColumn === 'tenancy_agreements_count') {
                        // Order by the count generated by withCount()
                        $query->orderBy('tenancy_agreements_count', $orderDirection);
                    } else {
                        $query->orderBy($orderColumn, $orderDirection);
                    }
                }
            } else {
                $query->orderBy('updated_at', 'desc');
            }

            // Apply pagination
            // $query->skip($request->start)->take($request->length);

            // Get the results
            $tenantProfiles = $query->get();


            // Transform the data
            $data = $tenantProfiles->map(function($profile) {
                return [
                    'id' => $profile->id,
                    'tenant_ref' => $profile->tenant_ref,
                    'tenant_type' => $profile->tenant_type,
                    'profile_status' => $profile->profile_status,
                    'display_name' => $profile->tenant_type === 'Business' 
                        ? $profile->business_name 
                        : $profile->tenant_name,
                    'primary_email' => $profile->tenant_type === 'Business'
                        ? $profile->business_email 
                        : $profile->tenant_email,
                    'primary_phone' => $profile->tenant_type === 'Business'
                        ? $profile->business_phone 
                        : $profile->tenant_phone,
                    'tenant_id_number' => $profile->tenant_id_number,
                    'business_registration' => $profile->business_registration,
                    'tenancies_count' => $profile->tenancy_agreements_count,
                    'latest_tenancy' => $profile->latestTenancy ? [
                        'id' => $profile->latestTenancy->id,
                        'tenancy_ref' => $profile->latestTenancy->tenancy_ref,
                        'status' => $profile->latestTenancy->status,
                        'complex_name' => $profile->latestTenancy->complex ? $profile->latestTenancy->complex->title : 'N/A',
                        'unit_number' => $profile->latestTenancy->unit_number ?: $profile->latestTenancy->specific_unit_number,
                        'monthly_payment' => $profile->latestTenancy->monthly_payment,
                        'lease_start_date' => $profile->latestTenancy->lease_start_date,
                        'lease_end_date' => $profile->latestTenancy->lease_end_date,
                    ] : null,
                    'is_blacklisted' => $profile->profile_status === 'Blacklisted',
                    'blacklist_reason' => $profile->blacklist_reason,
                    'created_at' => $profile->created_at,
                    'updated_at' => $profile->updated_at,
                ];
            });
    
            // Return the DataTables response format
            return response()->json([
                'draw' => intval($request->draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data
            ]);
    
        } catch (\Exception $e) {
            Log::error('Failed to retrieve tenant profiles data: ' . $e->getMessage(), [
                'user_id' => business_owner_id(),
                'error' => $e->getMessage()
            ]);
    
            return response()->json([
                'draw' => intval($request->draw ?? 0),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Failed to load tenant profiles data'
            ]);
        }
    }
    /**
     * Display the tenant billings page.
     */
    public function getTenantBillings()
    {
        // Every tenancy's account: what is owed, overdue, credit and deposit held.
        $accounts = app(\App\Services\Rent\TenantReportService::class)->getAccounts();
        return view('pages.business.tenant.billings', compact('accounts'));
    }
    /**
     * Get invoices for a specific tenant
     */
    public function getTenantInvoices($tenantId)
    {
        try {
            // First, verify the tenant management record exists and belongs to this user
            $tenant = TenantManagement::where('id', $tenantId)
                ->where('managed_by', business_owner_id())
                ->firstOrFail();
    
            // Get tenant invoices using tenancy_id (not tenant_id) with their related payments
            $invoices = TenantInvoice::where('tenancy_id', $tenant->id)
                ->with(['payments' => function($query) {
                    $query->where('payment_status', '!=', 'failed')
                          ->orderBy('payment_date', 'desc');
                }, 'tenancy'])
                ->orderBy('due_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();
    
            // Format invoices for the dropdown
            $formattedInvoices = $invoices->map(function($invoice) {
                $amount = $invoice->amount ?? 0;
                
                // Calculate amount paid from related payments
                $amountPaid = $invoice->payments->sum('amount_paid');
                $balance = $amount - $amountPaid;
                
                // Determine status
                $status = $this->calculateInvoiceStatus($invoice, $amount, $amountPaid);
                
                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number ?? "INV-{$invoice->id}",
                    'combined_invoice_number' => $invoice->combined_invoice_number,
                    'period' => $this->formatInvoicePeriod($invoice),
                    'amount' => $amount,
                    'amount_paid' => $amountPaid,
                    'balance' => $balance,
                    'status' => $status,
                    'due_date' => $invoice->due_date,
                    'late_fee' => $invoice->tenancy ? $invoice->tenancy->late_fee_amount : 0,
                    'billing_period_start' => $invoice->billing_period_start,
                    'billing_period_end' => $invoice->billing_period_end,
                    'description' => $invoice->line_items['description'] ?? 'Rent Payment',
                    'payment_count' => $invoice->payments->count(),
                    'latest_payment_date' => $invoice->payments->max('payment_date'),
                    'created_at' => $invoice->created_at,
                    'can_pay' => $balance > 0, // Only allow payments if there's a balance
                    'invoice_type' => $invoice->invoice_type ?? 'Rent',
                    'tenancy_ref' => $invoice->tenancy ? $invoice->tenancy->tenancy_ref : null,
                ];
            });
            
            return response()->json([
                'status' => 200,
                'message' => 'Invoices retrieved successfully',
                'invoices' => $formattedInvoices,
                'tenant_info' => [
                    'tenancy_ref' => $tenant->tenancy_ref,
                    'tenant_profile_id' => $tenant->tenant_profile_id,
                    'display_name' => $tenant->tenantProfile ? 
                        ($tenant->tenantProfile->tenant_type === 'Business' ? 
                            $tenant->tenantProfile->business_name : 
                            $tenant->tenantProfile->tenant_name) : 'Unknown'
                ]
            ]);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 404,
                'message' => 'Tenant not found or you do not have permission to access this tenant'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Error fetching tenant invoices: ' . $e->getMessage(), [
                'tenant_id' => $tenantId,
                'user_id' => business_owner_id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'status' => 500,
                'message' => 'Failed to fetch invoices',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
    private function calculateInvoiceStatus($invoice, $totalAmount, $amountPaid)
    {
        if ($amountPaid >= $totalAmount) {
            return 'paid';
        } elseif ($amountPaid > 0) {
            return 'partial';
        } elseif ($invoice->due_date && Carbon::parse($invoice->due_date)->isPast()) {
            return 'overdue';
        } else {
            return 'pending';
        }
    }
    private function formatInvoicePeriod($invoice)
    {
        // Check for billing period dates first
        if ($invoice->billing_period_start && $invoice->billing_period_end) {
            $start = Carbon::parse($invoice->billing_period_start);
            $end = Carbon::parse($invoice->billing_period_end);
            
            // For anniversary-based billing, show full date range for clarity
            // e.g., "Mar 12 - Apr 11, 2026" instead of "Mar 2026 - Apr 2026"
            if ($start->year === $end->year) {
                if ($start->month === $end->month) {
                    // Same month: "Mar 2026"
                    return $start->format('M Y');
                } else {
                    // Different months, same year: "Mar 12 - Apr 11, 2026"
                    return $start->format('M j') . ' - ' . $end->format('M j, Y');
                }
            } else {
                // Different years: "Dec 12, 2025 - Jan 11, 2026"
                return $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
            }
        }
        
        // Fallback to due date
        if ($invoice->due_date) {
            return Carbon::parse($invoice->due_date)->format('M Y');
        }
        
        return 'N/A';
    }
}
