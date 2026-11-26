<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complex extends Model
{
    use \App\Models\Concerns\BelongsToBusiness;

    use \App\Models\Concerns\RestrictedToAssignedProperties;

    /** Limited team members only see the properties assigned to them. */
    protected string $propertyColumn = 'id';

    protected $fillable = [
        'ref_code',
        'title',
        'slug',
        'email',
        'mobile',
        'description',
        'available',
        'setup_status',
        'setup_confirmed_at',
        'setup_confirmed_by',
        'principal',
        'type',
        'longitude',
        'latitude',
        'class',
        'county',
        'county_specific',
        'location_description',
        'created_at',
        'updated_at',
        'user_id',
        'original_user_id',
        'assigned_agent_id',
        'management_type',
    ];

    protected $casts = [
        'setup_confirmed_at' => 'datetime',
    ];

    /** Team members assigned to manage this property. */
    public function managers()
    {
        return $this->belongsToMany(User::class, 'complex_user')->withPivot('assigned_by')->withTimestamps();
    }

    public const MANAGED_BY_COMPANY = 'company';
    public const MANAGED_BY_INDIVIDUAL = 'individual';

    /** Managed by the business as a team (no single primary property manager). */
    public function isCompanyManaged(): bool
    {
        return $this->management_type !== self::MANAGED_BY_INDIVIDUAL || !$this->assigned_agent_id;
    }

    public function assignedAgent()
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function listings(){
        return $this->hasMany(Listing::class);
    }

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function serviceCharges(){
        return $this->hasMany('App\Models\ServiceCharge');
    }

    public function changes()
    {
        return $this->hasMany(ComplexChange::class);
    }

    public function commercialUnits()
    {
        return $this->hasMany(CommercialUnit::class);
    }

    public function amenities()
    {
        return $this->hasMany(ComplexAmenity::class, 'complex_id');
    }

    public function parkingSlots()
    {
        return $this->hasMany(ComplexParkingSlot::class, 'complex_id');
    }

    public function links()
    {
        return $this->hasMany(ComplexLink::class, 'complex_id');
    }

    public function complex_units()
    {
        return $this->hasMany(ComplexUnit::class, 'complex_id');
    }

    public function complex_residential_units()
    {
        return $this->hasMany(ComplexResidentialUnit::class, 'complex_id');
    }
}
