<?php

namespace App\Mail;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Support ticket emails: "opened" goes to the administrators (and SUPPORT_EMAIL), "updated" goes to the
 * person who posted the ticket when an administrator replies or changes its status.
 */
class SupportTicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public SupportTicket $ticket,
        public string $kind,
        public ?SupportTicketMessage $message = null,
        public string $url = '',
    ) {
    }

    public function build()
    {
        $subject = $this->kind === 'opened'
            ? "New support ticket {$this->ticket->reference}: {$this->ticket->topicLabel()}"
            : "Your support ticket {$this->ticket->reference}: " . $this->ticket->statusLabel();

        return $this->subject($subject)->view('emails.support.ticket');
    }
}
