<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantInvoicePaymentAllocation;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TenantInvoice extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenancy_id';

    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'tenancy_id',
        'tenant_id',
        'billing_id',
        'invoice_type',
        'kind',
        'related_invoice_id',
        'voided_at',
        'voided_by',
        'void_reason',
        'currency',
        'amount',
        'due_date',
        'billing_period_start',
        'billing_period_end',
        'status',
        'line_items',
        'paid_amount',
        'combined_invoice_number',
        'paid_at',
        'current_billing_amount',
        'pending_payments_amount',
        'pending_payments_data',
    ];

    protected $casts = [
        'line_items' => 'array',
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'billing_period_start' => 'date',
        'billing_period_end' => 'date',
        'paid_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function tenancy()
    {
        return $this->belongsTo(TenantManagement::class, 'tenancy_id');
    }

    public function tenant()
    {
        return $this->belongsTo(TenantProfile::class, 'tenant_id');
    }

    public function billing()
    {
        return $this->belongsTo(TenantBilling::class, 'billing_id');
    }

    public function payments()
    {
        return $this->hasMany(TenantPayment::class, 'invoice_id');
    }

    public function lateFees(): HasMany
    {
        return $this->hasMany(TenantLateFee::class, 'invoice_id');
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(TenantInvoicePaymentAllocation::class, 'invoice_id');
    }

    public function getTotalAllocatedAttribute(): float
    {
        return $this->paymentAllocations()->sum('allocated_amount');
    }

    public function isVoid(): bool
    {
        return $this->status === self::VOID;
    }

    /** What is still owed on this invoice. */
    public function getBalanceAttribute(): float
    {
        return $this->isVoid() ? 0.0 : round(max(0, (float) $this->amount - (float) $this->paid_amount), 2);
    }

    public function isOverdue(): bool
    {
        return !$this->isVoid() && $this->status !== self::PAID && $this->due_date && $this->due_date->lt(now()->startOfDay());
    }

    /** Status shown to people: Overdue is worked out from the due date, not stored. */
    public function getDisplayStatusAttribute(): string
    {
        return match (true) {
            $this->isVoid() => $this->kind === 'late_fee' ? 'Waived' : 'Void',
            $this->status === self::PAID => 'Paid',
            $this->isOverdue() => $this->status === self::PARTIAL ? 'Overdue (part paid)' : 'Overdue',
            $this->status === self::PARTIAL => 'Partly paid',
            default => 'Unpaid',
        };
    }

    public function getKindLabelAttribute(): string
    {
        return self::KIND_LABELS[$this->kind] ?? ucfirst((string) $this->kind);
    }

    public const UNPAID = 'Unpaid';
    public const PARTIAL = 'Partially Paid';
    public const PAID = 'Paid';
    public const VOID = 'Cancelled';

    public const KIND_LABELS = [
        'rent' => 'Rent',
        'service_charge' => 'Service charge',
        'deposit' => 'Deposit',
        'late_fee' => 'Late fee',
        'adjustment' => 'Adjustment',
    ];
}
