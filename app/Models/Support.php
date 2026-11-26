<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A support topic people choose when they post a ticket (seeded by SupportTopicSeeder). */
class Support extends Model
{
    protected $fillable = ['topic'];

    public function tickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'support_id');
    }

    /** The catch-all topic, which lets people type their own. */
    public function isOther(): bool
    {
        return (bool) preg_match('/\bother\b/i', (string) $this->topic);
    }
}
