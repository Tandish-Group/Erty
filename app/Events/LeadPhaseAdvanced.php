<?php

namespace App\Events;

use App\Models\Lead;
use App\Enums\LeadWorkflowPhaseEnum;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadPhaseAdvanced
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Lead $lead;
    public LeadWorkflowPhaseEnum $newPhase;

    /**
     * Create a new event instance.
     */
    public function __construct(Lead $lead, LeadWorkflowPhaseEnum $newPhase)
    {
        $this->lead = $lead;
        $this->newPhase = $newPhase;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('lead.' . $this->lead->id),
        ];
    }
}
