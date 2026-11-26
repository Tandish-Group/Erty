<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantLease extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenant_management_id';

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_management_id',
        'business_id',
        'lease_start_date',
        'lease_end_date',
        'lease_duration_months',
        'rent_amount',
        'security_deposit',
        'currency',
        'payment_frequency',
        'payment_day_of_month',
        'late_fee_amount',
        'grace_period_days',
        'lease_type',
        'status',
        'auto_renewal',
        'renewal_notice_days',
        'renewal_rent_increase_percentage',
        'special_terms',
        'terms_and_conditions',
        'utilities_included',
        'restrictions',
        'termination_date',
        'termination_reason',
        'terminated_by',
        'lease_document_path',
    ];

    protected $casts = [
        'lease_start_date' => 'date',
        'lease_end_date' => 'date',
        'termination_date' => 'date',
        'rent_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'renewal_rent_increase_percentage' => 'decimal:2',
        'auto_renewal' => 'boolean',
        'utilities_included' => 'array',
        'restrictions' => 'array',
    ];

    /**
     * Get the tenant management associated with this lease.
     */
    public function tenantManagement(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class);
    }

    /**
     * Get the business that owns this lease.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Check if the lease is expiring soon.
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->lease_end_date) {
            return false;
        }

        return $this->lease_end_date->diffInDays(now()) <= $days && 
               $this->lease_end_date->isFuture();
    }

    /**
     * Get days remaining until lease expiry.
     */
    public function daysUntilExpiry(): ?int
    {
        if (!$this->lease_end_date) {
            return null;
        }

        return now()->diffInDays($this->lease_end_date, false);
    }

    /**
     * Calculate total lease value.
     */
    public function getTotalLeaseValue(): float
    {
        $monthlyPayments = match($this->payment_frequency) {
            'monthly' => $this->lease_duration_months,
            'quarterly' => $this->lease_duration_months / 3,
            'semi-annually' => $this->lease_duration_months / 6,
            'annually' => $this->lease_duration_months / 12,
        };
        
        return $this->rent_amount * $monthlyPayments;
    }
}
