<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** How much of a buyer payment went to which instalment. */
class PropertySalePaymentAllocation extends Model
{
    protected $fillable = ['payment_id', 'instalment_id', 'amount'];

    protected $casts = ['amount' => 'float'];

    public function payment(): BelongsTo { return $this->belongsTo(PropertySalePayment::class, 'payment_id'); }
    public function instalment(): BelongsTo { return $this->belongsTo(PropertySaleInstalment::class, 'instalment_id'); }
}
