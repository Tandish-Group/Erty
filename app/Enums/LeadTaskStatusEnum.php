<?php

namespace App\Enums;

enum LeadTaskStatusEnum: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case BLOCKED = 'blocked';
    case CANCELLED = 'cancelled';
    case SKIPPED = 'skipped';
    
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::BLOCKED => 'Blocked',
            self::CANCELLED => 'Cancelled',
            self::SKIPPED => 'Skipped',
        };
    }
    
    public function badgeClass(): string
    {
        return match($this) {
            self::PENDING => 'badge-light-secondary',
            self::IN_PROGRESS => 'badge-light-warning',
            self::COMPLETED => 'badge-light-success',
            self::BLOCKED => 'badge-light-danger',
            self::CANCELLED => 'badge-light-dark',
            self::SKIPPED => 'badge-light-info',
        };
    }
}