<?php

namespace App\Models;

use Carbon\Carbon;
use App\Events\LeadPhaseAdvanced;
use App\Enums\LeadWorkflowPhaseEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lead extends Model
{
    use \App\Models\Concerns\BelongsToBusiness;

    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Leads for an assigned property, plus leads assigned to the member's own agent profile. */
    public function restrictToProperties(\Illuminate\Database\Eloquent\Builder $query, array $complexIds, ?int $agentId, int $userId): void
    {
        // Leads at properties they work in full; on properties where they only hold units, just their own leads.
        $full = array_values(array_diff($complexIds, array_keys(app(\App\Services\Access\PropertyAccess::class)->unitScope())));
        $query->where(function ($q) use ($full, $agentId) {
            $q->whereIn($this->qualifyColumn('complex_id'), $full ?: [0]);
            if ($agentId) $q->orWhere($this->qualifyColumn('assigned_agent_id'), $agentId);
        });
    }

    use HasFactory, SoftDeletes;

    protected $fillable = [
        // User who created this lead
        'user_id',
        'lead_profile_id',

        // Lead Classification
        'status',
        'priority',
        'source',
        'referral_details',
        
        // Property Requirements
        'property_type',
        'purpose',
        'budget_min',
        'budget_max',
        'bedrooms',
        'bathrooms',
        'location_preference',
        'timeline',
        'special_requirements',
        'min_sq_area',
        'max_sq_area',
        'business_use',
        'complex_id',
        
        // Communication Preferences
        'contact_method',
        'contact_time',
        
        // Assignment and Follow-up
        'assigned_agent_id',
        'follow_up_date',
        'notes',
        
        // Lead Scoring
        'lead_score',
        'interactions',
        'last_contacted_at',
        'contact_attempts',
        
        // Workflow
        'workflow_phase',
        'phase_started_at',
        'converted_tenant_id',
        'converted_sale_id',
        'converted_by',
        'created_by',
        'converted_at',
        'details_confirmed_at',
        'details_confirmed_by',
        'qualification_confirmed_at',
        'qualification_confirmed_by',
    ];

    protected $casts = [
        'follow_up_date' => 'datetime',
        'last_contacted_at' => 'datetime',
        'phase_started_at' => 'datetime',
        'converted_at' => 'datetime',
        'details_confirmed_at' => 'datetime',
        'qualification_confirmed_at' => 'datetime',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'min_sq_area' => 'decimal:2',
        'max_sq_area' => 'decimal:2',
        'interactions' => 'array',
        'contact_attempts' => 'integer',
        'lead_score' => 'integer',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'follow_up_date',
        'last_contacted_at',
        'phase_started_at',
        'converted_at',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profile()
    {
        return $this->belongsTo(LeadProfile::class, 'lead_profile_id');
    }

    public function assignedAgent()
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function complex()
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }
    
    public function tasks()
    {
        return $this->hasMany(LeadTask::class);
    }
    
    public function currentPhaseTasks()
    {
        return $this->hasMany(LeadTask::class)->where('phase', $this->workflow_phase);
    }
    
    public function convertedTenant()
    {
        return $this->belongsTo(TenantManagement::class, 'converted_tenant_id');
    }

    /** The house sale this lead became (a buyer). */
    public function convertedSale()
    {
        return $this->belongsTo(PropertySale::class, 'converted_sale_id');
    }

    /** Converted: became a tenant, or bought a house. */
    public function scopeConverted($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('converted_tenant_id')->orWhereNotNull('converted_sale_id'));
    }

    public function isBuyer(): bool
    {
        return $this->purpose === 'buy';
    }
    
    /** The person (owner or team member) who captured the lead. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** A lead can become a tenant once its details and qualification are confirmed. */
    public function readyToConvert(): bool
    {
        return !$this->converted_tenant_id && !$this->converted_sale_id && !$this->missingForConversion();
    }

    /** What is still needed before conversion, in plain words. */
    public function missingForConversion(): array
    {
        $missing = [];
        if (!$this->details_confirmed_at) $missing[] = 'confirm the lead details';
        if (!$this->qualification_confirmed_at) $missing[] = 'confirm the qualification';
        return $missing;
    }

    public function detailsConfirmedBy()
    {
        return $this->belongsTo(User::class, 'details_confirmed_by');
    }

    public function qualificationConfirmedBy()
    {
        return $this->belongsTo(User::class, 'qualification_confirmed_by');
    }
    
    // Workflow methods
    public function canAdvanceToNextPhase(): bool
    {
        $blockingTasks = $this->tasks()
            ->where('phase', $this->workflow_phase)
            ->where('blocks_next_phase', true)
            ->whereNotIn('status', ['completed', 'skipped'])
            ->count();
        
        return $blockingTasks === 0;
    }
    
    
    public function getPhaseProgress(): array
    {
        $phaseTasks = $this->tasks()->where('phase', $this->workflow_phase)->get();
        $total = $phaseTasks->count();
        $completed = $phaseTasks->whereIn('status', ['completed', 'skipped'])->count();
    
        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $total > 0 ? round(($completed / $total) * 100) : 0
        ];
    }

    // Accessors - delegate to profile
    public function getFullNameAttribute()
    {
        return $this->profile?->full_name ?? 'Unknown';
    }

    public function getPrimaryEmailAttribute()
    {
        return $this->profile?->primary_email ?? null;
    }

    public function getPrimaryPhoneAttribute()
    {
        return $this->profile?->primary_phone ?? null;
    }

    public function getLeadTypeAttribute()
    {
        return $this->profile?->lead_type ?? 'Individual';
    }

    public function getBudgetRangeAttribute()
    {
        if ($this->budget_min && $this->budget_max) {
            return number_format($this->budget_min) . ' - ' . number_format($this->budget_max);
        } elseif ($this->budget_min) {
            return 'From ' . number_format($this->budget_min);
        } elseif ($this->budget_max) {
            return 'Up to ' . number_format($this->budget_max);
        }
        return 'Not specified';
    }

    public function getStatusBadgeClassAttribute()
    {
        return match($this->status) {
            'new' => 'badge-light-info',
            'contacted' => 'badge-light-warning',
            'qualified' => 'badge-light-success',
            'proposal' => 'badge-light-primary',
            'closed_won' => 'badge-success',
            'closed_lost' => 'badge-light-danger',
            default => 'badge-light-secondary'
        };
    }

    public function getPriorityBadgeClassAttribute()
    {
        return match($this->priority) {
            'hot' => 'badge-danger',
            'warm' => 'badge-warning',
            'cold' => 'badge-info',
            default => 'badge-secondary'
        };
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['closed_won', 'closed_lost']);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecentlyCreated($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeByType($query, $type)
    {
        return $query->whereHas('profile', function($q) use ($type) {
            $q->where('lead_type', $type);
        });
    }

    // Methods
    public function calculateLeadScore()
    {
        $score = 0;
        
        $sourceScores = [
            'website' => 20,
            'referral' => 40,
            'agent_referral' => 35,
            'social_media' => 15,
            'google_ads' => 25,
            'facebook_ads' => 20,
            'walk_in' => 30,
            'phone_call' => 35,
            'email' => 25,
            'event' => 30
        ];
        
        $score += $sourceScores[$this->source] ?? 10;
        
        if ($this->budget_min && $this->budget_max) {
            $avgBudget = ($this->budget_min + $this->budget_max) / 2;
            if ($avgBudget > 500000) $score += 30;
            elseif ($avgBudget > 200000) $score += 20;
            elseif ($avgBudget > 100000) $score += 15;
            else $score += 10;
        }
        
        $timelineScores = [
            'immediately' => 40,
            'within_1_month' => 35,
            '1_3_months' => 25,
            '3_6_months' => 15,
            '6_months_plus' => 5,
            'flexible' => 10
        ];
        
        $score += $timelineScores[$this->timeline] ?? 10;
        
        if ($this->property_type) $score += 10;
        if ($this->location_preference) $score += 10;
        if ($this->bedrooms) $score += 5;
        
        if ($this->profile && $this->profile->lead_type === 'Business') $score += 15;
        
        $this->update(['lead_score' => min($score, 100)]);
        
        return $score;
    }

    public function addInteraction($type, $details = null)
    {
        $interactions = $this->interactions ?? [];
        $interactions[] = [
            'type' => $type,
            'details' => $details,
            'timestamp' => now()->toISOString(),
            'user_id' => auth()->id()
        ];
        
        $this->update([
            'interactions' => $interactions,
            'last_contacted_at' => now(),
            'contact_attempts' => $this->contact_attempts + 1
        ]);
    }

    public function assignToAgent($agentId)
    {
        $this->update([
            'assigned_agent_id' => $agentId,
            'status' => $this->status === 'new' && $this->details_confirmed_at ? 'contacted' : $this->status
        ]);
        
        $this->addInteraction('assigned', "Assigned to agent ID: {$agentId}");
    }

    public function updateStatus($newStatus, $notes = null)
    {
        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);
        
        $this->addInteraction('status_change', [
            'from' => $oldStatus,
            'to' => $newStatus,
            'notes' => $notes
        ]);
    }
}
