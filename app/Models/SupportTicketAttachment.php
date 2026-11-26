<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A file on a support ticket or one of its replies. Kept on a private disk and downloaded through the app. */
class SupportTicketAttachment extends Model
{
    protected $fillable = ['ticket_id', 'message_id', 'user_id', 'disk', 'path', 'original_name', 'size', 'mime'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
