<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgentCommissionTier extends Model
{
    use HasFactory;

    protected $table = 'agent_commission_tiers';

    protected $fillable = [
        'agent_id',
        'label',
        'min_value',
        'max_value',
        'rate',
    ];

    protected $casts = [
        'min_value' => 'decimal:2',
        'max_value' => 'decimal:2',
        'rate'      => 'decimal:2',
    ];

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
