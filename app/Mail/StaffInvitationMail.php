<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Invitation for a new team member to set their password and join a business on Erty. */
class StaffInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $staff,
        public string $businessName,
        public string $roleName,
        public string $invitedByName,
        public string $acceptUrl,
        public int $expiresInMinutes,
    ) {
    }

    public function build()
    {
        return $this->subject("You've been invited to join {$this->businessName} on " . config('app.name'))
            ->view('emails.team.invitation');
    }
}
