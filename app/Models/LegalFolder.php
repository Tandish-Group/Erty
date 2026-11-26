<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LegalFolder extends Model
{
    use \App\Models\Concerns\BelongsToBusiness;

    
    protected $fillable = [
        'name',
        'slug',
        'user_id',
    ];
}
