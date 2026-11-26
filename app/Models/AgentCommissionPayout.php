<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Commission paid to an agent for a period; the allocations it covered are stamped with its id. */
class AgentCommissionPayout extends Model
{
    protected $fillable = [
        'agent_id', 'kind', 'managed_by', 'period_from', 'period_to', 'currency', 'rent_collected', 'commission',
        'override', 'clawback', 'total', 'lines', 'method', 'reference', 'notes', 'paid_at', 'paid_by',
    ];

    protected $casts = [
        'period_from' => 'date',
        'period_to' => 'date',
        'paid_at' => 'datetime',
        'lines' => 'array',
        'rent_collected' => 'decimal:2',
        'commission' => 'decimal:2',
        'override' => 'decimal:2',
        'clawback' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

}
