<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_type',
        
        // Individual Lead Fields
        'first_name',
        'last_name',
        'email',
        'phone',
        'occupation',
        'date_of_birth',
        'current_address',
        
        // Business Lead Fields
        'company_name',
        'registration_number',
        'contact_person',
        'contact_title',
        'business_email',
        'business_phone',
        'industry',
        'company_size',
        'business_address',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    // Relationships
    public function leads()
    {
        return $this->hasMany(Lead::class, 'lead_profile_id');
    }

    // Accessors
    public function getFullNameAttribute()
    {
        if ($this->lead_type === 'Business') {
            return $this->contact_person ?? $this->company_name;
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function getPrimaryEmailAttribute()
    {
        return $this->lead_type === 'Business' ? $this->business_email : $this->email;
    }

    public function getPrimaryPhoneAttribute()
    {
        return $this->lead_type === 'Business' ? $this->business_phone : $this->phone;
    }

    public function getDisplayNameAttribute()
    {
        return $this->full_name;
    }
}