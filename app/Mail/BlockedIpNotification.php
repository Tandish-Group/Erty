<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BlockedIpNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $ip;
    public $url;

    public function __construct($ip, $url)
    {
        $this->ip = $ip;
        $this->url = $url;
    }

    public function build()
    {
        return $this->subject('Blocked IP Notification')
                    ->html("
                        <p>An IP address has been blocked:</p>
                        <p>IP Address: {$this->ip}</p>
                        <p>URL Attempted: {$this->url}</p>
                    ");
    }
}