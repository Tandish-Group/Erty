<?php

namespace App\Http\Controllers\Business\Listings;

use App\Http\Controllers\Controller;
use App\Models\{Complex, ImageUpload, Listing};
use App\Services\Listings\UnitListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\ValidationException;

/**
 * Business side of the Listings module: one listing per unit type (and per commercial space).
 * Drafts come from UnitListingService; here staff fill in rent, deposit, description and photos,
 * then publish or unpublish. Access is checked by business.access (listings_management) and the
 * Listings module middleware; listings of other businesses or unassigned properties are not found.
 */
class UnitListingController extends Controller
{
    public const MAX_PHOTOS = 12;
    public const FURNISHING = ['Unfurnished', 'Semi-furnished', 'Furnished'];

    public function __construct(private UnitListingService $listings) {}

    public function index(Request $request)
    {
        $ownerId = business_owner_id();
        // Self-healing: confirmed properties always have their drafts, even ones confirmed before this feature.
        $this->listings->syncOwner($ownerId);

        $status = in_array($request->query('status'), [Listing::DRAFT, Listing::PUBLISHED, Listing::UNPUBLISHED], true) ? $request->query('status') : null;
        $complexId = $request->integer('complex_id') ?: null;

        $all = Listing::with(['complex:id,title,slug', 'complexUnit', 'commercialUnit', 'photos'])
            ->where('user_id', $ownerId)
            ->orderBy('complex_id')->orderByRaw('commercial_unit_id is not null')->orderBy('id')
            ->get();
        $availability = $this->listings->availability($all, true);

        $counts = [
            'all' => $all->count(),
            Listing::PUBLISHED => $all->where('status', Listing::PUBLISHED)->count(),
            Listing::DRAFT => $all->where('status', Listing::DRAFT)->count(),
            Listing::UNPUBLISHED => $all->where('status', Listing::UNPUBLISHED)->count(),
            'available_units' => $all->where('status', Listing::PUBLISHED)->sum(fn ($l) => $availability[$l->id] ?? 0),
        ];

        $listings = $all
            ->when($status, fn ($c) => $c->where('status', $status))
            ->when($complexId, fn ($c) => $c->where('complex_id', $complexId))
            ->groupBy('complex_id');
        $complexes = $all->pluck('complex')->filter()->unique('id')->sortBy('title')->values();
        $canEdit = auth()->user()->hasBusinessPermission('listings_management', 'edit');
        $canPublish = auth()->user()->hasBusinessPermission('listings_management', 'publish');

        return view('pages.business.listings.index', compact('listings', 'availability', 'counts', 'complexes', 'status', 'complexId', 'canEdit', 'canPublish'));
    }

    public function edit(int $listing)
    {
        $listing = $this->find($listing)->load(['complex', 'complexUnit', 'commercialUnit', 'photos']);

        return view('pages.business.listings.edit', [
            'listing' => $listing,
            'available' => $this->listings->availableUnits($listing),
            'furnishing' => self::FURNISHING,
            'maxPhotos' => self::MAX_PHOTOS,
            'canPublish' => auth()->user()->hasBusinessPermission('listings_management', 'publish'),
        ]);
    }

    public function update(Request $request, int $listing)
    {
        $listing = $this->find($listing);
        $data = $request->validate([
            'amount' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'deposit' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'furnishing' => ['nullable', 'in:' . implode(',', self::FURNISHING)],
            'property_description' => ['nullable', 'string', 'max:5000'],
            'sale_price' => ['nullable', 'numeric', 'min:1', 'max:9999999999999'],
        ], [], ['amount' => 'monthly rent', 'property_description' => 'description', 'sale_price' => 'asking price']);
        // Only a house listing offered for sale has an asking price.
        if (!in_array($listing->offer, ['sale', 'both'], true)) unset($data['sale_price']);

        $listing->fill($data);
        if ($listing->isPublished() && ($missing = $listing->missingForPublishing())) {
            throw ValidationException::withMessages(['listing' => 'A published listing needs ' . implode(', ', $missing) . '. Unpublish it first to clear these.']);
        }
        $listing->save();

        return redirect()->route('listings.edit', $listing->id)->with('success', 'Listing saved.');
    }

    public function publish(int $listing)
    {
        $listing = $this->find($listing);
        $this->listings->publish($listing);
        $note = $this->listings->availableUnits($listing) > 0 ? '' : ' Renters will see it once a unit of this type is vacant.';
        return back()->with('success', 'Listing published.' . $note);
    }

    public function unpublish(int $listing)
    {
        $this->listings->unpublish($this->find($listing));
        return back()->with('success', 'Listing unpublished. Renters no longer see it.');
    }

    public function storePhoto(Request $request, int $listing)
    {
        $listing = $this->find($listing);
        $request->validate([
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ]);

        $room = self::MAX_PHOTOS - $listing->photos()->count();
        $files = array_slice($request->file('photos'), 0, max(0, $room));
        if (!$files) {
            throw ValidationException::withMessages(['photos' => 'A listing can have up to ' . self::MAX_PHOTOS . ' photos. Remove one first.']);
        }

        foreach ($files as $file) {
            $path = $file->store('users/' . $listing->user_id . '/listings/' . $listing->ref, 'public');
            ImageUpload::create(['image' => $path, 'type' => ImageUpload::LISTING_PHOTO, 'listing_id' => $listing->id, 'user_id' => $listing->user_id]);
        }

        $skipped = count($request->file('photos')) - count($files);
        return redirect()->route('listings.edit', $listing->id)->with('success', count($files) . ' photo' . (count($files) === 1 ? '' : 's') . ' added.' . ($skipped ? " {$skipped} not added: the limit is " . self::MAX_PHOTOS . '.' : ''));
    }

    public function destroyPhoto(int $listing, int $photo)
    {
        $listing = $this->find($listing);
        $image = $listing->photos()->whereKey($photo)->firstOrFail();
        DB::transaction(fn () => $image->delete());
        Storage::disk('public')->delete($image->image);

        return redirect()->route('listings.edit', $listing->id)->with('success', 'Photo removed.');
    }

    /** A listing of this business (and, for limited roles, of an assigned property). */
    private function find(int $id): Listing
    {
        return Listing::where('user_id', business_owner_id())->findOrFail($id);
    }
}
