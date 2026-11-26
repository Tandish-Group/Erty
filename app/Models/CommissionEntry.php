<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line of the commission ledger (see the commission_entries migration). */
class CommissionEntry extends Model
{
    public const PENDING = 'pending';
    public const EARNED = 'earned';
    public const APPROVED = 'approved';
    public const PAID = 'paid';
    public const REVERSED = 'reversed';

    protected $guarded = ['id'];

    protected $casts = ['breakdown' => 'array', 'collected_on' => 'date', 'period_from' => 'date', 'period_to' => 'date',
        'approved_at' => 'datetime', 'amount' => 'float', 'basis_amount' => 'float', 'rate' => 'float'];

    public function agent() { return $this->belongsTo(Agent::class); }
    public function complex() { return $this->belongsTo(Complex::class); }
    public function agreement() { return $this->belongsTo(CommissionAgreement::class); }
    public function tenancy() { return $this->belongsTo(TenantManagement::class, 'tenancy_id'); }
    public function reverses() { return $this->belongsTo(self::class, 'reverses_entry_id'); }
    public function payout() { return $this->belongsTo(AgentCommissionPayout::class, 'payout_id'); }

    /** Counts towards what is owed (earned or approved, not yet paid). */
    public function scopePayable($query)
    {
        return $query->whereIn('status', [self::EARNED, self::APPROVED])->whereNull('payout_id');
    }
}
