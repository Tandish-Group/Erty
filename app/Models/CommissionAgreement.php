<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Who earns commission, how much, and at which level. Versioned: CommissionAgreementService::change()
 * closes a version (effective_to) and starts a new one, so entries keep pointing at the terms they used.
 */
class CommissionAgreement extends Model
{
    public const SCOPES = ['company', 'property', 'agent', 'agent_property', 'unit'];
    public const ENTITLEMENTS = ['management_fee', 'agent', 'primary_on_delegated', 'sale'];
    public const METHODS = ['percent_collected', 'percent_net', 'flat_per_tenant', 'tiered', 'percent_of_sale', 'flat_per_sale'];
    /** Commission on selling a house: earned as the buyer pays, paid by the business (no management fee to draw on). */
    public const SALE_METHODS = ['percent_of_sale', 'flat_per_sale'];
    public const PERIOD_METHODS = ['percent_net', 'flat_per_tenant', 'tiered'];
    public const FUNDING = ['company_fee', 'additional_cost'];

    protected $fillable = ['business_id', 'name', 'scope', 'complex_id', 'agent_id', 'unit_type', 'unit_id', 'entitlement', 'method', 'rate',
        'flat_amount', 'tiers', 'sharing', 'sub_share_pct', 'funding', 'applies_to', 'effective_from', 'effective_to', 'replaces_id',
        'change_reason', 'created_by', 'ended_by'];

    protected $casts = ['tiers' => 'array', 'applies_to' => 'array', 'effective_from' => 'date', 'effective_to' => 'date',
        'rate' => 'float', 'flat_amount' => 'float', 'sub_share_pct' => 'float'];

    public function agent() { return $this->belongsTo(Agent::class); }
    public function complex() { return $this->belongsTo(Complex::class); }
    public function replaces() { return $this->belongsTo(self::class, 'replaces_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    public function isPeriodBased(): bool
    {
        return in_array($this->method, self::PERIOD_METHODS, true);
    }

    public function appliesTo(string $invoiceKind): bool
    {
        return in_array($invoiceKind, $this->applies_to ?: ['rent'], true);
    }

    /** In force on a date. */
    public function scopeActiveOn($query, $date)
    {
        $d = \Carbon\Carbon::parse($date)->toDateString();
        return $query->whereDate('effective_from', '<=', $d)->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $d));
    }

    public function describe(): string
    {
        return match ($this->method) {
            'percent_collected' => rtrim(rtrim(number_format($this->rate, 3), '0'), '.') . '% of rent collected',
            'percent_net' => rtrim(rtrim(number_format($this->rate, 3), '0'), '.') . '% of rent collected less property expenses',
            'flat_per_tenant' => number_format((float) $this->flat_amount, 2) . ' per paying tenant per period',
            'tiered' => 'Tiered by rent collected in the period',
            'percent_of_sale' => rtrim(rtrim(number_format($this->rate, 3), '0'), '.') . '% of the sale price, earned as the buyer pays',
            'flat_per_sale' => number_format((float) $this->flat_amount, 2) . ' per house sold, earned as the buyer pays',
            default => $this->method,
        };
    }
}
