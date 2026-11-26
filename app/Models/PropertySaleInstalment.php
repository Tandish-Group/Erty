<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** One step of a sale's payment plan; sequence 0 is the deposit. Paid oldest first. */
class PropertySaleInstalment extends Model
{
    protected $fillable = ['sale_id', 'sequence', 'number', 'label', 'due_date', 'amount', 'paid_amount', 'status', 'overdue_notified_at'];

    protected $casts = ['due_date' => 'date', 'amount' => 'float', 'paid_amount' => 'float', 'overdue_notified_at' => 'datetime'];

    public const STATUSES = ['due' => 'Due', 'part_paid' => 'Part paid', 'paid' => 'Paid', 'cancelled' => 'Cancelled'];

    public function sale(): BelongsTo { return $this->belongsTo(PropertySale::class, 'sale_id'); }
    public function allocations(): HasMany { return $this->hasMany(PropertySalePaymentAllocation::class, 'instalment_id'); }

    public function outstanding(): float
    {
        return $this->status === 'cancelled' ? 0.0 : round(max(0, $this->amount - $this->paid_amount), 2);
    }

    public function isOverdue(): bool
    {
        return $this->outstanding() > 0 && $this->due_date->lt(today());
    }
}
