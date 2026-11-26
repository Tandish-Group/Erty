<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    /** Sign-in codes expire this many minutes after they are sent. */
    public const EXPIRY_MINUTES = 10;

    protected $fillable = [
        'otp',
        'status',
        'expiry',
        'user_id',
    ];
}
