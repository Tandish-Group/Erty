<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'department_head',
        'created_by',
        'updated_by',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the business that owns the department.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }


    /**
     * Get the user who created this department.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this department.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the agents in this department.
     */
    public function agents()
    {
        return $this->hasMany(Agent::class, 'department_id');
    }

    /**
     * Get the roles in this department.
     */
    public function roles()
    {
        return $this->hasMany(AgentRole::class, 'department_id');
    }

    /**
     * Scope to get only active departments.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by business.
     */
    public function scopeForBusiness($query, $businessId)
    {
        return $query->where('business_id', $businessId);
    }
}
