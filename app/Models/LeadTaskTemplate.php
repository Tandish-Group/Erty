<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadTaskTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_type',
        'phase',
        'title',
        'description',
        'instructions',
        'sequence_order',
        'sla_hours',
        'is_required',
        'blocks_next_phase',
        'is_active',
        'trigger_conditions',
        'completion_requirements'
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'blocks_next_phase' => 'boolean',
        'is_active' => 'boolean',
        'trigger_conditions' => 'array',
        'completion_requirements' => 'array'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSystemAssigned($query)
    {
        return $query->where('task_type', 'system_assigned');
    }

    public function scopeInPhase($query, $phase)
    {
        return $query->where('phase', $phase);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence_order');
    }

    public function createTaskForLead(Lead $lead): LeadTask
    {
        return LeadTask::firstOrCreate(
            ['lead_id' => $lead->id, 'template_id' => $this->id],
            [
                'assigned_agent_id' => $lead->assigned_agent_id,
                'task_type' => $this->task_type,
                'phase' => $this->phase,
                'title' => $this->title,
                'description' => $this->description,
                'sequence_order' => $this->sequence_order,
                'sla_hours' => $this->sla_hours,
                'is_required' => $this->is_required,
                'blocks_next_phase' => $this->blocks_next_phase,
                'status' => 'pending'
            ]
        );
    }
}
