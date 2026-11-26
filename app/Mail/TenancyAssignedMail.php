<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Tells an agent that a tenancy was onboarded to them (or, for a property's lead agent,
 * that one of their agents placed a tenancy in their property).
 */
class TenancyAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{agent_name:string,tenant_name:string,tenant_type:string,property:string,unit:?string,start_date:?string,rent:string,reference:string,placed_by:?string,business:string,dashboard_url:string} $details */
    public function __construct(public array $details, public bool $asLeadAgent = false)
    {
    }

    public function build()
    {
        $subject = $this->asLeadAgent
            ? "Tenancy placed in {$this->details['property']} by {$this->details['placed_by']}"
            : "New tenancy assigned to you: {$this->details['tenant_name']} at {$this->details['property']}";
        return $this->subject($subject)->view('emails.agents.tenancy-assigned');
    }
}
