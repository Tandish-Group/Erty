<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;
use App\Enums\LeadTaskStatusEnum;
use App\Enums\LeadTaskTypeEnum;

class LeadTask extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** The member's own tasks, plus tasks on leads for an assigned property. */
    public function restrictToProperties(\Illuminate\Database\Eloquent\Builder $query, array $complexIds, ?int $agentId, int $userId): void
    {
        $full = array_values(array_diff($complexIds, array_keys(app(\App\Services\Access\PropertyAccess::class)->unitScope())));
        $query->where(function ($q) use ($full, $agentId) {
            $q->whereIn($this->qualifyColumn('lead_id'), \Illuminate\Support\Facades\DB::table('leads')->select('id')->whereIn('complex_id', $full ?: [0]));
            if ($agentId) $q->orWhere($this->qualifyColumn('assigned_agent_id'), $agentId);
        });
    }

    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lead_id',
        'assigned_agent_id',
        'template_id',
        'task_type',
        'phase',
        'title',
        'description',
        'status',
        'sequence_order',
        'is_required',
        'blocks_next_phase',
        'due_date',
        'sla_hours',
        'sla_breach_at',
        'is_overdue',
        'started_at',
        'completed_at',
        'completion_notes',
        'completion_data',
        'blocked_by_task_id',
        'block_reason',
        'created_by',
        'completed_by'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'sla_breach_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_required' => 'boolean',
        'blocks_next_phase' => 'boolean',
        'is_overdue' => 'boolean',
        'completion_data' => 'array',
        'status' => LeadTaskStatusEnum::class,
        'task_type' => LeadTaskTypeEnum::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($task) {
            if (!$task->created_by && auth()->check()) {
                $task->created_by = auth()->id();
            }
            
            // Calculate SLA breach time
            if ($task->sla_hours) {
                $task->sla_breach_at = Carbon::now()->addHours($task->sla_hours);
            }
        });

        static::updating(function ($task) {
            // Auto-mark overdue
            if ($task->sla_breach_at && Carbon::now()->greaterThan($task->sla_breach_at)) {
                $task->is_overdue = true;
            }
            
            // Set completion timestamp
            if ($task->isDirty('status') && $task->status === LeadTaskStatusEnum::COMPLETED && !$task->completed_at) {
                $task->completed_at = Carbon::now();
                $task->completed_by = auth()->id();
            }
        });
    }

    // Relationships
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignedAgent()
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function template()
    {
        return $this->belongsTo(LeadTaskTemplate::class, 'template_id');
    }

    public function blockedByTask()
    {
        return $this->belongsTo(LeadTask::class, 'blocked_by_task_id');
    }

    public function blockingTasks()
    {
        return $this->hasMany(LeadTask::class, 'blocked_by_task_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', LeadTaskStatusEnum::PENDING);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', LeadTaskStatusEnum::COMPLETED);
    }

    /** Open tasks that are flagged overdue or past their SLA time (the same rule as the task board). */
    public function scopeOverdue($query)
    {
        return $query->whereNotIn('status', [LeadTaskStatusEnum::COMPLETED, LeadTaskStatusEnum::CANCELLED])
                    ->where(fn ($q) => $q->where('is_overdue', true)->orWhere('sla_breach_at', '<', now()));
    }

    public function scopeForAgent($query, $agentId)
    {
        return $query->where('assigned_agent_id', $agentId);
    }

    public function scopeInPhase($query, $phase)
    {
        return $query->where('phase', $phase);
    }

    public function scopeSystemAssigned($query)
    {
        return $query->where('task_type', LeadTaskTypeEnum::SYSTEM_ASSIGNED);
    }

    // Methods
    public function markInProgress()
    {
        $this->update([
            'status' => LeadTaskStatusEnum::IN_PROGRESS,
            'started_at' => $this->started_at ?? Carbon::now()
        ]);
    }

    public function markCompleted(?string $notes = null, ?array $data = null)
    {
        $this->update([
            'status' => LeadTaskStatusEnum::COMPLETED,
            'completed_at' => Carbon::now(),
            'completion_notes' => $notes,
            'completion_data' => $data,
            'completed_by' => auth()->id()
        ]);

        // Unblock any tasks blocked by this one
        $this->blockingTasks()->update([
            'status' => LeadTaskStatusEnum::PENDING,
            'blocked_by_task_id' => null,
            'block_reason' => null
        ]);
    }

    public function markBlocked($blockedByTaskId, string $reason)
    {
        $this->update([
            'status' => LeadTaskStatusEnum::BLOCKED,
            'blocked_by_task_id' => $blockedByTaskId,
            'block_reason' => $reason
        ]);
    }

    public function skip(string $reason)
    {
        if (!$this->is_required) {
            $this->update([
                'status' => LeadTaskStatusEnum::SKIPPED,
                'completion_notes' => $reason,
                'completed_at' => Carbon::now(),
                'completed_by' => auth()->id()
            ]);
        }
    }

    public function canAdvancePhase(): bool
    {
        return $this->blocks_next_phase && $this->status === LeadTaskStatusEnum::COMPLETED;
    }

    public function isOverdue(): bool
    {
        return $this->sla_breach_at && Carbon::now()->greaterThan($this->sla_breach_at) 
               && !in_array($this->status, [LeadTaskStatusEnum::COMPLETED, LeadTaskStatusEnum::CANCELLED]);
    }

    public function getTimeRemaining(): ?string
    {
        if (!$this->sla_breach_at || $this->status === LeadTaskStatusEnum::COMPLETED) {
            return null;
        }

        $diff = Carbon::now()->diffInMinutes($this->sla_breach_at, false);
        
        if ($diff < 0) {
            return 'Overdue by ' . Carbon::now()->diffForHumans($this->sla_breach_at, ['parts' => 2]);
        }

        return 'Due in ' . Carbon::now()->diffForHumans($this->sla_breach_at, ['parts' => 2]);
    }
}