<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A support ticket posted by a team member and answered by the installation's platform administrators.
 * The conversation is the ticket's description followed by its messages; status changes are recorded on
 * the message that made them.
 */
class SupportTicket extends Model
{
    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'waiting_on_user' => 'Waiting on you',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    /** Labels the administrators see (the poster sees "Waiting on you"). */
    public const ADMIN_STATUS_LABELS = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'waiting_on_user' => 'Waiting on user',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
    ];

    /** Statuses that still need something from someone. */
    public const OPEN_STATUSES = ['open', 'in_progress', 'waiting_on_user'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];

    public const STATUS_TONES = [
        'open' => 'warning', 'in_progress' => 'primary', 'waiting_on_user' => 'info', 'resolved' => 'success', 'closed' => 'secondary',
    ];

    protected $fillable = [
        'description', 'support_id', 'custom_topic', 'user_id', 'status', 'priority', 'assigned_to',
        'last_activity_at', 'resolved_at', 'closed_at',
    ];

    protected $attributes = ['status' => 'open', 'priority' => 'normal'];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            $ticket->last_activity_at ??= now();
        });
        // The reference needs the id, so it is set straight after the ticket is saved.
        static::created(function (SupportTicket $ticket) {
            $ticket->forceFill(['reference' => sprintf('SUP-%s-%04d', $ticket->created_at->format('ym'), $ticket->id)])->saveQuietly();
        });
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Support::class, 'support_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class, 'ticket_id')->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class, 'ticket_id')->orderBy('id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** The topic shown in lists: the custom topic for "other", otherwise the topic name. */
    public function topicLabel(): string
    {
        return $this->custom_topic ?: ($this->topic?->topic ?? 'General');
    }

    /** A one-line subject from the topic and the start of the description. */
    public function subject(int $length = 80): string
    {
        $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $this->description))));

        return \Illuminate\Support\Str::limit($text, $length);
    }

    public function statusLabel(bool $forAdmin = false): string
    {
        return ($forAdmin ? self::ADMIN_STATUS_LABELS : self::STATUSES)[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusTone(): string
    {
        return self::STATUS_TONES[$this->status] ?? 'secondary';
    }

    /** Move to a status, stamping resolved/closed dates. Returns the previous status. */
    public function moveTo(string $status): string
    {
        $from = $this->status;
        $this->status = $status;
        $this->resolved_at = $status === 'resolved' ? ($this->resolved_at ?? now()) : ($status === 'closed' ? $this->resolved_at : null);
        $this->closed_at = $status === 'closed' ? now() : null;

        return $from;
    }
}
