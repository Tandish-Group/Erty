<?php

namespace App\Services\Properties;

use App\Services\Access\PropertyAccess;

use App\Models\Complex;
use App\Models\ComplexFloorPlan;

/** Saved reviews use the existing append-only property change history. */
class ComplexSetupService
{
    /** Residential columns that only houses use. */
    public const HOUSE_COLUMNS = ['house_type', 'plot_number', 'plot_size', 'plot_unit', 'title_number', 'offer', 'sale_price'];

    /**
     * Setup review of the whole property, whoever is looking: someone who only sees some units must not make the
     * inventory look incomplete. Works on a copy so the full unit list never reaches a limited viewer's page.
     */
    public function steps(Complex $complex): array
    {
        return app(PropertyAccess::class)->unrestricted(fn () => $this->review((clone $complex)->setRelations([])));
    }

    private function review(Complex $complex): array
    {
        $complex->load(['complex_units', 'complex_residential_units', 'commercialUnits', 'amenities', 'parkingSlots', 'serviceCharges', 'links']);
        foreach (['complex_units', 'complex_residential_units', 'commercialUnits', 'amenities', 'parkingSlots', 'serviceCharges', 'links'] as $relation) {
            $complex->setRelation($relation, $complex->getRelation($relation)->sortBy('id')->values());
        }
        $apartments = ComplexInventoryService::hasApartments($complex);
        $houses = ComplexInventoryService::hasHouses($complex);
        $commercial = ComplexInventoryService::hasCommercial($complex);
        // Apartments keep the "residential" step (and the reviews already saved for it); houses have their own.
        // Columns added for houses are left out of the apartment fingerprint so earlier reviews still match.
        $all = $complex->complex_residential_units;
        $res = $all->where('unit_category', '!=', 'house')->values();
        $homes = $all->where('unit_category', 'house')->values();
        $numbersUnique = $all->map(fn ($u) => mb_strtolower(trim($u->unit_number ?? '')))->unique()->count() === $all->count();
        $com = $complex->commercialUnits;
        $base = route('complex.show', $complex->slug);
        $steps = [
            'identity' => ['title' => 'Property details & responsibility', 'help' => 'Check the reference, contact, location and ownership information. These identify the property throughout operations.', 'data' => $complex->only(['title', 'ref_code', 'email', 'mobile', 'county', 'county_specific', 'location_description', 'latitude', 'longitude', 'description', 'principal', 'assigned_agent_id']), 'valid' => filled($complex->title) && filled($complex->county) && filled($complex->email) && filled($complex->location_description), 'url' => $base.'#settings'],
            'structure' => ['title' => 'Composition & inventory plan', 'help' => 'Confirm which inventories this property needs. Residential specifications and physical units are reviewed in the residential step; commercial spaces are reviewed separately. Mixed-use properties require both.', 'data' => ['composition' => $complex->type], 'valid' => $apartments || $houses || $commercial, 'url' => $base.'#settings'],
        ];
        if ($apartments) {
            $steps['residential'] = ['title' => $houses ? 'Apartments' : 'Residential inventory', 'help' => 'Generate units from specifications, then check each unit number, floor and availability. Duplicate or missing numbers cannot be confirmed.', 'data' => $res->map(fn ($u) => \Illuminate\Support\Arr::except($u->toArray(), self::HOUSE_COLUMNS))->all(), 'valid' => $res->isNotEmpty() && $res->every(fn ($u) => filled($u->unit_number) && $u->floor !== null) && $numbersUnique, 'url' => $base.'#residential_units'];
        }
        if ($houses) {
            $steps['houses'] = ['title' => 'Houses', 'help' => 'Add each house type, then give every house its number or plot. For a single house, add one house. Duplicate or missing numbers cannot be confirmed.', 'data' => $homes->map(fn ($u) => \Illuminate\Support\Arr::except($u->toArray(), ['offer', 'sale_price', 'floor']))->all(), 'valid' => $homes->isNotEmpty() && $homes->every(fn ($u) => filled($u->unit_number)) && $numbersUnique, 'url' => $base.'#houses'];
        }
        if ($commercial) {
            $steps['commercial'] = ['title' => 'Commercial inventory', 'help' => 'Enter each space manually. Check its identifier, floor, area, minimum leasable area and subdivision capacity before review.', 'data' => $com->toArray(), 'valid' => $com->isNotEmpty() && $com->every(fn ($u) => filled($u->unit_number) && filled($u->effective_floor) && $u->unit_sq_ft > 0 && $u->min_sq_ft > 0 && $u->min_sq_ft <= $u->unit_sq_ft && $u->max_subdivisions >= 0) && $com->map(fn ($u) => mb_strtolower(trim($u->unit_number ?? '')))->unique()->count() === $com->count(), 'url' => route('commercial-units.create', ['complex_id' => $complex->id])];
        }
        $steps['facilities'] = ['title' => 'Amenities & parking', 'help' => 'Record shared amenities and individually identifiable parking slots. If none apply, record that decision rather than leaving an unexplained gap.', 'data' => ['amenities' => $complex->amenities->toArray(), 'parking' => $complex->parkingSlots->toArray()], 'valid' => true, 'optional' => true, 'url' => $base.'#amenities'];
        $steps['charges'] = ['title' => 'Service charges', 'help' => 'Review recurring tenant charges separately from property operating expenses. State when no recurring shared charges apply.', 'data' => $complex->serviceCharges->toArray(), 'valid' => true, 'optional' => true, 'url' => route('complex.service-charges', ['complex_id' => $complex->id])];
        $steps['documents'] = ['title' => 'Floor plans & supporting records', 'help' => 'Upload available floor plans and check supporting property links. Record why these are deferred if they are not available yet.', 'data' => ['floor_plans' => ComplexFloorPlan::where('complex_id', $complex->id)->orderBy('id')->get()->toArray(), 'links' => $complex->links->toArray()], 'valid' => true, 'optional' => true, 'url' => url('/plans_complex/'.$complex->slug)];
        $reviews = $complex->changes()->whereLike('field_name', 'setup_review:%')->with('user')->orderByDesc('id')->get()->unique('field_name')->keyBy('field_name');
        foreach ($steps as $key => &$step) {
            $step['key'] = $key;
            $step['fingerprint'] = hash('sha256', json_encode($this->reviewData($step['data'])));
            $review = $reviews->get('setup_review:'.$key);
            $saved = $review ? json_decode($review->new_value, true) : null;
            $step['confirmed'] = $step['valid'] && ($saved['fingerprint'] ?? null) === $step['fingerprint'];
            $step['review'] = $review;
            $step['decision'] = $saved['decision'] ?? null;
            $step['note'] = $saved['note'] ?? null;
            $step['state'] = $step['confirmed'] ? 'Reviewed' : ($review ? 'Changed — review again' : ($step['valid'] ? 'Awaiting review' : 'Needs information'));
        }
        return $steps;
    }

    public function ready(Complex $complex): bool
    {
        return collect($this->steps($complex))->every(fn ($step) => $step['confirmed']);
    }

    private function reviewData(array $data, bool $nested = false): array
    {
        // Occupancy, timestamps and agent assignment change during ordinary operations, not setup.
        foreach ($data as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at', 'status', 'is_occupied'], true) || ($nested && $key === 'assigned_agent_id')) {
                unset($data[$key]);
            } elseif ($key === 'assigned_agent_id' || $key === 'occupied_tenant_id') {
                // Kept as null so reviews confirmed earlier still match. A parking slot given to a tenant is
                // occupancy (like is_occupied), not a setup change, and must not block further onboarding.
                $data[$key] = null;
            } elseif (is_array($value)) {
                $data[$key] = $this->reviewData($value, true);
            }
        }
        return $data;
    }
}
