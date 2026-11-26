<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCharge extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    use HasFactory;

    protected $fillable = [
        'user_id',
        'listing_id',
        'complex_id',
        'amenity_id',
        'amenity_type',
        'name',
        'charge',
    ];

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function complex(){
        return $this->belongsTo('App\Models\Complex');
    }

    public function listing(){
        return $this->belongsTo('App\Models\Listing');
    }

}
