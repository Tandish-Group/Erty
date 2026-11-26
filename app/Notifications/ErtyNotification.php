<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * An in-app notification shown in the bell panel. Everything the panel needs is stored with it:
 * kind (e.g. lead.assigned), title, body, link, icon and tone, plus an optional key used to avoid
 * sending the same reminder twice. Built by App\Services\NotificationCenter.
 */
class ErtyNotification extends Notification
{
    use Queueable;

    public function __construct(public array $payload) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return $this->payload;
    }
}
