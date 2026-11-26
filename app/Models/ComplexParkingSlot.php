<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplexParkingSlot extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    use HasFactory;
    
    protected $fillable = [
        'complex_id',
        'amenity_id',
        'parking_id',
        'is_occupied',
        'occupied_tenant_id',
        'is_additional_slot',
        'additional_slot_charge',

    ];

    public function complex()
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }
    
    public function amenity()
    {
        return $this->belongsTo(ComplexAmenity::class, 'amenity_id');
    }
    
    
    public function tenantManagements()
    {
        return $this->belongsToMany(
            TenantManagement::class,
            'tenant_parking_slots', // if you have this pivot table
            'complex_parking_slot_id',
            'tenant_management_id'
        )->withTimestamps();
    }
}
