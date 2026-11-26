<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'businesses';

    protected $fillable = [
        'account_type', 'business_name', 'business_description', 'business_site', 'is_demo_account',
        'business_email', 'user_id', 'registration_number', 'date_of_incorporation', 'is_verified', 'verified_at',
        'storage_used', 'modules'
    ];

    protected $casts = [
        'modules' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
}
