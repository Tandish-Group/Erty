<?php

namespace App\Enums;

enum LeadTaskTypeEnum: string
{
    case SYSTEM_ASSIGNED = 'system_assigned';
    case AGENT_OPERATIONAL = 'agent_operational';
    
    public function label(): string
    {
        return match($this) {
            self::SYSTEM_ASSIGNED => 'System Assigned',
            self::AGENT_OPERATIONAL => 'Agent Operational',
        };
    }
}