<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommercialUnit extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;
    /** On properties a member reaches only through units, only these units' rows (PropertyAccess::unitScope). */
    protected array $unitColumns = ['commercial' => 'id'];

    use HasFactory;

    protected $fillable = [
        'complex_id',
        'unit_number',
        'unit_sq_ft',
        'max_subdivisions',
        'min_sq_ft',
        'floors',
        'custom_floors',
        'monthly_rent',
        'status',
        'description',
        'assigned_agent_id',
    ];

    protected $casts = [
        'unit_sq_ft' => 'decimal:2',
        'min_sq_ft' => 'decimal:2',
        'monthly_rent' => 'decimal:2',
        'max_subdivisions' => 'integer',
    ];

    /**
     * Get the complex that owns the commercial unit.
     */
    /** Agent working this unit (overrides the property's agent for tenancies here). */
    public function assignedAgent()
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    /**
    * Get the tenant management records associated with this commercial unit.
    */
    public function tenantManagements()
    {        
        return $this->hasOne(TenantManagement::class, 'commercial_unit_id');
    }

    /**
     * Get the effective floor (either selected floor or custom floor).
     */
    public function getEffectiveFloorAttribute()
    {
        return $this->custom_floors ?: $this->floors;
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Get formatted monthly rent.
     */
    public function getFormattedMonthlyRentAttribute()
    {
        return $this->monthly_rent ? 'KES ' . number_format($this->monthly_rent, 2) : 'Not specified';
    }

    
    public function activeTenants()
    {
        return $this->hasMany(TenantManagement::class, 'commercial_unit_id', 'id')
                    ->where('status', 'Active');
    }
    
    public function allTenants()
    {
        return $this->hasMany(TenantManagement::class, 'commercial_unit_id', 'id');
    }
}
