<?php

namespace App\Services\Tenancy;

use App\Services\ExchangeRateService;

use App\Models\Complex;
use App\Models\CommercialUnit;
use App\Models\ComplexResidentialUnit;
use App\Models\TenantManagement;

class OverviewTenantStatsService
{
    
    public function __construct(private ExchangeRateService $exchangeRates) {}
    
    public function calculateTenantManagementStats($userId)
    {
        // Get all complexes owned by user
        $complexes = Complex::forBusiness($userId)->get();
        $totalComplexes = $complexes->count();
        
        $complexIds = $complexes->pluck('id');

        // Get commercial units for this user's complexes
        $commercialUnits = CommercialUnit::whereIn('complex_id', $complexIds)->get();
        
        // Get all active tenant managements
        $activeTenants = TenantManagement::forBusiness($userId)
            ->where('status', 'Active')
            ->with(['complex', 'commercialUnit', 'tenantProfile'])
            ->get();
        
        $allTenants = TenantManagement::forBusiness($userId)
            ->with(['complex', 'commercialUnit', 'tenantProfile'])
            ->get();
        
        // Calculate occupancy statistics
        $occupancyStats = $this->calculateOccupancyStats($activeTenants, $commercialUnits, $complexes);
        
        // Calculate financial statistics
        $financialStats = $this->calculateFinancialStats($allTenants);
        
        // Calculate property type distribution
        $propertyTypeStats = $this->calculatePropertyTypeStats($allTenants);
        
        // Calculate tenant type distribution
        $tenantTypeStats = $this->calculateTenantTypeStats($allTenants);
        
        // Calculate lease status distribution
        $leaseStatusStats = $this->calculateLeaseStatusStats($allTenants);
        
        // Calculate complex-wise occupancy
        // $complexOccupancy = $this->calculateComplexOccupancy($complexes, $allTenants);
        
        return [
            'overview' => [
                'total_complexes' => $totalComplexes,
                'total_available_units' => $occupancyStats['total_capacity'],
                'total_commercial_units' => $commercialUnits->count(),
                'total_commercial_capacity' => $commercialUnits->sum('max_subdivisions'),
                'total_commercial_space' => $commercialUnits->sum('unit_sq_ft'),
                'total_tenants' => $allTenants->count(),
                'active_tenants' => $activeTenants->count(),
                'inactive_tenants' => $allTenants->where('status', '!=', 'Active')->count(),
            ],
            'occupancy' => $occupancyStats,
            'financial' => $financialStats,
            'property_types' => $propertyTypeStats,
            'tenant_types' => $tenantTypeStats,
            'lease_status' => $leaseStatusStats,
            // 'complex_occupancy' => $complexOccupancy,
        ];
    }
 
    private function calculateOccupancyStats($activeTenants, $commercialUnits, $complexes)
    {
        $complexIds = $complexes->pluck('id');

        // ── COMMERCIAL ────────────────────────────────────────────────────────────
        $totalCommercialSpace    = $commercialUnits->sum('unit_sq_ft');
        $totalCommercialSlots    = $commercialUnits->sum('max_subdivisions');
        $commercialTenantCount   = $activeTenants->where('listing_type', 'Commercial Unit')->count();
        $occupiedCommercialSpace = $activeTenants->where('listing_type', 'Commercial Unit')
            ->sum(fn($t) => $t->occupied_sq_area ?? $t->sq_area ?? 0);

        $effectiveOccupiedSlots  = 0;
        $effectiveAvailableSlots = 0;
        foreach ($commercialUnits as $unit) {
            $unitSqFt = $unit->unit_sq_ft ?? 0;
            $maxSubs  = $unit->max_subdivisions ?? 0;
            if ($maxSubs <= 0 || $unitSqFt <= 0) {
                continue;
            }
            $subdivisionSize = $unitSqFt / $maxSubs;
            $unitOccupied    = $activeTenants->where('listing_type', 'Commercial Unit')
                ->where('commercial_unit_id', $unit->id)
                ->sum(fn($t) => $t->occupied_sq_area ?? $t->sq_area ?? 0);
            $slotsUsed = min($maxSubs, (int) ceil($unitOccupied / $subdivisionSize));
            $effectiveOccupiedSlots  += $slotsUsed;
            $effectiveAvailableSlots += $maxSubs - $slotsUsed;
        }

        // ── RESIDENTIAL APARTMENTS (complexes with individual unit instances) ─────
        // Capacity  = actual ComplexResidentialUnit records
        // Occupied  = those linked to an active tenant via residential_unit_id
        $residentialUnits = ComplexResidentialUnit::whereIn('complex_id', $complexIds)->get();
        $occupiedResidentialIds = $activeTenants
            ->where('listing_type', '!=', 'Commercial Unit')
            ->whereNotNull('residential_unit_id')
            ->pluck('residential_unit_id')
            ->unique();
        $residentialCapacity = $residentialUnits->count();
        $residentialOccupied = $residentialUnits->whereIn('id', $occupiedResidentialIds)->count();

        // ── STANDALONE (houses / properties with no individual unit instances) ────
        // Each such complex counts as 1 unit; it's occupied if it has an active tenant
        // that is not linked to a residential_unit_id.
        $complexesWithResUnits  = $residentialUnits->pluck('complex_id')->unique();
        $complexesWithCommUnits = $commercialUnits->pluck('complex_id')->unique();
        $standaloneComplexes    = $complexes
            ->whereNotIn('id', $complexesWithResUnits)
            ->whereNotIn('id', $complexesWithCommUnits);
        $standaloneCapacity = $standaloneComplexes->count();
        $standaloneOccupied = $activeTenants
            ->where('listing_type', '!=', 'Commercial Unit')
            ->whereNull('residential_unit_id')
            ->pluck('complex_id')
            ->unique()
            ->intersect($standaloneComplexes->pluck('id'))
            ->count();

        // ── TOTALS ────────────────────────────────────────────────────────────────
        $totalResidentialCapacity = $residentialCapacity + $standaloneCapacity;
        $totalResidentialOccupied = $residentialOccupied + $standaloneOccupied;

        $totalCapacity      = $totalResidentialCapacity + $totalCommercialSlots;
        $totalOccupied      = $totalResidentialOccupied + $effectiveOccupiedSlots;
        $occupancyPercentage = $totalCapacity > 0
            ? round(($totalOccupied / $totalCapacity) * 100, 2)
            : 0;

        $commercialSpaceOccupancy = $totalCommercialSpace > 0
            ? round(($occupiedCommercialSpace / $totalCommercialSpace) * 100, 2)
            : 0;

        return [
            'total_capacity'     => $totalCapacity,
            'total_occupied'     => $totalOccupied,
            'total_available'    => $totalCapacity - $totalOccupied,
            'occupancy_percentage' => $occupancyPercentage,
            'regular_units' => [
                'available' => $totalResidentialCapacity,
                'occupied'  => $totalResidentialOccupied,
                'vacant'    => $totalResidentialCapacity - $totalResidentialOccupied,
                'apartment_capacity' => $residentialCapacity,
                'apartment_occupied' => $residentialOccupied,
                'standalone_capacity' => $standaloneCapacity,
                'standalone_occupied' => $standaloneOccupied,
            ],
            'commercial_units' => [
                'total_units'             => $commercialUnits->count(),
                'total_capacity'          => $totalCommercialSlots,
                'occupied_tenants'        => $commercialTenantCount,
                'effective_occupied_slots'  => $effectiveOccupiedSlots,
                'effective_available_slots' => $effectiveAvailableSlots,
                'total_space_sqft'        => $totalCommercialSpace,
                'occupied_space_sqft'     => $occupiedCommercialSpace,
                'available_space_sqft'    => max(0, $totalCommercialSpace - $occupiedCommercialSpace),
                'space_occupancy_percentage' => $commercialSpaceOccupancy,
            ],
        ];
    }

    private function calculateFinancialStats($tenants)
    {
        $defaultCurrency = default_currency();
        $exchangeRates = $this->exchangeRates->rates();

        // Standardize amounts to the default currency
        $tenants->each(function ($tenant) use ($defaultCurrency, $exchangeRates) {
            $currency = $tenant->currency ?? $defaultCurrency;
            $rate = $exchangeRates[$currency] ?? 1; // Default to 1 if the currency is not in the exchange rates
            $paymentFrequency = strtolower($tenant->payment_frequency ?? 'monthly');
        
            // Normalize amounts to monthly
            $tenant->monthly_payment = match ($paymentFrequency) {
                'quarterly' => ($tenant->monthly_payment / 3) / $rate,
                'annually' => ($tenant->monthly_payment / 12) / $rate,
                default => $tenant->monthly_payment / $rate, // Assume monthly by default
            };
        });

        $totalMonthlyRevenue = $tenants->where('status', 'Active')->sum('monthly_payment');
        $totalSecurityDeposits = $tenants->sum('security_deposit');
        $averageRent = $tenants->where('monthly_payment', '>', 0)->avg('monthly_payment');
        
        // Calculate potential revenue (if all units were occupied)
        $potentialMonthlyRevenue = $tenants->sum('monthly_payment');
        
        return [
            'currency' => $defaultCurrency,
            'total_monthly_revenue' => $totalMonthlyRevenue,
            'potential_monthly_revenue' => $potentialMonthlyRevenue,
            'revenue_loss' => $potentialMonthlyRevenue - $totalMonthlyRevenue,
            'total_security_deposits' => $totalSecurityDeposits,
            'average_rent' => $averageRent,
            'annual_revenue_projection' => $totalMonthlyRevenue * 12,
        ];
    }

    private function calculatePropertyTypeStats($tenants)
    {
        $propertyTypes = $tenants->groupBy('listing_type')->map(function($group) {
            return [
                'count' => $group->count(),
                'percentage' => 0, // Will calculate below
                'active_count' => $group->where('status', 'Active')->count(),
                'total_revenue' => $group->where('status', 'Active')->sum('monthly_payment'),
            ];
        });
        
        $totalTenants = $tenants->count();
        
        // Fix: Convert to array first, then modify, or use transform()
        $propertyTypes = $propertyTypes->map(function($data) use ($totalTenants) {
            $data['percentage'] = $totalTenants > 0 ? 
                round(($data['count'] / $totalTenants) * 100, 1) : 0;
            return $data;
        });
        
        return $propertyTypes->toArray();
    }

    private function calculateTenantTypeStats($tenants)
    {
        $tenantTypes = ['Individual' => 0, 'Business' => 0, 'Unknown' => 0];
        $revenueByType = ['Individual' => 0, 'Business' => 0, 'Unknown' => 0];
        
        foreach ($tenants as $tenant) {
            $type = $tenant->tenantProfile->tenant_type ?? 'Unknown';
            $tenantTypes[$type]++;
            
            if ($tenant->status === 'Active') {
                $revenueByType[$type] += $tenant->monthly_payment ?? 0;
            }
        }
        
        $totalTenants = $tenants->count();
        
        return [
            'individual' => [
                'count' => $tenantTypes['Individual'],
                'percentage' => $totalTenants > 0 ? round(($tenantTypes['Individual'] / $totalTenants) * 100, 1) : 0,
                'revenue' => $revenueByType['Individual'],
            ],
            'business' => [
                'count' => $tenantTypes['Business'],
                'percentage' => $totalTenants > 0 ? round(($tenantTypes['Business'] / $totalTenants) * 100, 1) : 0,
                'revenue' => $revenueByType['Business'],
            ],
            'unknown' => [
                'count' => $tenantTypes['Unknown'],
                'percentage' => $totalTenants > 0 ? round(($tenantTypes['Unknown'] / $totalTenants) * 100, 1) : 0,
                'revenue' => $revenueByType['Unknown'],
            ],
        ];
    }
    
    private function calculateLeaseStatusStats($tenants)
    {
        $statusCounts = $tenants->groupBy('status')->map->count();
        $totalTenants = $tenants->count();
        
        // Calculate lease expiry warnings
        $expiringThisMonth = $tenants->filter(function($tenant) {
            if (!$tenant->lease_end_date) return false;
            return $tenant->lease_end_date->isCurrentMonth();
        })->count();
        
        $expiringNext30Days = $tenants->filter(function($tenant) {
            if (!$tenant->lease_end_date) return false;
            return $tenant->lease_end_date->isBetween(now(), now()->addDays(30));
        })->count();
        
        return [
            'active' => $statusCounts['Active'] ?? 0,
            'inactive' => $statusCounts['Inactive'] ?? 0,
            'pending' => $statusCounts['Pending'] ?? 0,
            'terminated' => $statusCounts['Terminated'] ?? 0,
            'overdue' => $statusCounts['Overdue'] ?? 0,
            'expiring_this_month' => $expiringThisMonth,
            'expiring_next_30_days' => $expiringNext30Days,
        ];
    }
   
    public function calculateComplexOccupancy($complexes, $tenants)
    {
        $complexOccupancy = [];
        $defaultCurrency = default_currency();
        $exchangeRates = $this->exchangeRates->rates();
        
        foreach ($complexes as $complex) {
            $complexTenants = $tenants->where('complex_id', $complex->id);
            $activeTenants = $complexTenants->where('status', 'Active');
            
            // Get commercial units for this complex
            $commercialUnits = CommercialUnit::where('complex_id', $complex->id)->get();
            $commercialCapacity = $commercialUnits->sum('max_subdivisions');
            $commercialTenantsCount = $activeTenants->where('listing_type', 'Commercial Unit')->count();

            // ── Residential apartment units (individual instances) ────────────────
            $residentialUnits = ComplexResidentialUnit::where('complex_id', $complex->id)->get();
            $occupiedResidentialIds = $activeTenants
                ->where('listing_type', '!=', 'Commercial Unit')
                ->whereNotNull('residential_unit_id')
                ->pluck('residential_unit_id')
                ->unique();
            $residentialCapacity = $residentialUnits->count();
            $residentialOccupied = $residentialUnits->whereIn('id', $occupiedResidentialIds)->count();

            // ── Standalone (house / no unit instances) ────────────────────────────
            $isStandalone = $residentialCapacity === 0 && $commercialUnits->isEmpty();
            $standaloneCapacity = $isStandalone ? 1 : 0;
            $standaloneOccupied = ($isStandalone && $activeTenants
                ->where('listing_type', '!=', 'Commercial Unit')
                ->whereNull('residential_unit_id')
                ->where('complex_id', $complex->id)
                ->count() > 0) ? 1 : 0;

            $regularUnits    = $residentialCapacity + $standaloneCapacity;
            $regularOccupied = $residentialOccupied + $standaloneOccupied;

            // ── Commercial slot occupancy (sq ft based) ───────────────────────────
            $effectiveOccupiedSlots = 0;
            foreach ($commercialUnits as $unit) {
                $unitSqFt = $unit->unit_sq_ft ?? 0;
                $maxSubs  = $unit->max_subdivisions ?? 0;
                if ($maxSubs <= 0 || $unitSqFt <= 0) continue;
                $subdivisionSize = $unitSqFt / $maxSubs;
                $unitOccupied = $activeTenants->where('listing_type', 'Commercial Unit')
                    ->where('commercial_unit_id', $unit->id)
                    ->sum(fn($t) => $t->occupied_sq_area ?? $t->sq_area ?? 0);
                $effectiveOccupiedSlots += min($maxSubs, (int) ceil($unitOccupied / $subdivisionSize));
            }

            $totalCapacity = $regularUnits + $commercialCapacity;
            $totalOccupied = $regularOccupied + $effectiveOccupiedSlots;
            
            $occupancyPercentage = $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100, 1) : 0;

            // Calculate monthly revenue in the default currency
            $monthlyRevenue = $activeTenants->sum(function ($tenant) use ($defaultCurrency, $exchangeRates) {
                $currency = $tenant->currency ?? $defaultCurrency;
                $rate = $exchangeRates[$currency] ?? 1; // Default to 1 if the currency is not in the exchange rates
                $paymentFrequency = strtolower($tenant->payment_frequency ?? 'monthly');

                // Normalize payment amounts to monthly
                $normalizedAmount = match ($paymentFrequency) {
                    'quarterly' => $tenant->monthly_payment / 3, // Divide quarterly payments by 3
                    'annually' => $tenant->monthly_payment / 12, // Divide annual payments by 12
                    default => $tenant->monthly_payment, // Assume monthly by default
                };

                return $normalizedAmount / $rate; // Convert to the default currency
            });
            
            $complexOccupancy[] = [
                'id'                  => $complex->id,
                'title'               => $complex->title,
                'type'                => $complex->type,
                'ref_code'            => $complex->ref_code,
                'total_capacity'      => $totalCapacity,
                'total_occupied'      => $totalOccupied,
                'occupancy_percentage' => $occupancyPercentage,
                'regular_units' => [
                    'available'           => $regularUnits,
                    'occupied'            => $regularOccupied,
                    'apartment_capacity'  => $residentialCapacity,
                    'apartment_occupied'  => $residentialOccupied,
                    'standalone_capacity' => $standaloneCapacity,
                    'standalone_occupied' => $standaloneOccupied,
                ],
                'commercial_units' => [
                    'count'            => $commercialUnits->count(),
                    'capacity'         => $commercialCapacity,
                    'tenants'          => $commercialTenantsCount,
                    'effective_occupied' => $effectiveOccupiedSlots,
                ],
                'monthly_revenue'     => round($monthlyRevenue, 2),
                'currency'            => $defaultCurrency,
                'tenant_count'        => $complexTenants->count(),
                'active_tenant_count' => $activeTenants->count(),
            ];
        }

        return $complexOccupancy;
    }
}