<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantInvoicePaymentAllocation extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenant_id';

    use HasFactory;

    protected $table = 'tenant_invoice_payment_allocations';

    protected $fillable = [
        'invoice_id',
        'payment_id',
        'tenant_id',
        'allocated_amount',
        'invoice_balance_before',
        'invoice_balance_after',
        'allocation_type',
        'allocation_breakdown',
        'notes',
        'recorded_by',
        'allocation_date',
        'source',
        'invoice_kind',
        'agent_id',
        'override_agent_id',
        'reversed_at',
        'commission_payout_id',
        'override_payout_id',
        'clawback_payout_id',
        'override_clawback_payout_id',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
        'invoice_balance_before' => 'decimal:2',
        'invoice_balance_after' => 'decimal:2',
        'allocation_breakdown' => 'json',
        'allocation_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TenantPayment::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('allocation_type', $type);
    }
}