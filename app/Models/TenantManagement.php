<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantManagement extends Model
{
    use \App\Models\Concerns\BelongsToBusiness;

    protected string $businessOwnerColumn = 'managed_by';

    use \App\Models\Concerns\RestrictedToAssignedProperties;
    /** On properties a member reaches only through units, only these units' rows (PropertyAccess::unitScope). */
    protected array $unitColumns = ['residential' => 'residential_unit_id', 'commercial' => 'commercial_unit_id'];

    use HasFactory;

    protected $table = 'tenant_management';

    protected $fillable = [
        // Profile Reference
        'tenant_profile_id',
        'previous_tenancy_id',
        'rent_paid_up_to',
        'billing_start_date',
        'tenancy_ref',
        'currency',                     
        
        // Property Information
        'listing_type',
        'complex_id',
        'complex_name',                 
        'furnishing',
        'bedrooms',
        'bathrooms',
        'sq_area',
        'unit_number',
        'commercial_unit_id',
        'residential_unit_id',
        'occupied_sq_area',
        'rate_per_sq_ft',
        'specific_unit_number',
        'service_charge_per_sq_ft',
        'assigned_agent_id',
        
        // Agreement Details
        'agreement_type',
        'monthly_payment',
        'security_deposit',
        'move_in_date',
        'lease_start_date',
        'lease_end_date',
        'lease_duration_months',
        'special_terms',
        'agreement_start_date',         
        'agreement_end_date',           
        'grace_period_days',      
        'build_out_period_days',      
        'late_fee_amount',              
        'penalty_amount',             
        'tenancy_quarterly_payment_type',
        
        // Additional Information
        'number_of_occupants',
        'has_pets',
        'pet_details',
        'additional_notes',
        'general_notes',                
        'internal_notes',               
        'uploaded_documents',
        
        // Financial Information
        'deposit_paid',                 
        'deposit_paid_date',            
        'payment_frequency',            
        'payment_due_day',              
        
        // Status and Management
        'status',
        'notice_given_date',            
        'expected_move_out_date',       
        'termination_reason',           
        'managed_by',
        'created_by',                   
        'updated_by',                   
    ];

    protected $casts = [
        // Date fields
        'move_in_date' => 'date',
        'lease_start_date' => 'date',
        'lease_end_date' => 'date',
        'agreement_start_date' => 'date',       
        'agreement_end_date' => 'date',         
        'deposit_paid_date' => 'date',
        'rent_paid_up_to' => 'date',
        'billing_start_date' => 'date',          
        'notice_given_date' => 'date',          
        'expected_move_out_date' => 'date',     
        
        // Decimal fields
        'monthly_payment' => 'decimal:2',
        'security_deposit' => 'decimal:2',
        'sq_area' => 'decimal:2',
        'occupied_sq_area' => 'decimal:2',
        'rate_per_sq_ft' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',       
        'penalty_amount' => 'decimal:2',        
        'deposit_paid' => 'decimal:2',         
        
        'tenancy_quarterly_payment_type' => 'string',
        
        // Boolean fields
        'has_pets' => 'boolean',
        
        // JSON fields
        'uploaded_documents' => 'array',
        
        // Integer fields
        'grace_period_days' => 'integer',       
        'payment_due_day' => 'integer',         
        
        // Timestamp fields
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'Pending',
        'number_of_occupants' => 1,
        'has_pets' => false,
        'payment_frequency' => 'Monthly',       
        'payment_due_day' => 1,                 
    ];

    // Relationships
    public function tenantProfile(): BelongsTo
    {
        return $this->belongsTo(TenantProfile::class, 'tenant_profile_id');
    }

    public function lease()
    {
        return $this->hasOne(TenantLease::class, 'tenant_management_id');
    }

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }

    public function commercialUnit(): BelongsTo
    {
        return $this->belongsTo(CommercialUnit::class, 'commercial_unit_id');
    }

    public function residentialUnit(): BelongsTo
    {
        return $this->belongsTo(ComplexResidentialUnit::class, 'residential_unit_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by');
    }

    public function creator(): BelongsTo       
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo         
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lateFees(): HasMany
    {
        return $this->hasMany(TenantLateFee::class, 'tenant_id');
    }

    public function billings()
    {
        return $this->hasMany(TenantBilling::class, 'tenancy_id');
    }

    public function amenities()
    {
        return $this->belongsToMany(
            ComplexAmenity::class,
            'tenant_management_amenities', 
            'tenant_management_id',
            'amenity_id'
        )->withTimestamps();
    }

    public function parkingSlots()
    {
        return $this->hasMany(ComplexParkingSlot::class, 'occupied_tenant_id')
                    ->where('is_additional_slot', false)
                    ->where('is_occupied', true);
    }

    public function additionalParkingSlots()
    {
        return $this->hasMany(ComplexParkingSlot::class, 'occupied_tenant_id')->where('is_additional_slot', true)->where('is_occupied', true);
    }

    public function getIsCommercialAttribute(): bool
    {
        return $this->listing_type === 'Commercial Unit';
    }

    public function getLeaseStatusAttribute()
    {
        // Only calculate lease status for lease agreements with end dates
        if ($this->agreement_type !== 'lease' || !$this->lease_end_date) {
            return null;
        }
    
        $now = now();
        $endDate = $this->lease_end_date;
        $startDate = $this->lease_start_date;
    
        // If lease hasn't started yet
        if ($now->lt($startDate)) {
            return 'Upcoming';
        }
    
        // If lease has expired
        if ($now->gt($endDate)) {
            return 'Expired';
        }
    
        // Calculate days until expiration
        $daysUntilExpiry = $now->diffInDays($endDate, false);
    
        // If expiring within 30 days
        if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0) {
            return 'Expiring Soon';
        }
    
        // If currently active
        if ($now->between($startDate, $endDate)) {
            return 'Active';
        }
    
        return 'Unknown';
    }
    
    /**
     * Get days until lease expiry
     *
     * @return int|null
     */
    public function getDaysUntilExpiryAttribute()
    {
        if ($this->agreement_type !== 'lease' || !$this->lease_end_date) {
            return null;
        }
    
        $now = now();
        $endDate = $this->lease_end_date;
    
        if ($now->gt($endDate)) {
            return 0; // Already expired
        }
    
        return $now->diffInDays($endDate);
    }
    
    /**
     * Check if lease is expiring within specified days
     *
     * @param int $days
     * @return bool
     */
    public function isExpiringSoon($days = 30)
    {
        if ($this->agreement_type !== 'lease' || !$this->lease_end_date) {
            return false;
        }
    
        $now = now();
        $endDate = $this->lease_end_date;
        $daysUntilExpiry = $now->diffInDays($endDate, false);
    
        return $daysUntilExpiry <= $days && $daysUntilExpiry > 0;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeCommercial($query)
    {
        return $query->where('listing_type', 'Commercial Unit');
    }

    public function scopeResidential($query)
    {
        return $query->where('listing_type', '!=', 'Commercial Unit');
    }

    // Scope for overdue payments

    // Scope for business tenants
    public function scopeBusiness($query)
    {
        return $query->where('tenant_type', 'Business');
    }

    // Scope for individual tenants
    public function scopeIndividual($query)
    {
        return $query->where('tenant_type', 'Individual');
    }

    private function getSanitizedTenantName(): string
    {
        $name = $this->tenant_type === 'Business' && $this->business_name 
            ? $this->business_name 
            : $this->tenant_name;
            
        $sanitized = strtolower(preg_replace('/[^a-zA-Z0-9\s]/', '', $name));
        $sanitized = str_replace(' ', '_', $sanitized);
        $sanitized = preg_replace('/_{2,}/', '_', $sanitized);
        
        return substr($sanitized, 0, 30);
    }

    // Boot method to automatically set created_by and updated_by
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /** The tenancy this one was transferred from. */
    public function previousTenancy(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class, 'previous_tenancy_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(TenantInvoice::class, 'tenancy_id');
    }

    public function payments()
    {
        return $this->hasMany(TenantPayment::class, 'tenant_id');
    }
    
    public function latestPayment()
    {
        return $this->hasOne(TenantPayment::class, 'tenant_id')->latest('payment_date');
    }
    
    public function getPaymentStatusAttribute()
    {
        $latestPayment = $this->latestPayment;
        
        if (!$latestPayment) {
            return 'No payments';
        }
        
        // Check if payment is for current month
        $currentMonth = now()->format('Y-m');
        $paymentMonth = $latestPayment->payment_period;
        
        if ($paymentMonth === $currentMonth) {
            return $latestPayment->payment_status === 'paid' ? 'Current' : 'Pending';
        } else {
            return 'Overdue';
        }
    }
    
    public function getTotalPaidAttribute()
    {
        return $this->payments()->where('payment_status', '!=', 'failed')->sum('total_amount');
    }
    
    public function getOutstandingBalanceAttribute()
    {
        // Calculate based on your business logic
        // This is a simple example
        $monthsActive = now()->diffInMonths($this->lease_start_date ?? $this->created_at);
        $expectedTotal = $monthsActive * $this->monthly_payment;
        
        return max(0, $expectedTotal - $this->total_paid);
    }

    public function notices()
    {
        return $this->hasMany(TenantNotice::class, 'tenant_id');
    }
    
    
    public function getActiveNoticesAttribute()
    {
        return $this->notices()
                    ->where('effective_date', '>=', now())
                    ->where('tenant_response', '!=', 'complied')
                    ->get();
    }

    public function termination()
    {
        return $this->hasOne(TenantTermination::class, 'tenant_management_id');
    }

    public function getIsTerminatedAttribute()
    {
        return $this->status === 'Terminated' && $this->termination()->exists();
    }

}