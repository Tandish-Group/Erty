<?php

namespace App\Models;

use App\Models\AgentCommissionTier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Agent Type
        'agent_type',
        
        // Personal Information
        'first_name',
        'last_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'national_id',
        'emergency_contact',
        
        // Agency-specific fields
        'company_name',
        'agency_license',
        'contact_person_name',
        'contact_person_email',
        'contact_person_phone',
        'company_address',
        'tax_id',
        'business_registration_number',
        
        // Employment Details
        'department_id',
        'role_id',
        'employment_type',
        'start_date',
        'reporting_manager_id',
        'work_location_id',
        'probation_period',
        'contract_end_date',
        'contract_start_date',
        'contract_type',
        'specializations',
        'job_description',
        
        // Compensation Details
        'base_salary',
        'commission_rate',
        'commission_type',
        'bonus_rate',
        'hourly_rate',
        'project_fee',
        'payment_terms',
        'payment_frequency',
        'benefits',
        'sales_target',
        'bank_account',
        'compensation_notes',
        
        // Additional Information
        'profile_photo',
        'resume',
        'certifications',
        'years_experience',
        'previous_employer',
        'linkedin_profile',
        'permissions',
        'references',
        'notes',
        
        // Status & Tracking
        'status',
        'created_by',
        'updated_by',
        'is_active',
        'user_id',
        'reports_to_agent_id',
        'override_rate',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'start_date' => 'date',
        'contract_end_date' => 'date',
        'contract_start_date' => 'date',
        'base_salary' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'bonus_rate' => 'decimal:2',
        'sales_target' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'project_fee' => 'decimal:2',
        'specializations' => 'array',
        'benefits' => 'array',
        'permissions' => 'array',
        'is_active' => 'boolean',
        'override_rate' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * URL-safe encrypted identifier for profile links.
     * This keeps sequential database IDs out of URLs; profile access must still
     * be authorized against the current user's agents in the controller.
     */
    public function getProfileRouteTokenAttribute(): string
    {
        return rtrim(strtr(encrypt((string) $this->getKey()), '+/', '-_'), '=');
    }

    /** The signed-in business's agent behind a profile link token; 404 when the token is bad or not theirs. */
    public static function fromProfileToken(string $token): self
    {
        try {
            $id = self::idFromProfileRouteToken($token);
        } catch (\Throwable $e) {
            abort(404);
        }
        return self::where('created_by', business_owner_id())->findOrFail($id);
    }

    public static function idFromProfileRouteToken(string $token): int
    {
        $encoded = strtr($token, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $id = decrypt($encoded);

        if (!ctype_digit((string) $id)) {
            throw new \InvalidArgumentException('Invalid agent profile token.');
        }

        return (int) $id;
    }

    protected $appends = ['full_name'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            // Agents belong to the business (owner id), also when a team member adds them.
            if (auth()->check() && !$model->created_by) {
                $model->created_by = business_owner_id();
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    // Relationships
    /** The sign-in (owner or team member) this agent profile belongs to; null for external agents. */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** The lead agent this agent works under. */
    public function reportsTo()
    {
        return $this->belongsTo(Agent::class, 'reports_to_agent_id');
    }

    /** Agents who report to this (lead) agent. */
    public function subAgents()
    {
        return $this->hasMany(Agent::class, 'reports_to_agent_id');
    }

    public function department()
    {
        return $this->belongsTo(AgentDepartment::class, 'department_id');
    }

    public function commissionTiers()
    {
        return $this->hasMany(AgentCommissionTier::class)->orderBy('min_value');
    }

    public function complexes()
    {
        return $this->hasMany(Complex::class, 'assigned_agent_id');
    }

    public function role()
    {
        return $this->belongsTo(AgentRole::class, 'role_id');
    }

    public function workLocation()
    {
        return $this->belongsTo(AgentWorkLocation::class, 'work_location_id');
    }

    public function reportingManager()
    {
        return $this->belongsTo(User::class, 'reporting_manager_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getDisplayNameAttribute()
    {
        if ($this->agent_type === 'agency') {
            return $this->company_name ?? $this->full_name;
        }
        return $this->full_name;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('agent_type', $type);
    }

    public function scopeAgencies($query)
    {
        return $query->where('agent_type', 'agency');
    }

    public function scopeEmployees($query)
    {
        return $query->where('agent_type', 'employee');
    }

    public function scopeContractors($query)
    {
        return $query->where('agent_type', 'contractor');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereLike('first_name', "%{$search}%")
                ->orWhereLike('last_name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereLike('phone', "%{$search}%");
        });
    }
}
