<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Legal extends Model
{
    use \App\Models\Concerns\BelongsToBusiness;

    protected $fillable = [
        'file_name',
        'legal_type_id',
        'legal_folder_id',
        'user_id',
    ];
}
