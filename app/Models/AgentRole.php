<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'level',
        'department_id',
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
     * Get the business that owns the role.
     */
    public function business()
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the department this role belongs to.
     */
    public function department()
    {
        return $this->belongsTo(AgentDepartment::class, 'department_id');
    }

    /**
     * Get the user who created this role.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated this role.
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the agents with this role.
     */
    public function agents()
    {
        return $this->hasMany(Agent::class, 'role_id');
    }

    /**
     * Scope to get only active roles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

}
