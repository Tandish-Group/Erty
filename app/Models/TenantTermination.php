<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TenantTermination extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenant_management_id';

    use HasFactory;

    protected $fillable = [
        'tenant_management_id',
        'termination_date',
        'termination_reason',
        'other_reason',
        'notice_compliance',
        'security_deposit_status',
        'security_deposit_refund',
        'outstanding_rent',
        'damage_charges',
        'net_settlement',
        'property_condition',
        'keys_returned',
        'access_cards_returned',
        'forwarding_address',
        'termination_notes',
        'terminated_by',
    ];

    protected $casts = [
        'termination_date' => 'date',
        'security_deposit_refund' => 'decimal:2',
        'outstanding_rent' => 'decimal:2',
        'damage_charges' => 'decimal:2',
        'net_settlement' => 'decimal:2',
        'keys_returned' => 'boolean',
        'access_cards_returned' => 'boolean',
    ];

    // Relationships
    public function tenantManagement()
    {
        return $this->belongsTo(TenantManagement::class, 'tenant_management_id');
    }

    public function terminatedBy()
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }

    // Accessors
    public function getTerminationReasonLabelAttribute()
    {
        return ucwords(str_replace('_', ' ', $this->termination_reason));
    }

    public function getFormattedNetSettlementAttribute()
    {
        if ($this->net_settlement > 0) {
            return '+KES ' . number_format($this->net_settlement, 2);
        } elseif ($this->net_settlement < 0) {
            return '-KES ' . number_format(abs($this->net_settlement), 2);
        }
        return 'KES 0.00';
    }

    public function getSettlementTypeAttribute()
    {
        if ($this->net_settlement > 0) {
            return 'owed_to_tenant';
        } elseif ($this->net_settlement < 0) {
            return 'owed_by_tenant';
        }
        return 'settled';
    }
}