<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantProfile extends Model
{
    use \App\Models\Concerns\BelongsToBusiness;

    protected string $businessOwnerColumn = 'managed_by';

    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Tenants with a tenancy at an assigned property, plus profiles this member created (before a tenancy exists). */
    public function restrictToProperties(\Illuminate\Database\Eloquent\Builder $query, array $complexIds, ?int $agentId, int $userId): void
    {
        $query->where(fn ($q) => $q
            ->whereIn($this->qualifyColumn('id'), static::tenanciesIn($complexIds, 'tenant_profile_id'))
            ->orWhere($this->qualifyColumn('created_by'), $userId));
    }

    use HasFactory;

    protected $fillable = [
        // Basic Information
        'tenant_ref',
        'tenant_type',
        'lead_id',
        
        // Individual Information
        'tenant_name',
        'tenant_email',
        'tenant_phone',
        'tenant_id_number',
        'date_of_birth',
        'gender',
        'address',
        'nationality',
        'occupation',
        'employer',
        'monthly_income',
        
        // Business Information
        'business_name',
        'business_registration',
        'business_contact_person',
        'business_email',
        'business_phone',
        'business_address',
        'business_type',
        'tax_id_number',
        'annual_revenue',
        'years_in_business',
        
        // Emergency Contact
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'emergency_contact_address',
        
        // Additional Information
        'identification_documents',
        'references',
        'additional_notes',
        
        // Status
        'profile_status',
        'blacklist_reason',
        'blacklisted_date',
        
        // Management
        'managed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'blacklisted_date' => 'date',
        'monthly_income' => 'decimal:2',
        'annual_revenue' => 'decimal:2',
        'identification_documents' => 'array',
        'references' => 'array',
    ];

    protected $attributes = [
        'profile_status' => 'Active',
    ];

    // Relationships
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

    public function tenancyAgreements(): HasMany
    {
        return $this->hasMany(TenantManagement::class, 'tenant_profile_id');
    }

    public function activeTenancies(): HasMany
    {
        return $this->hasMany(TenantManagement::class, 'tenant_profile_id')
                    ->where('status', 'Active');
    }
    
    public function latestTenancy()
    {
        return $this->hasOne(TenantManagement::class, 'tenant_profile_id')->latest();
    }

    // Accessors
    public function getDisplayNameAttribute(): string
    {
        return $this->tenant_type === 'Business' && $this->business_name 
            ? $this->business_name . ($this->business_contact_person ? ' (' . $this->business_contact_person . ')' : '')
            : $this->tenant_name;
    }

    public function getPrimaryEmailAttribute(): string
    {
        return $this->tenant_type === 'Business' && $this->business_email 
            ? $this->business_email 
            : $this->tenant_email;
    }

    public function getPrimaryPhoneAttribute(): string
    {
        return $this->tenant_type === 'Business' && $this->business_phone 
            ? $this->business_phone 
            : $this->tenant_phone;
    }

    public function getIsBlacklistedAttribute(): bool
    {
        return $this->profile_status === 'Blacklisted';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('profile_status', 'Active');
    }

    public function scopeBusiness($query)
    {
        return $query->where('tenant_type', 'Business');
    }

    public function scopeIndividual($query)
    {
        return $query->where('tenant_type', 'Individual');
    }

    public function scopeBlacklisted($query)
    {
        return $query->where('profile_status', 'Blacklisted');
    }

    // Boot method
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

}