<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexAmenity extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    use HasFactory;

    protected $fillable = [
        'complex_id',
        'amenity_type',
        'parking_slots',
        'parking_ids',
        'garbage_frequency',
        'internet_provider',
        'internet_speed',
        'security_type',
        'water_type',
        'gym_description',
        'elevator_count',
        'cctv_coverage',
        'cleaning_schedule',
        'backup_type',
        'conference_capacity',
        'loading_bay_size',
        'currency',
        'annual_price',
        'common_area_water_description',
        'common_area_electricity_description',
        'landscaping_description',
    ];

    public function complex()
    {
        return $this->belongsTo(Complex::class);
    }

    public function parkingSlots()
    {
        return $this->hasMany(ComplexParkingSlot::class, 'amenity_id');
    }

    public function tenantManagements()
    {
        return $this->belongsToMany(
            TenantManagement::class,
            'tenant_management_amenities',
            'amenity_id',
            'tenant_management_id'
        )->withTimestamps();
    }
}
