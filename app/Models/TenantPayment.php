<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\TenantInvoicePaymentAllocation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class TenantPayment extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Records of tenancies at the member's assigned properties. */
    protected string $tenancyColumn = 'tenant_id';

    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'entry_type',
        'unallocated_amount',
        'confirmed_at',
        'confirmed_by',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
        'invoice_id',
        'recorded_by',
        'payment_date',
        'payment_period',
        'amount_paid',
        'total_amount',
        'payment_method',
        'transaction_reference',
        'payment_status',
        'payment_notes',
        'receipt_path',
        'is_verified',
        'late_fee_paid_now',
        'verified_at',
        'verified_by',
        'allocation_data', // Add this new field
        'is_combined_payment', // Add this new field
        'combined_payment_reference', // Add this new fiel
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount_paid' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_verified' => 'boolean',
        'unallocated_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'reversed_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_combined_payment' => 'boolean', // Add this new cast
        'allocation_data' => 'json', // Add this new cast
    ];

    // Relationships
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantInvoice::class, 'invoice_id');
    }

    public function lateFees(): HasMany
    {
        return $this->hasMany(TenantLateFee::class, 'payment_id');
    }

    public function invoiceAllocations(): HasMany
    {
        return $this->hasMany(TenantInvoicePaymentAllocation::class, 'payment_id');
    }

    
    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'KES ' . number_format($this->amount_paid, 2);
    }

    public function getTotalAllocatedAttribute(): float
    {
        return $this->invoiceAllocations()->whereNull('reversed_at')->sum('allocated_amount');
    }

    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const REVERSED = 'reversed';

    public const ENTRY_LABELS = [
        'payment' => 'Payment',
        'credit' => 'Credit',
        'deposit_release' => 'Deposit applied',
        'refund' => 'Refund',
        'opening' => 'Brought forward',
    ];

    public function getPaymentMethodLabelAttribute(): string
    {
        return match($this->payment_method) {
            'cash' => 'cash',
            'bank_transfer' => 'bank transfer',
            'mobile_money' => 'mobile money',
            'check' => 'cheque',
            'card' => 'card',
            'other' => 'other',
            default => str_replace('_', ' ', (string) $this->payment_method)
        };
    }

    public function getFormattedPaymentPeriodAttribute(): string
    {
        return Carbon::createFromFormat('Y-m', $this->payment_period)->format('F Y');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('payment_date', '>=', now()->subDays($days));
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year);
    }

    // Boot method for model events
    protected static function boot()
    {
        parent::boot();

        // Automatically calculate total_amount before saving
        static::saving(function ($payment) {
            // Only auto-calculate if total_amount is not already set or is 0
            if (!$payment->total_amount || $payment->total_amount == 0) {
                $payment->total_amount = $payment->amount_paid;
            }
        });
    }
}