<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in the activity log (see App\Services\Access\ActivityLogger). */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const EVENTS = [
        'viewed' => 'Opened', 'created' => 'Added', 'changed' => 'Changed', 'deleted' => 'Deleted', 'downloaded' => 'Downloaded',
        'refused' => 'Refused', 'signed_in' => 'Signed in', 'signed_out' => 'Signed out', 'sign_in_failed' => 'Sign-in failed',
    ];

    public const TONES = [
        'viewed' => 'dark', 'created' => 'success', 'changed' => 'primary', 'deleted' => 'danger', 'downloaded' => 'info',
        'refused' => 'warning', 'signed_in' => 'success', 'signed_out' => 'dark', 'sign_in_failed' => 'danger',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? ucfirst(str_replace('_', ' ', $this->event));
    }
}
