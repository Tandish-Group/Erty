<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'color',
        'is_active',
        'user_id',
        'permissions',
        'property_scope',
        'department',
    ];

    public const SCOPE_ALL = 'all';
    public const SCOPE_ASSIGNED = 'assigned';

    /** Roles for agents (leasing and sales) work only the properties and units assigned to each person. */
    public function isAgentRole(): bool
    {
        return \App\Services\Access\StarterRoles::isAgentRoleName($this->name, $this->department)
            || ($this->exists && \App\Models\Agent::whereIn('user_id', \App\Models\User::where('business_role_id', $this->id)->select('id'))->whereNotNull('user_id')->exists());
    }

    public function limitedToAssignedProperties(): bool
    {
        return $this->property_scope === self::SCOPE_ASSIGNED;
    }

    protected $casts = [
        'is_active' => 'boolean',
        'permissions' => 'array',
    ];

    /**
     * Get the user who created this role
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the users assigned to this role (different from creator)
     */
    public function assignedUsers()
    {
        return $this->hasMany(User::class, 'business_role_id');
    }

    /**
     * Scope for active roles
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for business
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
