<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessPaymentMethod extends Model
{
    protected $fillable = [
        'user_id',
        'method_type',
        'label',
        'details',
        'is_primary',
        'is_active',
    ];

    protected $casts = [
        'details'    => 'array',
        'is_primary' => 'boolean',
        'is_active'  => 'boolean',
    ];

    /**
     * Human-readable method type labels.
     */
    public static function typeLabels(): array
    {
        return [
            'mpesa_paybill'   => 'M-Pesa Paybill',
            'mpesa_till'      => 'M-Pesa Till',
            'mpesa_send_money'=> 'M-Pesa Send Money',
            'bank_transfer'   => 'Bank Transfer',
            'cash'            => 'Cash',
        ];
    }

    /**
     * Get the type label for display.
     */
    public function getTypeLabelAttribute(): string
    {
        return static::typeLabels()[$this->method_type] ?? ucfirst($this->method_type);
    }

    /**
     * Relationship to user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
