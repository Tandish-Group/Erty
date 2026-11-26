<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMovement extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** A move is visible when either end of it is at an assigned property. */
    public function restrictToProperties(\Illuminate\Database\Eloquent\Builder $query, array $complexIds, ?int $agentId, int $userId): void
    {
        $query->where(fn ($q) => $q->whereIn($this->qualifyColumn('old_complex_id'), $complexIds)->orWhereIn($this->qualifyColumn('new_complex_id'), $complexIds));
    }

    use HasFactory;

    protected $table = 'tenant_movements';

    protected $fillable = [
        'ref',
        'managed_by',
        'tenant_profile_id',
        'old_tenancy_id',
        'new_tenancy_id',
        'old_complex_id',
        'old_unit_type',
        'old_unit_id',
        'old_unit_number',
        'new_complex_id',
        'new_unit_type',
        'new_unit_id',
        'new_unit_number',
        'move_type',
        'requested_date',
        'transfer_date',
        'executed_at',
        'old_rent',
        'new_rent',
        'old_deposit',
        'new_deposit',
        'deposit_adjustment',
        'deposit_adjustment_type',
        'proration_amount',
        'currency',
        'arrears_amount',
        'arrears_acknowledged',
        'parking_action',
        'old_parking_slot_id',
        'new_parking_slot_id',
        'lease_data',
        'status',
        'initiated_by',
        'approved_by',
        'cancelled_by',
        'notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'requested_date'      => 'date',
        'transfer_date'       => 'date',
        'executed_at'         => 'datetime',
        'old_rent'            => 'decimal:2',
        'new_rent'            => 'decimal:2',
        'old_deposit'         => 'decimal:2',
        'new_deposit'         => 'decimal:2',
        'deposit_adjustment'  => 'decimal:2',
        'proration_amount'    => 'decimal:2',
        'arrears_amount'      => 'decimal:2',
        'arrears_acknowledged'=> 'boolean',
        'lease_data'          => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────

    public function oldTenancy(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class, 'old_tenancy_id');
    }

    public function newTenancy(): BelongsTo
    {
        return $this->belongsTo(TenantManagement::class, 'new_tenancy_id');
    }

    public function tenantProfile(): BelongsTo
    {
        return $this->belongsTo(TenantProfile::class, 'tenant_profile_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending'   => '<span class="badge badge-light-warning">Pending</span>',
            'approved'  => '<span class="badge badge-light-primary">Approved</span>',
            'completed' => '<span class="badge badge-light-success">Completed</span>',
            'cancelled' => '<span class="badge badge-light-danger">Cancelled</span>',
            default     => '<span class="badge badge-light-secondary">' . ucfirst($this->status) . '</span>',
        };
    }

    public function getFromUnitLabelAttribute(): string
    {
        return $this->old_unit_number ?? 'Unit ' . $this->old_unit_id;
    }

    public function getToUnitLabelAttribute(): string
    {
        return $this->new_unit_number ?? 'Unit ' . $this->new_unit_id;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────

    public static function generateRef(): string
    {
        do {
            $ref = 'TM-' . strtoupper(\Illuminate\Support\Str::random(8));
        } while (static::where('ref', $ref)->exists());

        return $ref;
    }
}
