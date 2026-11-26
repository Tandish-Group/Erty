<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexFloorPlan extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    // use HasFactory;
    protected $fillable = [
        'plan',
        'user_id',
        'complex_id',
        'type',
    ];

    public function complex(){
        return $this->belongsTo('App\Models\Complex');
    }
}
