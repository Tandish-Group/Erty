<?php

namespace App\Http\Controllers\Business\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\User;
use App\Models\Listing;
use App\Models\CommercialUnit;
use App\Models\Complex;
use App\Models\ComplexResidentialUnit;
use App\Models\TenantManagement;
use App\Models\Lead;
use App\Models\TenantInvoice;

class DashboardController extends Controller
{
    /**
     * Display the business dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user_id = business_owner_id();
        
        // Get user with business details
        $user = $this->getUserWithBusiness($user_id);
        
        // Check if user is new (for walkthrough)
        $isNewUser = $this->checkIfNewUser($user_id);
        
        // Get dashboard statistics
        $dashboardStats = $this->getDashboardStatistics($user_id);

        return view('pages.business.index', compact('user', 'isNewUser') + $dashboardStats);
    }
    
    /**
     * Get user with business information.
     *
     * @param int $user_id
     * @return mixed
     */
    private function getUserWithBusiness($user_id)
    {
        return User::join('businesses', 'users.id', '=', 'businesses.user_id')
            ->where('users.id', $user_id)
            ->select('users.*', 'businesses.*')
            ->first();
    }
    
    /**
     * Check if user is new (for walkthrough functionality).
     *
     * @param int $user_id
     * @return bool
     */
    private function checkIfNewUser($user_id)
    {
        $user = auth()->user();
        
        // Consider user as "new" if:
        // 1. Account created within last 7 days
        // 2. Has no listings yet
        // 3. Has never logged in before (first login)
        
        $accountAge = $user->created_at->diffInDays(now());
        $hasListings = Listing::where('user_id', $user_id)->exists();
        $isFirstLogin = is_null($user->last_login_at);
        
        return $accountAge <= 70000 || !$hasListings || $isFirstLogin;
    }
    
    /**
     * Get all dashboard statistics.
     *
     * @param int $user_id
     * @return array
     */
    private function getDashboardStatistics($user_id)
    {
        return [
            'listingStats'   => $this->getListingStatistics($user_id),
            'complexStats'   => $this->getComplexStatistics($user_id),
            'tenantStats'    => $this->getTenantStatistics($user_id),
            'leadStats'      => $this->getLeadStatistics($user_id),
            'financialStats' => $this->getFinancialStatistics($user_id),
            'workflowSteps'  => $this->getWorkflowSteps($user_id),
        ];
    }

    /**
     * Build the primary operating path for the business dashboard.
     *
     * This is intentionally derived from existing records while the full
     * workflow state model is being introduced. It gives users a single,
     * clear next action without changing existing module behaviour.
     */
    private function getWorkflowSteps($user_id)
    {
        $complexStats = $this->getComplexStatistics($user_id);
        $listingStats = $this->getListingStatistics($user_id);
        $leadStats = $this->getLeadStatistics($user_id);
        $tenantStats = $this->getTenantStatistics($user_id);

        $hasComplex = $complexStats['total'] > 0;
        $hasUnits = $complexStats['total_units'] > 0;
        $unitsReady = $hasUnits && $complexStats['residential_unassigned_numbers'] === 0;
        $hasAvailableUnits = $complexStats['available_units'] > 0;
        $hasListings = $listingStats['total'] > 0;
        $hasPublished = $listingStats['published'] > 0;
        $hasLeads = $leadStats['total'] > 0;
        $hasTenants = $tenantStats['active'] > 0;

        $listingsEnabled = app(\App\Services\Access\ModuleService::class)->enabled(\App\Services\Access\ModuleService::LISTINGS);

        $steps = [
            [
                'title' => 'Set up your property',
                'description' => 'Create a complex and confirm its property details.',
                'status' => $hasComplex ? 'complete' : 'next',
                'status_label' => $hasComplex ? 'Complete' : 'Start here',
                'action_label' => $hasComplex ? 'Review complexes' : 'Create a complex',
                'url' => $hasComplex ? url('/complex') : route('complex.create'),
            ],
            [
                'title' => 'Confirm unit inventory',
                'description' => 'Review generated residential units or add commercial units manually.',
                'status' => !$hasComplex ? 'blocked' : ($unitsReady ? 'complete' : 'next'),
                'status_label' => !$hasComplex ? 'Needs a complex' : ($unitsReady ? 'Ready to use' : ($hasUnits ? 'Needs review' : 'Next step')),
                'action_label' => 'Review units',
                'url' => url('/complex'),
            ],
        ];

        // Optional Listings module: marketing confirmed units to the public.
        if ($listingsEnabled) {
            $steps[] = [
                'title' => 'Prepare listings',
                'description' => 'Each unit type gets a draft listing. Add rent, deposit, a description and photos, then publish.',
                'status' => !$hasUnits ? 'blocked' : ($hasPublished ? 'complete' : 'next'),
                'status_label' => !$hasUnits ? 'Needs confirmed units' : ($hasPublished ? 'Published' : ($hasListings ? 'Drafts ready' : 'Next step')),
                'action_label' => $hasPublished ? 'Manage listings' : 'Publish a listing',
                'url' => route('listings.index'),
            ];
        }

        // Leads depend on confirmed inventory, not on listings: a lead can be for any unit.
        $steps[] = [
            'title' => 'Work the lead pipeline',
            'description' => 'Capture leads, qualify them, and assign follow-up work.',
            'status' => !$hasUnits ? 'blocked' : ($hasLeads ? 'complete' : 'next'),
            'status_label' => !$hasUnits ? 'Needs confirmed units' : ($hasLeads ? 'Active pipeline' : 'Start CRM'),
            'action_label' => 'Open CRM',
            'url' => url('/customer_relationship/lead_management'),
        ];

        $steps[] = [
            'title' => 'Onboard tenants',
            'description' => 'Assign a confirmed tenant to an available unit.',
            'status' => !$hasAvailableUnits ? 'blocked' : ($hasTenants ? 'complete' : 'next'),
            'status_label' => !$hasAvailableUnits ? 'Needs an available unit' : ($hasTenants ? 'Active tenants' : 'Ready to onboard'),
            'action_label' => 'Onboard a tenant',
            'url' => url('/tenant_management/onboarding/tenant'),
        ];

        // Only steps in sections this person can work in, and only buttons they can use.
        $user = auth()->user();
        foreach ($steps as &$step) {
            if ($step['url'] === route('complex.create') && !$user->hasBusinessPermission('complex_management', 'create')) {
                [$step['action_label'], $step['url']] = ['Open properties', url('/complex')];
            }
            if ($step['title'] === 'Onboard tenants' && !$user->hasBusinessPermission('tenant_management', 'create')) {
                [$step['action_label'], $step['url']] = ['View tenants', url('/tenant_management')];
            }
            if ($step['title'] === 'Work the lead pipeline' && !$user->hasBusinessPermission('crm_management', 'view')) {
                [$step['action_label'], $step['url']] = ['View leads', url('/customer_relationship/lead_management')];
            }
        }
        unset($step);
        $required = ['Set up your property' => 'complex_management', 'Confirm unit inventory' => 'complex_management', 'Prepare listings' => 'listings_management',
            'Work the lead pipeline' => 'lead_management', 'Onboard tenants' => 'tenant_management'];
        $steps = array_values(array_filter($steps, fn ($step) => !isset($required[$step['title']]) || $user->hasBusinessPermission($required[$step['title']], 'view')));

        foreach ($steps as $index => &$step) {
            $step = ['number' => $index + 1] + $step;
        }

        return $steps;
    }
    
    /**
     * Get listing statistics.
     *
     * @param int $user_id
     * @return array
     */
    private function getListingStatistics($user_id)
    {
        $listings = Listing::where('user_id', $user_id)->get(['id', 'status', 'complex_unit_id', 'commercial_unit_id']);
        $published = $listings->where('status', Listing::PUBLISHED);
        $free = $published->isEmpty() ? [] : app(\App\Services\Listings\UnitListingService::class)->availability($published, true);

        return [
            'total' => $listings->count(),
            'published' => $published->count(),
            'draft' => $listings->where('status', Listing::DRAFT)->count(),
            // Vacant units renters can see right now (published listings only).
            'available' => array_sum($free),
        ];
    }
    
    
    
    
    private function getComplexStatistics($user_id)
    {
        $complexIds = Complex::forBusiness($user_id)->pluck('id')->toArray();
        $total      = count($complexIds);

        if (empty($complexIds)) {
            // A new business has no properties yet: same keys as below, all zero.
            return array_fill_keys(['total', 'total_units', 'available_units', 'vacant_units', 'occupied_units',
                'residential_units', 'residential_vacant', 'residential_occupied', 'residential_unassigned_numbers',
                'commercial_units', 'commercial_vacant', 'commercial_occupied'], 0);
        }

        // Residential units live in complex_residential_units (status lowercase)
        $resBase = ComplexResidentialUnit::whereIn('complex_id', $complexIds);

        // Commercial units live in a separate commercial_units table (status Title Case)
        $comBase = CommercialUnit::whereIn('complex_id', $complexIds);

        $residentialUnits = (clone $resBase)->count();
        $commercialUnits = (clone $comBase)->count();
        $residentialAvailable = (clone $resBase)->where('status', 'available')->count();
        $commercialAvailable = (clone $comBase)->where('status', 'Available')->count();
        $residentialOccupied = (clone $resBase)->where('status', 'occupied')->count();
        $commercialOccupied = (clone $comBase)->where('status', 'Occupied')->count();

        return [
            'total'                => $total,
            'total_units'          => $residentialUnits + $commercialUnits,
            'available_units'      => $residentialAvailable + $commercialAvailable,
            'vacant_units'         => $residentialAvailable + $commercialAvailable,
            'occupied_units'       => $residentialOccupied + $commercialOccupied,
            'residential_units'    => $residentialUnits,
            'residential_vacant'   => $residentialAvailable,
            'residential_occupied' => $residentialOccupied,
            'residential_unassigned_numbers' => (clone $resBase)->whereNull('unit_number')->count(),
            'commercial_units'     => $commercialUnits,
            'commercial_vacant'    => $commercialAvailable,
            'commercial_occupied'  => $commercialOccupied,
        ];
    }

    private function getTenantStatistics($user_id)
    {
        $complexIds = Complex::forBusiness($user_id)->pluck('id')->toArray();

        if (empty($complexIds)) {
            return ['total' => 0, 'active' => 0, 'move_in_this_month' => 0, 'expiring_soon' => 0];
        }

        $base = TenantManagement::whereIn('complex_id', $complexIds);
        // A tenancy is active by its status; open-ended rentals have no end date.
        $active = (clone $base)->where('status', 'Active');

        return [
            // People (tenant profiles) holding at least one active tenancy.
            'total'              => (clone $active)->distinct()->count('tenant_profile_id'),
            'active'             => (clone $active)->count(),
            'move_in_this_month' => (clone $base)->whereMonth('move_in_date', now()->month)
                                                  ->whereYear('move_in_date', now()->year)->count(),
            'expiring_soon'      => (clone $active)->whereBetween('lease_end_date', [
                now()->toDateString(),
                now()->addDays(30)->toDateString(),
            ])->count(),
        ];
    }

    private function getLeadStatistics($user_id)
    {
        $base = Lead::forBusiness($user_id);

        return [
            'total'           => (clone $base)->count(),
            'open'            => (clone $base)->whereNotIn('status', ['Closed', 'Converted'])->count(),
            'hot'             => (clone $base)->where('priority', 'high')->count(),
            'new_this_week'   => (clone $base)->whereBetween('created_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ])->count(),
            'follow_up_today' => (clone $base)->whereDate('follow_up_date', Carbon::today())->count(),
        ];
    }

    private function getFinancialStatistics($user_id)
    {
        $complexIds = Complex::forBusiness($user_id)->pluck('id')->toArray();

        if (empty($complexIds)) {
            return ['collected_this_month' => 0, 'pending_invoices' => 0, 'overdue_amount' => 0, 'overdue_count' => 0];
        }

        $tenancyIds = TenantManagement::whereIn('complex_id', $complexIds)->pluck('id')->toArray();

        if (empty($tenancyIds)) {
            return ['collected_this_month' => 0, 'pending_invoices' => 0, 'overdue_amount' => 0, 'overdue_count' => 0];
        }

        $base = TenantInvoice::whereIn('tenancy_id', $tenancyIds);

        return [
            'collected_this_month' => (clone $base)->whereNotNull('paid_at')
                ->whereMonth('paid_at', now()->month)
                ->whereYear('paid_at', now()->year)
                ->sum('paid_amount'),
            'pending_invoices'     => (clone $base)->where('status', 'Unpaid')->count(),
            'overdue_amount'       => (clone $base)->where('status', 'Unpaid')
                ->where('due_date', '<', now()->toDateString())
                ->sum('amount'),
            'overdue_count'        => (clone $base)->where('status', 'Unpaid')
                ->where('due_date', '<', now()->toDateString())
                ->count(),
        ];
    }

    /**
     * Mark user as having completed walkthrough.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeWalkthrough(Request $request)
    {
        $user_id = business_owner_id();
        
        // Update user to mark walkthrough as completed
        User::where('id', $user_id)->update([
            'walkthrough_completed' => true,
            'walkthrough_completed_at' => now(),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Walkthrough completed successfully!'
        ]);
    }
}
