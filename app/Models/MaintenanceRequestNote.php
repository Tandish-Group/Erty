<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequestNote extends Model
{
    protected $fillable = ['maintenance_request_id', 'user_id', 'status_from', 'status_to', 'note'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
