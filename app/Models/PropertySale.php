<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * The sale of a house: buyer, price, agreement date and payment plan (deposit + instalments).
 * reserved (deposit not yet covered) → agreed → completed, or cancelled. The house is "under offer"
 * while the sale is live and "sold" once it completes.
 */
class PropertySale extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    public const STATUSES = ['reserved' => 'Reserved', 'agreed' => 'Agreed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    public const LIVE = ['reserved', 'agreed'];

    protected $fillable = [
        'business_id', 'complex_id', 'residential_unit_id', 'listing_id', 'lead_id', 'agent_id', 'reference',
        'buyer_name', 'buyer_phone', 'buyer_email', 'buyer_id_number', 'buyer_kra_pin',
        'price', 'currency', 'agreement_date', 'deposit_amount', 'status', 'completed_at', 'completion_note',
        'title_transferred_at', 'cancelled_at', 'cancel_reason', 'notes', 'created_by',
    ];

    protected $casts = [
        'price' => 'float', 'deposit_amount' => 'float', 'agreement_date' => 'date', 'completed_at' => 'datetime',
        'title_transferred_at' => 'date', 'cancelled_at' => 'datetime',
    ];

    /** Members see sales at their properties (only their houses on unit-only properties), and sales they are the agent on. */
    public function restrictToProperties(Builder $query, array $complexIds, ?int $agentId, int $userId): void
    {
        $query->where(function ($q) use ($complexIds, $agentId) {
            $q->where(fn ($scoped) => static::constrainUnits($scoped, $this->qualifyColumn('complex_id'), $complexIds,
                ['residential' => $this->qualifyColumn('residential_unit_id')]));
            if ($agentId) {
                $q->orWhere($this->qualifyColumn('agent_id'), $agentId);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function complex(): BelongsTo { return $this->belongsTo(Complex::class); }
    public function unit(): BelongsTo { return $this->belongsTo(ComplexResidentialUnit::class, 'residential_unit_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Agent::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function instalments(): HasMany { return $this->hasMany(PropertySaleInstalment::class, 'sale_id')->orderBy('sequence'); }
    public function payments(): HasMany { return $this->hasMany(PropertySalePayment::class, 'sale_id')->orderBy('paid_on')->orderBy('id'); }

    public function isLive(): bool
    {
        return in_array($this->status, self::LIVE, true);
    }

    /** Confirmed money received. */
    public function collected(): float
    {
        return round((float) $this->payments->where('status', 'confirmed')->sum('amount'), 2);
    }

    public function balance(): float
    {
        return round(max(0, $this->price - $this->collected()), 2);
    }

    /** Instalments past their due date and not fully paid. */
    public function overdue()
    {
        return $this->instalments->filter(fn ($i) => $i->isOverdue());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }
}
