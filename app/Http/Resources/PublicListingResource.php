<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A published listing as renters see it. Fields are listed one by one on purpose: no internal ids,
 * no owner, staff, tenant or contact details (the property's email, phone and principal stay private).
 *
 * Expects the listing with complex.amenities, complexUnit, commercialUnit and photos loaded, and
 * $availableUnits set by the controller.
 */
class PublicListingResource extends JsonResource
{
    public const FACILITY_LABELS = [
        'parking' => 'Parking', 'water' => 'Water', 'security' => 'Security', 'internet' => 'Internet',
        'gym' => 'Gym', 'elevator' => 'Lift', 'cctv' => 'CCTV', 'cleaning' => 'Cleaning', 'backup' => 'Backup power',
        'conference' => 'Conference room', 'loading_bay' => 'Loading bay', 'garbage' => 'Garbage collection',
        'common_area_water' => 'Common area water', 'common_area_electricity' => 'Common area electricity', 'landscaping' => 'Landscaping',
    ];

    /** @param array{rent:int,sale:int}|null $offerCounts free houses to let / for sale (house listings) */
    public function __construct($resource, private int $availableUnits = 0, private ?array $offerCounts = null, private bool $salesShown = true)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $listing = $this->resource;
        $complex = $listing->complex;
        $type = $listing->complexUnit;
        $space = $listing->commercialUnit;
        $number = fn ($v) => $v === null || $v === '' ? null : (is_numeric($v) ? (float) $v : null);

        $house = $type && $type->unit_category === 'house';
        $offer = $house ? ($listing->offer ?: 'rent') : 'rent';
        if (!$this->salesShown && $offer !== 'rent') $offer = 'rent';
        $forRent = in_array($offer, ['rent', 'both'], true);
        $forSale = in_array($offer, ['sale', 'both'], true);

        return [
            'ref' => $listing->ref,
            'title' => $listing->name,
            'category' => $listing->isCommercial() ? 'commercial' : 'residential',
            'property_type' => $listing->isCommercial() ? 'commercial' : ($house ? 'house' : 'apartment'),
            'offer' => $offer,
            'unit' => $listing->isCommercial()
                ? [
                    'size_sq_ft' => $number($space?->unit_sq_ft),
                    'minimum_let_sq_ft' => $number($space?->min_sq_ft),
                    'floor' => $space ? ($space->custom_floors ?: $space->floors) : null,
                ]
                : [
                    'bedrooms' => (int) ($type?->bedrooms ?? $listing->bedrooms),
                    'bathrooms' => (int) ($type?->bathrooms ?? $listing->bathrooms),
                    'size_sq_ft' => $number($type?->sq_ft ?? $listing->sq_area),
                    'furnishing' => $listing->furnishing,
                ] + ($house ? [
                    'house_type' => $type->unit_type,
                    'storeys' => $type->storeys,
                    'plot' => $type->plot_size ? ['size' => (float) $type->plot_size, 'unit' => $type->plot_unit] : null,
                    'features' => collect($type->features ?: [])->map(fn ($f) => config('property.house_features')[$f] ?? $f)->values()->all(),
                ] : []),
            // "rent" stays for older clients; it is null for a house offered for sale only.
            'rent' => $forRent ? ['amount' => (int) $listing->amount, 'currency' => $listing->currency, 'period' => 'month'] : null,
            'deposit' => $forRent ? ['amount' => (int) $listing->deposit, 'currency' => $listing->currency] : null,
            'sale' => $forSale ? ['price' => (float) $listing->sale_price, 'currency' => $listing->currency] : null,
            'available_units' => $this->availableUnits,
            'available_to_let' => $house ? ($forRent ? ($this->offerCounts['rent'] ?? 0) : 0) : $this->availableUnits,
            'available_for_sale' => $house && $forSale ? ($this->offerCounts['sale'] ?? 0) : 0,
            'total_units' => $listing->isCommercial() ? 1 : (int) $listing->units,
            'description' => $listing->property_description,
            'photos' => $listing->photos->values()->map(fn ($photo, $i) => ['url' => $photo->url(), 'cover' => $i === 0])->all(),
            'property' => [
                'name' => $complex?->title,
                'county' => $complex?->county,
                'area' => $complex?->county_specific,
                'location' => $complex?->location_description,
                'latitude' => $number($complex?->latitude),
                'longitude' => $number($complex?->longitude),
                'facilities' => $complex
                    ? $complex->amenities->pluck('amenity_type')->filter()->unique()->map(fn ($t) => self::FACILITY_LABELS[$t] ?? ucfirst(str_replace('_', ' ', $t)))->values()->all()
                    : [],
            ],
            'published_at' => $listing->published_at?->toIso8601String(),
            'updated_at' => $listing->updated_at?->toIso8601String(),
        ];
    }
}
