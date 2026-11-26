<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\CommercialUnit;
use App\Models\Complex;
use App\Models\ComplexAmenity;
use App\Models\ComplexParkingSlot;
use App\Models\ComplexResidentialUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Small lookups used by forms across the app: a property's facilities, parking, units and commercial spaces, and property search. */
class PropertyLookupController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get Complex Amenities
     *
     * @param  string  $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getComplexAmenities($slug)
    {
        try {
            $ownerId = business_owner_id();
            $complex = Complex::forBusiness($ownerId)
                ->where(fn ($q) => ctype_digit((string) $slug) ? $q->where('id', (int) $slug) : $q->where('slug', $slug))
                ->first();
            if (!$complex) {
                return response()->json(['success' => false, 'message' => 'Property not found.'], 404);
            }
            // Parking only by default (parking assignment); ?all=1 lists every facility (service charges).
            $amenities = ComplexAmenity::where('complex_id', $complex->id)
                ->when(!request()->boolean('all'), fn ($q) => $q->where('amenity_type', 'parking'))
                ->orderBy('amenity_type')->orderBy('id')
                ->get(['id', 'amenity_type', 'parking_slots', 'currency', 'annual_price']);

            return response()->json([
                'success' => true,
                'amenities' => $amenities
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching complex amenities: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Unable to fetch amenities at this time.'
            ], 500);
        }
    }
    /**
     * Get Available Parking Slots
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getParkingSlots($id)
    {
        try {
            // Fetch parking slots for the given amenity ID
            $complex = Complex::forBusiness()
                ->where(fn ($q) => ctype_digit((string) $id) ? $q->where('id', (int) $id) : $q->where('slug', $id))
                ->firstOrFail();
            $parkingSlots = ComplexParkingSlot::where('complex_id', $complex->id)
                ->where('is_occupied', false)
                ->get(['id', 'parking_id']);

            return response()->json([
                'success' => true,
                'parking_slots' => $parkingSlots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch parking slots.'
            ], 500);
        }
    }
    /**
     * Get available residential units for tenant onboarding
     *
     * @param  string  $complexId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResidentialUnitsForTenant($complexId)
    {
        $complex = Complex::where('slug', $complexId)->where('user_id', business_owner_id())->firstOrFail();
        $inventory = ComplexResidentialUnit::where('complex_id', $complex->id);
        $hasInventory = (clone $inventory)->exists();
        $assignment = app(\App\Services\Agents\AgentAssignmentService::class);
        // Houses offered for sale only are not let.
        $units = $inventory->where('status', 'available')->whereDoesntHave('activeTenancies')
            ->where(fn ($q) => $q->where('unit_category', '!=', 'house')->orWhereNull('offer')->orWhereIn('offer', ['rent', 'both']))
            ->with(['complexUnit', 'assignedAgent'])->orderBy('unit_number')->get()->map(function ($unit) use ($assignment, $complex) {
                $agent = $assignment->defaultAgentFor($complex, $unit);
                return [
                    'agent' => $agent ? ['id' => $agent->id, 'name' => $agent->full_name] : null,
                    'id' => $unit->id,
                    'unit_number' => $unit->unit_number,
                    'bedrooms' => $unit->bedrooms ?? $unit->complexUnit?->bedrooms,
                    'bathrooms' => $unit->bathrooms ?? $unit->complexUnit?->bathrooms,
                    'sq_ft' => $unit->sq_ft ?? $unit->complexUnit?->sq_ft,
                    'floor' => $unit->floor,
                    'status' => $unit->status,
                    'category' => $unit->unit_category,
                    'house_type' => $unit->house_type,
                    'label' => $unit->label() . ($unit->isHouse() ? ' · ' . $unit->layoutLabel() : ''),
                ];
            });
        return response()->json(['success' => true, 'has_inventory' => $hasInventory, 'units' => $units]);
    }
    /**
     * Commercial spaces of one of this business's properties (lead form). Limited roles only see
     * properties assigned to them (Complex is restricted to assigned properties).
     */
    public function commercialSpaces(int $complex)
    {
        $complex = Complex::forBusiness()->findOrFail($complex);
        $spaces = $complex->commercialUnits()
            ->orderBy('unit_number')
            ->get(['id', 'complex_id', 'unit_number', 'unit_sq_ft', 'min_sq_ft', 'max_subdivisions', 'floors', 'custom_floors', 'status'])
            ->each(fn ($unit) => $unit->effective_floor = $unit->custom_floors ?: $unit->floors);

        return response()->json(['status' => 200, 'complex' => ['id' => $complex->id, 'title' => $complex->title, 'commercial_units' => $spaces]]);
    }
    /**
     * Search complexes for Select2 dropdown
     */
    public function searchComplexes(Request $request)
    {
        try {
            $userId =  business_owner_id();
            $searchTerm = $request->get('q', '');
            $listingType = $request->get('listing_type', ''); // Get listing type filter
            $page = $request->get('page', 1);
            $perPage = 10;
            
            $query = Complex::with('assignedAgent')->where('user_id', $userId);
                
            // Apply search filter
            if (!empty($searchTerm)) {
                $query->where(function($q) use ($searchTerm) {
                    $like = '%' . mb_strtolower($searchTerm) . '%';
                    $q->whereRaw('LOWER(title) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(ref_code) LIKE ?', [$like])
                      ->orWhereRaw('LOWER(location_description) LIKE ?', [$like]);
                });
            }
            
            // Apply listing type filter
            if (!empty($listingType)) {
                $query->whereLike('type', "%{$listingType}%");
            }

            // Get total count for pagination
            $total = $query->count();
            
            $page = max(1, (int) $page);
            $complexes = $query->orderBy('title', 'asc')
                ->skip(($page - 1) * $perPage)
                ->take($perPage)
                ->get();
                
            // Format results for Select2
            $results = $complexes->map(function($complex) {
                return [
                    'id' => $complex->slug,
                    'text' => $complex->title . ' (' . $complex->ref_code . ')',
                    'title' => $complex->title,
                    'ref_code' => $complex->ref_code,
                    'type' => $complex->type,
                    'commercial_unit' => str_contains($complex->type, 'Commercial Unit') ? 'true' : 'false',
                    'location' => $complex->location_description,
                    'assigned_agent' => $complex->assignedAgent ? [
                        'id' => $complex->assignedAgent->id,
                        'name' => $complex->assignedAgent->first_name . ' ' . $complex->assignedAgent->last_name,
                        'email' => $complex->assignedAgent->email,
                    ] : null,
                ];
            });
            
            return response()->json([
                'results' => $results,
                'pagination' => [
                    'more' => ($page * $perPage) < $total
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to search complexes: ' . $e->getMessage(), [
                'user_id' => business_owner_id(),
                'search_term' => $request->get('q'),
                'listing_type' => $request->get('listing_type'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'results' => [],
                'pagination' => ['more' => false],
                'error' => 'Failed to load complexes'
            ]);
        }
    }

    /** Units of a property for the form's unit picker (apartments and houses together, labelled). */
    public function getComplexUnits($slug)
    {
        $complex = Complex::where('slug', $slug)->where('user_id', business_owner_id())->first();
        if (!$complex) {
            return response()->json(['residential_units' => [], 'commercial_units' => []]);
        }
        $residential = ComplexResidentialUnit::where('complex_id', $complex->id)->orderBy('unit_number')->orderBy('id')->get()
            ->map(fn ($u) => ['id' => $u->id, 'unit_number' => $u->unit_number, 'label' => $u->label() . ($u->isHouse() ? ' · ' . $u->layoutLabel() : '')]);
        $commercial = CommercialUnit::where('complex_id', $complex->id)->orderBy('unit_number')->get(['id', 'unit_number'])
            ->map(fn ($u) => ['id' => $u->id, 'unit_number' => $u->unit_number, 'label' => 'Space ' . $u->unit_number]);
        return response()->json(['residential_units' => $residential, 'commercial_units' => $commercial]);
    }

    /** Property search for the history form (slug, title, county, type), paginated for the select box. */
    /**
     * Search complexes for the DataTables AJAX request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchForHistory(Request $request)
    {
        $user_id = business_owner_id();
        $search = $request->get('q', '');
        $page = $request->get('page', 1);
        $perPage = 20;
        
        $query = Complex::forBusiness($user_id)
            ->select('slug', 'title', 'county', 'type');
        
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->whereLike('title', '%' . $search . '%')
                  ->orWhereLike('county', '%' . $search . '%')
                  ->orWhereLike('type', '%' . $search . '%');
            });
        }
        
        $total = $query->count();
        $complexes = $query->offset(($page - 1) * $perPage)
                         ->limit($perPage)
                         ->orderBy('title')
                         ->get();
        
        $results = $complexes->map(function($complex) {
            return [
                'id' => $complex->slug,
                'text' => $complex->title,
                'subtitle' => $complex->county . ' - ' . $complex->type
            ];
        });
        
        return response()->json([
            'results' => $results,
            'pagination' => [
                'more' => ($page * $perPage) < $total
            ]
        ]);
    }
}
