<?php

namespace App\Services\Tenancy;

use App\Models\TenantNotice;
use App\Models\TenantPayment;
use App\Models\TenantLateFee;
use App\Models\TenantManagement;
use Illuminate\Support\Facades\Storage;

class TenancyDetailsService
{
    
    public function getNoticeStatistics($tenantId)
    {
        $notices = TenantNotice::where('tenant_id', $tenantId);
        
        return [
            'total_notices' => $notices->count(),
            'notices_this_year' => $notices->whereYear('notice_date', now()->year)->count(),
            'notice_types' => $notices->distinct('notice_type')->pluck('notice_type'),
            'last_notice_date' => $notices->max('notice_date'),
            'pending_notices' => $notices->where('delivery_status', 'pending')->count(),
            'sent_notices' => $notices->where('delivery_status', 'sent')->count(),
        ];
    }
    
    public function calculateLeaseInformation($tenant)
    {
        if (!$tenant->lease_start_date) {
            return [
                'status' => 'No Lease',
                'days_remaining' => null,
                'progress_percentage' => 0,
                'is_expiring_soon' => false,
            ];
        }
    
        $now = now();
        $startDate = $tenant->lease_start_date;
        $endDate = $tenant->lease_end_date;
    
        if (!$endDate) {
            return [
                'status' => 'Open-ended',
                'days_remaining' => null,
                'progress_percentage' => 0,
                'is_expiring_soon' => false,
            ];
        }
    
        $totalDays = $startDate->diffInDays($endDate);
        $daysElapsed = $startDate->diffInDays($now);
        $daysRemaining = $now->diffInDays($endDate, false);
    
        $progressPercentage = $totalDays > 0 ? min(100, ($daysElapsed / $totalDays) * 100) : 0;
    
        $status = 'Active';
        if ($now->lt($startDate)) {
            $status = 'Not Started';
        } elseif ($now->gt($endDate)) {
            $status = 'Expired';
        } elseif ($daysRemaining <= 30 && $daysRemaining > 0) {
            $status = 'Expiring Soon';
        }
    
        return [
            'status' => $status,
            'total_days' => $totalDays,
            'days_elapsed' => max(0, $daysElapsed),
            'days_remaining' => max(0, $daysRemaining),
            'progress_percentage' => round($progressPercentage, 1),
            'is_expiring_soon' => $daysRemaining <= 30 && $daysRemaining > 0,
            'months_remaining' => $daysRemaining > 0 ? ceil($daysRemaining / 30) : 0,
        ];
    }
    
    public function getCommercialUnitOccupancyDetails($commercialUnitId, $userId)
    {
        // Get all active tenants in this commercial unit
        $unitTenants = TenantManagement::with([
                'tenantProfile' => fn($q) => $q->select('id', 'tenant_ref', 'tenant_name', 'business_name', 'tenant_type'),
            ])
            ->where('commercial_unit_id', $commercialUnitId)
            ->where('managed_by', $userId)
            ->where('status', 'Active')
            ->select('id', 'tenant_profile_id', 'tenancy_ref', 'occupied_sq_area', 'sq_area', 'monthly_payment')
            ->get();
    
        // Get commercial unit details
        $commercialUnit = \App\Models\CommercialUnit::find($commercialUnitId);
        
        if (!$commercialUnit) {
            return null;
        }
    
        // Calculate occupancy
        $totalOccupiedSpace = $unitTenants->sum(function($tenant) {
            return $tenant->tenantProfile?->tenant_type === 'Business'
                ? ($tenant->occupied_sq_area ?? 0)
                : ($tenant->sq_area ?? 0);
        });
    
        $remainingSpace = max(0, $commercialUnit->unit_sq_ft - $totalOccupiedSpace);
        $remainingSubdivisions = max(0, $commercialUnit->max_subdivisions - $unitTenants->count());
        $occupancyPercentage = $commercialUnit->unit_sq_ft > 0 
            ? round(($totalOccupiedSpace / $commercialUnit->unit_sq_ft) * 100, 2) 
            : 0;
    
        return [
            'unit_details' => [
                'unit_number' => $commercialUnit->unit_number,
                'total_sq_ft' => $commercialUnit->unit_sq_ft,
                'min_sq_ft' => $commercialUnit->min_sq_ft,
                'max_subdivisions' => $commercialUnit->max_subdivisions,
                'rate_per_sq_ft' => $commercialUnit->rate_per_sq_ft,
                'effective_floor' => $commercialUnit->custom_floors ?: $commercialUnit->floors,
            ],
            'occupancy_summary' => [
                'total_tenants' => $unitTenants->count(),
                'total_occupied_space' => $totalOccupiedSpace,
                'remaining_space' => $remainingSpace,
                'remaining_subdivisions' => $remainingSubdivisions,
                'occupancy_percentage' => $occupancyPercentage,
                'is_fully_occupied' => $remainingSubdivisions <= 0,
            ],
            'co_tenants' => $unitTenants->map(function($tenant) {
                $profile = $tenant->tenantProfile;
                return [
                    'tenant_ref' => $profile?->tenant_ref,
                    'display_name' => $profile?->tenant_type === 'Business' ? $profile?->business_name : $profile?->tenant_name,
                    'tenant_type' => $profile?->tenant_type,
                    'occupied_space' => $profile?->tenant_type === 'Business'
                        ? ($tenant->occupied_sq_area ?? 0)
                        : ($tenant->sq_area ?? 0),
                    'monthly_payment' => $tenant->monthly_payment,
                ];
            })->toArray(),
        ];
    }
    
    public function getRecentActivity($tenantId)
    {
        $activities = [];
    
        // Get recent payments
        $recentPayments = TenantPayment::where('tenant_id', $tenantId)
            ->orderBy('payment_date', 'desc')
            ->limit(5)
            ->get();
    
        foreach ($recentPayments as $payment) {
            $activities[] = [
                'type' => 'payment',
                'date' => $payment->payment_date,
                'title' => 'Payment Received',
                'description' => "Payment of KES " . number_format($payment->total_amount) . " for " . $payment->formatted_payment_period,
                'amount' => $payment->total_amount,
                'status' => $payment->payment_status,
            ];
        }
    
        // Get recent notices
        $recentNotices = TenantNotice::where('tenant_id', $tenantId)
            ->orderBy('notice_date', 'desc')
            ->limit(5)
            ->get();
    
        foreach ($recentNotices as $notice) {
            $activities[] = [
                'type' => 'notice',
                'date' => $notice->notice_date,
                'title' => 'Notice Issued',
                'description' => $notice->notice_subject,
                'notice_type' => $notice->notice_type,
                'status' => $notice->delivery_status,
            ];
        }
    
        // Sort by date descending
        usort($activities, function($a, $b) {
            return $b['date'] <=> $a['date'];
        });
    
        return array_slice($activities, 0, 10); // Return top 10 recent activities
    }
    
    public function calculateFinancialSummary($tenantId)
    {
        $currentYear = now()->year;
        $currentMonth = now()->format('Y-m');
        
        $payments = TenantPayment::where('tenant_id', $tenantId);
        $paymentIds = (clone $payments)->pluck('id');

        return [
            'total_revenue' => $payments->sum('amount_paid'),
            'revenue_this_year' => (clone $payments)->whereYear('payment_date', $currentYear)->sum('amount_paid'),
            'revenue_this_month' => (clone $payments)->where('payment_period', $currentMonth)->sum('amount_paid'),
            'total_late_fees' => TenantLateFee::whereIn('payment_id', $paymentIds)->sum('late_fee_amount'),
            'outstanding_balance' => 0,
            'average_monthly_payment' => $payments->avg('amount_paid'),
        ];
    }
    
    public function transformDocumentsForDisplay($documents)
    {
        if (empty($documents) || !is_array($documents)) {
            return [];
        }
    
        return array_map(function($doc, $index) {
            return [
                'index' => $index,
                'name' => $doc['original_name'] ?? 'Unknown',
                'size' => file_size($doc['size'] ?? 0),
                'type' => $doc['file_type'] ?? 'Unknown',
                'uploaded_at' => isset($doc['uploaded_at']) 
                    ? \Carbon\Carbon::parse($doc['uploaded_at'])->format('M j, Y H:i')
                    : null,
                'url' => isset($doc['path']) ? Storage::url($doc['path']) : null,
                'exists' => isset($doc['path']) ? Storage::disk('public')->exists($doc['path']) : false,
            ];
        }, $documents, array_keys($documents));
    }
}