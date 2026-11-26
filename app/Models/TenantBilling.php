<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantBilling extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenancy_id';

    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'tenancy_id',
        'tenancy_ref',
        'currency',
        'billing_date',
        'billing_type',
        'tenancy_billing_id',
        'total_amount',
        'status',
        'system_notes',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'billing_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relationship to tenant management
     */
    public function tenant()
    {
        return $this->belongsTo(TenantManagement::class, 'tenancy_id');
    }

    /**
     * Relationship to tenancy billing
     */
    public function tenancyBilling()
    {
        return $this->belongsTo(TenantTenancyBilling::class, 'tenancy_billing_id');
    }

    /**
     * Relationship to invoices
     */
    public function invoices()
    {
        return $this->hasMany(TenantInvoice::class, 'billing_id');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }

    public function scopeOverdue($query)
    {
        return $query->whereIn('status', ['System_Overdue_Recent', 'System_Overdue_Extended']);
    }

    public function scopeHistorical($query)
    {
        return $query->where('status', 'like', 'System_%');
    }

    /**
     * Check if billing is overdue
     */
    public function getIsOverdueAttribute()
    {
        return in_array($this->status, ['System_Overdue_Recent', 'System_Overdue_Extended']);
    }

}