<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicListingResource;
use App\Models\Listing;
use App\Services\Access\ModuleService;
use App\Services\Listings\UnitListingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Public, read-only listings for a renter-facing site or app.
 *
 * GET /api/v1/listings            published listings with at least one unit free (paginated, filterable)
 * GET /api/v1/listings/{ref}      one of them; 404 when it is not published or nothing is free
 *
 * Shown only when: the listing is published; its property's setup is confirmed; its business has the
 * Listings module on and is not suspended or closing; and at least one unit is free right now.
 */
class ListingController extends Controller
{
    public function __construct(private UnitListingService $listings, private ModuleService $modules) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'category' => ['nullable', 'in:residential,commercial'],
            'county' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'min_rent' => ['nullable', 'integer', 'min:0'],
            'max_rent' => ['nullable', 'integer', 'min:0'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:newest,rent_asc,rent_desc,price_asc,price_desc'],
            'offer' => ['nullable', 'in:rent,sale'],
            'property_type' => ['nullable', 'in:apartment,house,commercial'],
            'house_type' => ['nullable', 'string', 'max:40'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:' . config('listings.api.max_per_page', 50)],
        ]);

        $query = $this->visible()
            ->when($filters['category'] ?? null, fn ($q, $c) => $c === 'commercial' ? $q->whereNotNull('listings.commercial_unit_id') : $q->whereNotNull('listings.complex_unit_id'))
            ->when($filters['county'] ?? null, fn ($q, $v) => $q->whereHas('complex', fn ($c) => $c->whereRaw('lower(county) = ?', [mb_strtolower($v)])))
            ->when($filters['area'] ?? null, fn ($q, $v) => $q->whereHas('complex', fn ($c) => $c->whereRaw('lower(county_specific) like ?', ['%' . mb_strtolower($v) . '%'])))
            ->when(isset($filters['bedrooms']), fn ($q) => $q->whereHas('complexUnit', fn ($u) => $u->where('bedrooms', (int) $filters['bedrooms'])))
            ->when(isset($filters['min_bedrooms']), fn ($q) => $q->whereHas('complexUnit', fn ($u) => $u->where('bedrooms', '>=', (int) $filters['min_bedrooms'])))
            ->when(isset($filters['min_rent']), fn ($q) => $q->where('listings.amount', '>=', (int) $filters['min_rent']))
            ->when(isset($filters['max_rent']), fn ($q) => $q->where('listings.amount', '<=', (int) $filters['max_rent']))
            // Houses can be for rent, for sale or both; apartments and commercial spaces are for rent.
            ->when($filters['offer'] ?? null, fn ($q, $o) => $o === 'sale'
                ? $q->whereIn('listings.offer', ['sale', 'both'])
                : $q->where(fn ($w) => $w->whereNull('listings.offer')->orWhereIn('listings.offer', ['rent', 'both'])))
            ->when($filters['property_type'] ?? null, fn ($q, $t) => match ($t) {
                'commercial' => $q->whereNotNull('listings.commercial_unit_id'),
                'house' => $q->whereHas('complexUnit', fn ($u) => $u->withoutGlobalScopes()->where('unit_category', 'house')),
                default => $q->whereHas('complexUnit', fn ($u) => $u->withoutGlobalScopes()->where('unit_category', 'apartment')),
            })
            ->when($filters['house_type'] ?? null, fn ($q, $h) => $q->whereHas('complexUnit', fn ($u) => $u->withoutGlobalScopes()->where('unit_category', 'house')->whereRaw('lower(unit_type) = ?', [mb_strtolower($h)])))
            ->when(isset($filters['min_price']), fn ($q) => $q->where('listings.sale_price', '>=', (float) $filters['min_price']))
            ->when(isset($filters['max_price']), fn ($q) => $q->where('listings.sale_price', '<=', (float) $filters['max_price']))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->where(fn ($w) => $w->whereRaw('lower(listings.name) like ?', [$like])
                    ->orWhereHas('complex', fn ($c) => $c->whereRaw('lower(title) like ? or lower(county_specific) like ? or lower(county) like ?', [$like, $like, $like])));
            });

        match ($filters['sort'] ?? 'newest') {
            'rent_asc' => $query->orderBy('listings.amount')->orderBy('listings.id'),
            'rent_desc' => $query->orderByDesc('listings.amount')->orderBy('listings.id'),
            'price_asc' => $query->orderBy('listings.sale_price')->orderBy('listings.id'),
            'price_desc' => $query->orderByDesc('listings.sale_price')->orderBy('listings.id'),
            default => $query->orderByDesc('listings.published_at')->orderByDesc('listings.id'),
        };

        $page = $query->paginate($filters['per_page'] ?? config('listings.api.per_page', 20))->withQueryString();
        $free = $this->listings->availability($page->getCollection());
        $offers = $this->listings->offerCounts($page->getCollection());

        return response()->json($this->paginated($page, $free, $offers));
    }

    public function show(string $ref)
    {
        $listing = $this->visible()->where('listings.ref', $ref)->first();
        abort_unless($listing, 404, 'Listing not found.');

        return (new PublicListingResource($listing, $this->listings->availableUnits($listing), $this->listings->offerCounts([$listing])[$listing->id] ?? null, $this->salesShown($listing)))->response();
    }

    /** Published, confirmed, from an active business with the module on, with a unit free. */
    private function visible(): Builder
    {
        abort_unless($this->modules->installationEnabled(ModuleService::LISTINGS), 404);

        $query = Listing::withoutGlobalScopes()
            ->select('listings.*')
            ->published()
            ->onMarketplace()
            ->whereHas('complex', fn ($c) => $c->withoutGlobalScopes()->where('setup_status', 'ready'))
            ->whereHas('user', fn ($u) => $u->where('is_suspended', false)->where(fn ($d) => $d->whereNull('delete_account')->orWhere('delete_account', false)))
            ->with([
                'complex' => fn ($c) => $c->withoutGlobalScopes()->select('id', 'title', 'county', 'county_specific', 'location_description', 'latitude', 'longitude'),
                'complex.amenities' => fn ($a) => $a->withoutGlobalScopes()->select('id', 'complex_id', 'amenity_type'),
                'complexUnit' => fn ($u) => $u->withoutGlobalScopes(),
                'commercialUnit' => fn ($u) => $u->withoutGlobalScopes(),
                'photos',
            ]);

        // With the Sales module off (for the installation or the business), houses for sale only are not listed.
        $salesOff = $this->modules->installationEnabled(ModuleService::SALES) ? $this->modules->ownersWithModuleOff(ModuleService::SALES) : null;
        if ($salesOff === null || $salesOff) {
            $query->where(fn ($q) => $q->whereNull('listings.offer')->orWhere('listings.offer', '!=', 'sale')
                ->when($salesOff, fn ($w) => $w->orWhereNotIn('listings.user_id', $salesOff)));
        }

        return $this->listings->whereAvailable($query);
    }

    private function salesShown(Listing $listing): bool
    {
        $modules = app(ModuleService::class); // per request (scoped), so business switches are current
        return $modules->installationEnabled(ModuleService::SALES)
            && $modules->businessOverride((int) $listing->user_id, ModuleService::SALES) !== false;
    }

    /** Laravel's paginated resource shape (data, links, meta) with each item's free-unit count. */
    private function paginated($page, array $free, array $offers = []): array
    {
        $request = request();
        return [
            'data' => $page->getCollection()->map(fn ($l) => (new PublicListingResource($l, $free[$l->id] ?? 0, $offers[$l->id] ?? null, $this->salesShown($l)))->toArray($request))->values()->all(),
            'links' => [
                'first' => $page->url(1),
                'last' => $page->url($page->lastPage()),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ];
    }
}
