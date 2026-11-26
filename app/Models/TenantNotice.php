<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantNotice extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenant_id';

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'issued_by',
        'notice_type',
        'notice_date',
        'effective_date',
        'notice_period',
        'notice_subject',
        'notice_details',
        'delivery_methods',
        'delivery_status',
        'sent_at',
        'delivered_at',
        'tenant_response',
        'response_date',
        'response_notes',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'effective_date' => 'date',
        'delivery_methods' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'response_date' => 'datetime',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    // Accessors
    public function getNoticeTypeLabelAttribute(): string
    {
        return match($this->notice_type) {
            'lease_termination' => 'Lease Termination',
            'rent_increase' => 'Rent Increase',
            'lease_violation' => 'Lease Violation',
            'property_inspection' => 'Property Inspection',
            'maintenance_notice' => 'Maintenance Notice',
            'eviction_notice' => 'Eviction Notice',
            'lease_renewal' => 'Lease Renewal',
            'other' => 'Other',
            default => ucfirst(str_replace('_', ' ', $this->notice_type))
        };
    }

    public function getDeliveryMethodsListAttribute(): string
    {
        $methods = collect($this->delivery_methods)->map(function($method) {
            return match($method) {
                'email' => 'Email',
                'sms' => 'SMS',
                'physical' => 'Physical Copy',
                default => ucfirst($method)
            };
        });
        
        return $methods->implode(', ');
    }
}