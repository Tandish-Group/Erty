<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantLateFee extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenant_id';

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'payment_id',
        'recorded_by',
        'late_fee_amount',
        'days_late',
        'due_date',
        'payment_date',
        'late_fee_date',
        'calculation_method',
        'base_amount',
        'flat_fee',
        'daily_penalty',
        'grace_period_days',
        'status',
        'amount_paid',
        'amount_waived',
        'waiver_reason',
        'waived_by',
        'waived_at',
        'notes',
        'calculation_details',
    ];

    protected $casts = [
        'due_date' => 'date',
        'payment_date' => 'date',
        'late_fee_date' => 'date',
        'waived_at' => 'datetime',
        'late_fee_amount' => 'decimal:2',
        'base_amount' => 'decimal:2',
        'flat_fee' => 'decimal:2',
        'daily_penalty' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'amount_waived' => 'decimal:2',
        'calculation_details' => 'json',
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class, 'tenant_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class, 'invoice_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(TenantPayment::class, 'payment_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getBalanceAttribute(): float
    {
        return $this->late_fee_amount - $this->amount_paid - $this->amount_waived;
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'pending' => 'Outstanding',
            'paid' => 'Paid',
            'partially_paid' => 'Partially Paid',
            'waived' => 'Waived',
            default => ucfirst($this->status)
        };
    }

    public function scopeOutstanding($query)
    {
        return $query->where('status', 'pending')->orWhere('status', 'partially_paid');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeWaived($query)
    {
        return $query->where('status', 'waived');
    }

    public function waive($reason, $amount = null)
    {
        $waiverAmount = $amount ?? $this->balance;
        
        $this->update([
            'status' => 'waived',
            'amount_waived' => $this->amount_waived + $waiverAmount,
            'waiver_reason' => $reason,
            'waived_by' => auth()->id(),
            'waived_at' => now()
        ]);
    }
}