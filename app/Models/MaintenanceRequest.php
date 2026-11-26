<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A reported repair at a property, tracked from report to closure. */
class MaintenanceRequest extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    protected $fillable = [
        'reference', 'managed_by', 'complex_id', 'residential_unit_id', 'commercial_unit_id', 'tenancy_id',
        'title', 'description', 'category', 'priority', 'status', 'reported_by', 'reported_via', 'reported_at',
        'logged_by', 'assigned_user_id', 'assigned_at', 'started_at', 'completed_at', 'closed_at', 'resolution',
        'cost', 'cost_borne_by', 'expense_id', 'tenant_invoice_id',
    ];

    protected $casts = [
        'reported_at' => 'datetime', 'assigned_at' => 'datetime', 'started_at' => 'datetime',
        'completed_at' => 'datetime', 'closed_at' => 'datetime', 'cost' => 'decimal:2',
    ];

    public const CATEGORIES = ['plumbing' => 'Plumbing', 'electrical' => 'Electrical', 'appliance' => 'Appliance', 'structural' => 'Structural / building', 'security' => 'Security & locks', 'cleaning' => 'Cleaning & pests', 'other' => 'Other'];
    public const PRIORITIES = ['urgent' => 'Urgent', 'high' => 'High', 'normal' => 'Normal', 'low' => 'Low'];
    public const STATUSES = ['open' => 'Open', 'assigned' => 'Assigned', 'in_progress' => 'In progress', 'completed' => 'Completed', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];
    public const OPEN_STATUSES = ['open', 'assigned', 'in_progress'];

    /** Team members see requests at their properties (only their units' on unit-only properties), and any request assigned to them. */
    public function restrictToProperties(Builder $query, array $complexIds, ?int $agentId, int $userId): void
    {
        $query->where(function ($q) use ($complexIds, $userId) {
            $q->where(fn ($scoped) => static::constrainUnits($scoped, $this->qualifyColumn('complex_id'), $complexIds,
                    ['residential' => $this->qualifyColumn('residential_unit_id'), 'commercial' => $this->qualifyColumn('commercial_unit_id')]))
              ->orWhere($this->qualifyColumn('assigned_user_id'), $userId);
        });
    }

    public function complex(): BelongsTo { return $this->belongsTo(Complex::class); }
    public function residentialUnit(): BelongsTo { return $this->belongsTo(ComplexResidentialUnit::class, 'residential_unit_id'); }
    public function commercialUnit(): BelongsTo { return $this->belongsTo(CommercialUnit::class, 'commercial_unit_id'); }
    public function tenancy(): BelongsTo { return $this->belongsTo(TenantManagement::class, 'tenancy_id'); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class, 'assigned_user_id'); }
    public function logger(): BelongsTo { return $this->belongsTo(User::class, 'logged_by'); }
    public function expense(): BelongsTo { return $this->belongsTo(ComplexExpense::class, 'expense_id'); }
    public function invoice(): BelongsTo { return $this->belongsTo(TenantInvoice::class, 'tenant_invoice_id'); }
    public function notes(): HasMany { return $this->hasMany(MaintenanceRequestNote::class)->latest('id'); }

    public function getUnitLabelAttribute(): ?string
    {
        return $this->residentialUnit?->unit_number ?? $this->commercialUnit?->unit_number;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
