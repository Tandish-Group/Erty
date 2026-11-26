<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** Money received from a buyer, with its receipt number. A mistake is reversed, never edited. */
class PropertySalePayment extends Model
{
    protected $fillable = ['sale_id', 'receipt_number', 'paid_on', 'amount', 'method', 'reference', 'status', 'recorded_by', 'reversed_by', 'reversed_at', 'reversal_reason'];

    protected $casts = ['paid_on' => 'date', 'amount' => 'float', 'reversed_at' => 'datetime'];

    public function sale(): BelongsTo { return $this->belongsTo(PropertySale::class, 'sale_id'); }
    public function allocations(): HasMany { return $this->hasMany(PropertySalePaymentAllocation::class, 'payment_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }

    public function methodLabel(): string
    {
        return config('property.sale_payment_methods')[$this->method] ?? ucfirst(str_replace('_', ' ', $this->method));
    }
}
