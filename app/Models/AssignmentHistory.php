<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One period of responsibility for a property (company or primary manager) or a unit (its agent).
 * Rows are closed when responsibility changes, never edited or deleted (AssignmentHistoryService).
 */
class AssignmentHistory extends Model
{
    use Concerns\RestrictedToAssignedProperties;

    protected $table = 'assignment_history';

    public const PROPERTY = 'property';
    public const RESIDENTIAL = 'residential_unit';
    public const COMMERCIAL = 'commercial_unit';

    protected $fillable = ['complex_id', 'scope', 'unit_id', 'assignee_type', 'agent_id', 'role', 'starts_at', 'ends_at', 'assigned_by', 'ended_by', 'reason'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function agent() { return $this->belongsTo(Agent::class); }
    public function complex() { return $this->belongsTo(Complex::class); }
    public function assignedBy() { return $this->belongsTo(User::class, 'assigned_by'); }
    public function endedBy() { return $this->belongsTo(User::class, 'ended_by'); }

    /** The assignment scope for a unit kind: "commercial" or residential. */
    public static function scopeForUnitKind(string $kind): string
    {
        return $kind === 'commercial' ? self::COMMERCIAL : self::RESIDENTIAL;
    }

}
