<?php

namespace App\Enums;

enum LeadWorkflowPhaseEnum: string
{
    case QUALIFICATION = 'qualification';
    case ENGAGEMENT = 'engagement';
    case CONVERSION = 'conversion';
    case FINALIZATION = 'finalization';
    case COMPLETED = 'completed';
    
    public function label(): string
    {
        return match($this) {
            self::QUALIFICATION => 'Qualification & Matching',
            self::ENGAGEMENT => 'Viewing & Engagement',
            self::CONVERSION => 'Conversion',
            self::FINALIZATION => 'Finalization',
            self::COMPLETED => 'Completed',
        };
    }
    
    public function sequenceOrder(): int
    {
        return match($this) {
            self::QUALIFICATION => 1,
            self::ENGAGEMENT => 2,
            self::CONVERSION => 3,
            self::FINALIZATION => 4,
            self::COMPLETED => 5,
        };
    }
    
    public function nextPhase(): ?self
    {
        return match($this) {
            self::QUALIFICATION => self::ENGAGEMENT,
            self::ENGAGEMENT => self::CONVERSION,
            self::CONVERSION => self::FINALIZATION,
            self::FINALIZATION => self::COMPLETED,
            self::COMPLETED => null,
        };
    }
}