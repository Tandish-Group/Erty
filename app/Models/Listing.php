<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A listing advertises one unit type of a property (for example the 2-bedroom flats of a block)
 * or one commercial space. Drafts are created by UnitListingService when a property's setup is
 * confirmed; staff add rent, deposit, description and photos, then publish. The number of units
 * available is never stored: it is counted from the units and their tenancies.
 */
class Listing extends Model
{
    use \App\Models\Concerns\RestrictedToAssignedProperties;

    public const DRAFT = 'Draft';
    public const PUBLISHED = 'Published';
    public const UNPUBLISHED = 'Unpublished';

    protected $fillable = [
        'name',
        'ref',
        'slug',
        'category',
        'county',
        'county_specific',
        'longitude',
        'latitude',
        'location_description',
        'type',
        'units',
        'currency',
        'class',
        'furnishing',
        'bedrooms',
        'condition',
        'bathrooms',
        'sq_area',
        'nearby_amenities',
        'external_amenities',
        'internal_amenities',
        'amount',
        'viewing_fee',
        'link',
        'property_description',
        'status',
        'availability',
        'complex_id',
        'complex_unit_id',
        'deposit',
        'published_at',
        'offer',
        'sale_price',
        'user_id',
        'original_user_id', 
        'commercial_unit_id',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'published_at' => 'datetime',
        'sale_price' => 'float',
        'amount' => 'integer',
        'deposit' => 'integer',
    ];

    public function complexUnit()
    {
        return $this->belongsTo(ComplexUnit::class);
    }

    public function commercialUnit()
    {
        return $this->belongsTo(CommercialUnit::class);
    }

    /** Photos, in the order they were added. */
    public function photos()
    {
        return $this->hasMany(ImageUpload::class)->where('type', ImageUpload::LISTING_PHOTO)->orderBy('id');
    }

    public function scopePublished($query)
    {
        return $query->where($this->getTable() . '.status', self::PUBLISHED);
    }

    public function isPublished(): bool
    {
        return $this->status === self::PUBLISHED;
    }

    public function isCommercial(): bool
    {
        return $this->commercial_unit_id !== null;
    }

    /** "2-bedroom, 2-bathroom apartment", "Studio apartment" or "Commercial space G01". */
    public static function titleFor(?ComplexUnit $type, ?CommercialUnit $space = null): string
    {
        if ($space) {
            return 'Commercial space ' . $space->unit_number;
        }
        $bedrooms = (int) ($type->bedrooms ?? 0);
        $bathrooms = (int) ($type->bathrooms ?? 0);
        if ($type && $type->unit_category === 'house') {
            // "4-bedroom Maisonette", "3-bedroom Bungalow"
            return ($bedrooms ? $bedrooms . '-bedroom ' : '') . ($type->unit_type ?: 'House');
        }
        $rooms = $bedrooms === 0 ? 'Studio' : $bedrooms . '-bedroom';
        return $rooms . ($bathrooms > 0 ? ', ' . $bathrooms . '-bathroom' : '') . ' apartment';
    }

    /** What is still missing before this listing can be published. */
    public function missingForPublishing(): array
    {
        $rent = in_array($this->offer ?: 'rent', ['rent', 'both'], true);
        $sale = in_array($this->offer, ['sale', 'both'], true);
        return array_values(array_filter([
            !$rent || $this->amount > 0 ? null : 'the monthly rent',
            !$rent || $this->deposit !== null ? null : 'the deposit (enter 0 if there is none)',
            !$sale || $this->sale_price > 0 ? null : 'the asking price',
            trim((string) $this->property_description) !== '' ? null : 'a description',
        ]));
    }

    public function images(){
        return $this->hasMany('App\Models\ImageUpload');
    }

    public function user(){
        return $this->belongsTo('App\Models\User');
    }

    public function complex(){
        return $this->belongsTo('App\Models\Complex');
    }

    public function serviceCharges(){
        return $this->hasMany('App\Models\ServiceCharge');
    }

    

    /**
     * Public marketplace visibility: exclude businesses that switched the Listings module off.
     */
    public function scopeOnMarketplace($query)
    {
        $hidden = app(\App\Services\Access\ModuleService::class)->ownersWithModuleOff(\App\Services\Access\ModuleService::LISTINGS);

        return $hidden ? $query->whereNotIn($this->getTable() . '.user_id', $hidden) : $query;
    }

    public function isOnMarketplace(): bool
    {
        return !in_array((int) $this->user_id, app(\App\Services\Access\ModuleService::class)->ownersWithModuleOff(\App\Services\Access\ModuleService::LISTINGS), true);
    }
}
