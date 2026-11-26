<?php

namespace App\Services\Listings;

use App\Services\Access\PropertyAccess;
use App\Services\Properties\ComplexInventoryService;

use App\Models\{CommercialUnit, Complex, ComplexResidentialUnit, ComplexUnit, Listing};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Listings per unit type.
 *
 * Every confirmed property has one listing per residential unit type (complex_units row) and one per
 * commercial space. sync() creates the missing drafts and keeps their unit details (title, rooms,
 * size, number of units) in step with the property; it never touches what staff entered (rent,
 * deposit, description, photos, status). Removing a unit type or space removes its listing (foreign key).
 *
 * Availability is counted live: residential units of the type whose status is "available" and that have
 * no active or pending tenancy; a commercial space counts as one while it still has room for a tenancy.
 */
class UnitListingService
{
    /** Tenancy statuses that hold a unit. */
    public const HOLDING_TENANCY = ['Active', 'Pending'];

    public function __construct(private ComplexInventoryService $inventory, private PropertyAccess $access) {}

    /** Create or refresh the drafts of one property. Returns the number of listings created. */
    public function sync(Complex $complex): int
    {
        if ($complex->setup_status !== 'ready') {
            return 0;
        }

        return $this->access->unrestricted(function () use ($complex) {
            $created = 0;

            foreach (ComplexUnit::where('complex_id', $complex->id)->orderBy('id')->get() as $type) {
                $house = $type->isHouse();
                $created += $this->upsert($complex, ['complex_unit_id' => $type->id], [
                    'name' => Listing::titleFor($type),
                    'category' => 'Residential',
                    'type' => $house ? 'House' : 'Apartment',
                    'bedrooms' => (string) (int) $type->bedrooms,
                    'bathrooms' => (int) $type->bathrooms,
                    'sq_area' => $type->sq_ft !== null ? (int) round((float) $type->sq_ft) : null,
                    'units' => (int) $type->unit_count,
                    // A house type is offered for rent, sale or both; its asking price starts from the specification.
                    'offer' => $house ? ($type->offer ?: 'rent') : 'rent',
                ], $house && $type->sale_price ? ['sale_price' => $type->sale_price] : []);
            }

            foreach (CommercialUnit::where('complex_id', $complex->id)->orderBy('id')->get() as $space) {
                $created += $this->upsert($complex, ['commercial_unit_id' => $space->id], [
                    'name' => Listing::titleFor(null, $space),
                    'category' => 'Commercial',
                    'type' => 'Commercial Unit',
                    'bedrooms' => null,
                    'bathrooms' => null,
                    'sq_area' => (int) round((float) $space->unit_sq_ft),
                    'units' => 1,
                ], ['amount' => $space->monthly_rent !== null ? (int) round((float) $space->monthly_rent) : null]);
            }

            return $created;
        });
    }

    /** Sync every confirmed property of a business (cheap: one upsert per unit type). */
    public function syncOwner(int $ownerId): int
    {
        $complexes = $this->access->unrestricted(fn () => Complex::forBusiness($ownerId)->where('setup_status', 'ready')->get());
        return $complexes->sum(fn (Complex $complex) => $this->sync($complex));
    }

    private function upsert(Complex $complex, array $key, array $details, array $initial = []): int
    {
        $listing = Listing::withoutGlobalScopes()->where($key)->first();
        if ($listing) {
            $listing->fill($details + ['complex_id' => $complex->id, 'user_id' => $complex->user_id]);
            if ($listing->isDirty()) {
                $listing->save();
            }
            return 0;
        }

        $ref = $this->newRef();
        Listing::create($key + $details + $initial + [
            'complex_id' => $complex->id,
            'user_id' => $complex->user_id,
            'ref' => $ref,
            'slug' => Str::slug($complex->title . ' ' . $details['name']) . '-' . strtolower($ref),
            'status' => Listing::DRAFT,
            'currency' => default_currency(),
        ]);
        return 1;
    }

    private function newRef(): string
    {
        do {
            $ref = 'L' . strtoupper(Str::random(8));
        } while (Listing::withoutGlobalScopes()->where('ref', $ref)->exists());
        return $ref;
    }

    /**
     * Units available per listing id, for any number of listings (two queries plus one per commercial space).
     * With $forViewer, only units the signed-in member can see are counted (their units on unit-only properties).
     *
     * @param  iterable<Listing>  $listings
     * @return array<int, int>
     */
    public function availability(iterable $listings, bool $forViewer = false): array
    {
        $listings = collect($listings);
        $counts = $listings->mapWithKeys(fn (Listing $l) => [$l->id => 0])->all();
        // Staff pages count only the units the signed-in member can see; renters and the public API see all free units.
        $run = $forViewer ? fn (callable $cb) => $cb() : fn (callable $cb) => $this->access->unrestricted($cb);

        return $run(function () use ($listings, $counts) {
            $byType = $listings->whereNotNull('complex_unit_id')->keyBy('complex_unit_id');
            if ($byType->isNotEmpty()) {
                $free = ComplexResidentialUnit::query()
                    ->whereIn('complex_unit_id', $byType->keys())
                    ->where('status', 'available')
                    ->whereNotExists(fn ($q) => $q->from('tenant_management')
                        ->whereColumn('tenant_management.residential_unit_id', 'complex_residential_units.id')
                        ->whereIn('tenant_management.status', self::HOLDING_TENANCY))
                    ->groupBy('complex_unit_id')
                    ->select('complex_unit_id', DB::raw('count(*) as free'))
                    ->pluck('free', 'complex_unit_id');
                foreach ($free as $typeId => $n) {
                    $counts[$byType[$typeId]->id] = (int) $n;
                }
            }

            $bySpace = $listings->whereNotNull('commercial_unit_id')->keyBy('commercial_unit_id');
            if ($bySpace->isNotEmpty()) {
                foreach (CommercialUnit::whereIn('id', $bySpace->keys())->get() as $space) {
                    $counts[$bySpace[$space->id]->id] = $this->inventory->commercialOccupancy($space)['has_space'] ? 1 : 0;
                }
            }

            return $counts;
        });
    }

    /**
     * Limit a listings query to those with at least one unit free right now (same rules as availability()),
     * so public results can be paginated in SQL.
     */
    public function whereAvailable($query)
    {
        $holding = "'" . implode("','", self::HOLDING_TENANCY) . "'";

        return $query->where(function ($q) use ($holding) {
            $q->whereExists(fn ($s) => $s->from('complex_residential_units as cru')
                ->whereColumn('cru.complex_unit_id', 'listings.complex_unit_id')
                ->where('cru.status', 'available')
                ->whereRaw("not exists (select 1 from tenant_management t where t.residential_unit_id = cru.id and t.status in ($holding))"))
            ->orWhereExists(fn ($s) => $s->from('commercial_units as cu')
                ->whereColumn('cu.id', 'listings.commercial_unit_id')
                ->whereIn('cu.status', ['Available', 'Occupied'])
                // Same test as ComplexInventoryService::commercialOccupancy()['has_space'].
                ->whereRaw("(select count(*) from tenant_management t where t.commercial_unit_id = cu.id and t.status = 'Active') < (case when coalesce(cu.max_subdivisions, 1) < 1 then 1 else coalesce(cu.max_subdivisions, 1) end)")
                ->whereRaw("cu.unit_sq_ft - coalesce((select sum(coalesce(t.occupied_sq_area, t.sq_area, 0)) from tenant_management t where t.commercial_unit_id = cu.id and t.status = 'Active'), 0) >= coalesce(cu.min_sq_ft, 0)")
                ->whereRaw("cu.unit_sq_ft - coalesce((select sum(coalesce(t.occupied_sq_area, t.sq_area, 0)) from tenant_management t where t.commercial_unit_id = cu.id and t.status = 'Active'), 0) > 0"));
        });
    }

    /**
     * Free houses of each listing split by what they are offered for: [listing id => ['rent' => n, 'sale' => n]].
     * A house "for rent or sale" counts on both sides. Apartments are always for rent.
     */
    public function offerCounts(iterable $listings): array
    {
        $byType = collect($listings)->whereNotNull('complex_unit_id')->keyBy('complex_unit_id');
        if ($byType->isEmpty()) return [];
        $rows = $this->access->unrestricted(fn () => ComplexResidentialUnit::query()->whereIn('complex_unit_id', $byType->keys())->where('status', 'available')
            ->whereNotExists(fn ($q) => $q->from('tenant_management')->whereColumn('tenant_management.residential_unit_id', 'complex_residential_units.id')
                ->whereIn('tenant_management.status', self::HOLDING_TENANCY))
            ->groupBy('complex_unit_id', 'unit_category', 'offer')->select('complex_unit_id', 'unit_category', 'offer', DB::raw('count(*) as n'))->get());
        $counts = [];
        foreach ($rows as $row) {
            $id = $byType[$row->complex_unit_id]->id;
            $counts[$id] ??= ['rent' => 0, 'sale' => 0];
            $offer = $row->unit_category === 'house' ? ($row->offer ?: 'rent') : 'rent';
            if (in_array($offer, ['rent', 'both'], true)) $counts[$id]['rent'] += (int) $row->n;
            if (in_array($offer, ['sale', 'both'], true)) $counts[$id]['sale'] += (int) $row->n;
        }
        return $counts;
    }

    public function availableUnits(Listing $listing): int
    {
        return $this->availability([$listing])[$listing->id] ?? 0;
    }

    /** Publish after checking the listing is complete. */
    public function publish(Listing $listing): void
    {
        if ($missing = $listing->missingForPublishing()) {
            throw ValidationException::withMessages(['listing' => 'Add ' . $this->sentence($missing) . ' before publishing.']);
        }
        if ($listing->isPublished()) {
            return;
        }
        $listing->forceFill(['status' => Listing::PUBLISHED, 'published_at' => $listing->published_at ?? now()])->save();
    }

    public function unpublish(Listing $listing): void
    {
        if ($listing->isPublished()) {
            $listing->forceFill(['status' => Listing::UNPUBLISHED])->save();
        }
    }

    private function sentence(array $items): string
    {
        return count($items) > 1 ? implode(', ', array_slice($items, 0, -1)) . ' and ' . end($items) : $items[0];
    }
}
