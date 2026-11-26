<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\ComplexExpense;
use App\Models\ComplexLink;
use App\Models\CommercialUnit;
use App\Models\ComplexResidentialUnit;
use App\Models\ImageUpload;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;



class ComplexController extends Controller
{

    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $user_id = business_owner_id();
            
            // Improved query that filters by user_id on complexes table
            $portfolio = Complex::select('complexes.*')
                ->leftJoinSub(\App\Models\ComplexExpense::query()->select('id', 'complex_id', 'amount', 'user_id'), 'complex_expenses', 'complexes.id', '=', 'complex_expenses.complex_id')
                ->where(function($query) use ($user_id) {
                    $query->where('complexes.user_id', $user_id)
                        ->orWhere('complex_expenses.user_id', $user_id);
                })
                ->addSelect(DB::raw('SUM(COALESCE(complex_expenses.amount, 0)) as total_expenses'))
                ->groupBy('complexes.id');

            // toBase() keeps the assigned-property limit (getQuery() would skip it).
            $portfolioTotal = (clone $portfolio)->toBase()->getCountForPagination();
            $portfolioReady = (clone $portfolio)->where('complexes.setup_status', 'ready')->toBase()->getCountForPagination();
            $query = clone $portfolio;
            $search = mb_substr(trim((string) $request->query('q', '')), 0, 200);
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    foreach (['title', 'ref_code', 'county', 'county_specific'] as $column) {
                        $q->orWhereRaw('LOWER(complexes.'.$column.') LIKE ?', ['%'.mb_strtolower($search).'%']);
                    }
                });
            }
            if ($request->query('setup') === 'ready') {
                $query->where('complexes.setup_status', 'ready');
            } elseif ($request->query('setup') === 'review') {
                $query->where(fn ($q) => $q->whereNull('complexes.setup_status')->orWhere('complexes.setup_status', '!=', 'ready'));
            }
            $composition = $request->query('composition');
            $kinds = \App\Services\Properties\ComplexInventoryService::class;
            match ($composition) {
                // residential: apartments and/or houses, no commercial; houses: any property with houses
                'residential' => $kinds::whereKind($query->where(fn ($r) => $kinds::whereKind($r, 'apartments')->orWhere(fn ($h) => $kinds::whereKind($h, 'houses'))), 'commercial', true),
                'houses' => $kinds::whereKind($query, 'houses'),
                'commercial' => $kinds::whereKind($kinds::whereKind($kinds::whereKind($query, 'commercial'), 'apartments', true), 'houses', true),
                'combined' => $kinds::whereKind($query, 'commercial')->where(fn ($r) => $kinds::whereKind($r, 'apartments')->orWhere(fn ($h) => $kinds::whereKind($h, 'houses'))),
                default => null,
            };
            // "My properties": the ones assigned to the signed-in team member.
            $hasAssignments = DB::table('complex_user')->where('user_id', auth()->id())->exists();
            if ($hasAssignments && $request->query('managed') === 'mine') {
                $query->whereIn('complexes.id', DB::table('complex_user')->select('complex_id')->where('user_id', auth()->id()));
            }
            $sort = in_array($request->query('sort'), ['name', 'oldest'], true) ? $request->query('sort') : 'updated';
            $query->orderBy($sort === 'name' ? 'complexes.title' : 'complexes.updated_at', $sort === 'updated' ? 'desc' : 'asc')->orderBy('complexes.id');
            $complexes = $query->with(['managers:users.id,first_name,last_name', 'assignedAgent:id,first_name,last_name'])->withCount([
                'complex_residential_units as residential_count',
                'complex_residential_units as house_count' => fn ($q) => $q->where('unit_category', 'house'),
                'commercialUnits as commercial_count',
                'complex_residential_units as residential_available' => fn ($q) => $q->where('status', 'available'),
                'commercialUnits as commercial_available' => fn ($q) => $q->where('status', 'Available'),
            ])->paginate(25)->withQueryString();

            // How the signed-in agent relates to each listed property: primary manager, or sub-agent of some units.
            $myAgent = \App\Services\Agents\AgentProfileService::current();
            $myUnits = [];
            if ($myAgent) {
                $ids = $complexes->pluck('id');
                foreach (['complex_residential_units', 'commercial_units'] as $table) {
                    foreach (DB::table($table)->whereIn('complex_id', $ids)->where('assigned_agent_id', $myAgent->id)->selectRaw('complex_id, count(*) as n')->groupBy('complex_id')->get() as $row) {
                        $myUnits[$row->complex_id] = ($myUnits[$row->complex_id] ?? 0) + (int) $row->n;
                    }
                }
            }

            $property_types = cache()->remember('property_types', now()->addHour(), function() {
                return DB::table('property_types')->where('type', '3')->get();
            });
            
            return view('pages.business.complex.index', compact(
                'property_types', 
                'complexes', 
                'portfolioTotal', 'portfolioReady', 'hasAssignments', 'myAgent', 'myUnits'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading complexes: ' . $e->getMessage());
            // return redirect()->back()
            //     ->with('error', 'There was a problem loading the complexes. Please try again.');
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $user_id = business_owner_id();
        $property_types = DB::table('property_types')->where('type', '3')->get();
        //  cache()->remember('property_types', now()->addHour(), function() {
        //     return DB::table('property_types')->where('type', '3')->get();
        // });

        $complexes_count = Complex::forBusiness($user_id)->count();
        // Team & responsibility: who manages the new property and its responsible agent.
        $canAssignManagers = auth()->user()->hasBusinessPermission('complex_management', 'assign');
        $assignableMembers = $canAssignManagers ? app(\App\Services\Agents\PropertyAssignmentService::class)->assignableMembers() : collect();

        return view('pages.business.complex.draft', compact('canAssignManagers', 'assignableMembers'));
    }

    /**
     * Return stats for a batch of complex IDs (used by the complex listing page).
     * Accepts ?ids[]=1&ids[]=2 — only returns stats for complexes owned by the authenticated user.
     * Handles residential-only, commercial-only, and hybrid complexes.
     */
    public function getComplexesStats(Request $request)
    {
        try {
            $userId  = business_owner_id();
            $ids     = array_filter(array_map('intval', (array) $request->get('ids', [])));

            if (empty($ids)) {
                return response()->json([]);
            }

            // Verify all requested IDs belong to this user (security)
            $ownedIds = Complex::forBusiness($userId)->whereIn('id', $ids)->pluck('id')->toArray();

            // Residential unit counts per complex
            $residentialRaw = ComplexResidentialUnit::selectRaw(
                    'complex_id,
                     COUNT(*) as total,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as occupied,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as available',
                    ['occupied', 'available']
                )
                ->whereIn('complex_id', $ownedIds)
                ->groupBy('complex_id')
                ->get()
                ->keyBy('complex_id');


            // Commercial unit counts per complex (4 distinct statuses)
            $commercialRaw = CommercialUnit::selectRaw(
                    'complex_id,
                     COUNT(*) as total,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as occupied,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as available,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as under_maintenance,
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as reserved',
                    ['Occupied', 'Available', 'Under Maintenance', 'Reserved']
                )
                ->whereIn('complex_id', $ownedIds)
                ->groupBy('complex_id')
                ->get()
                ->keyBy('complex_id');


            // Listing counts per complex
            $listingCounts = Listing::selectRaw('complex_id, COUNT(*) as total')
                ->where('status', Listing::PUBLISHED)
                ->whereIn('complex_id', $ownedIds)
                ->groupBy('complex_id')
                ->pluck('total', 'complex_id');

            // Expense totals per complex
            $expenseTotals = ComplexExpense::selectRaw('complex_id, SUM(amount) as total')
                ->whereIn('complex_id', $ownedIds)
                ->groupBy('complex_id')
                ->pluck('total', 'complex_id');


            $stats = [];
            foreach ($ownedIds as $id) {
                $res = $residentialRaw[$id] ?? null;
                $com = $commercialRaw[$id]  ?? null;

                $resTotal     = (int) ($res->total    ?? 0);
                $resOccupied  = (int) ($res->occupied  ?? 0);
                $resAvailable = (int) ($res->available ?? 0);

                $comTotal        = (int) ($com->total             ?? 0);
                $comOccupied     = (int) ($com->occupied          ?? 0);
                $comAvailable    = (int) ($com->available         ?? 0);
                $comMaintenance  = (int) ($com->under_maintenance ?? 0);
                $comReserved     = (int) ($com->reserved          ?? 0);

                $stats[$id] = [
                    // Residential
                    'residential_total'     => $resTotal,
                    'residential_occupied'  => $resOccupied,
                    'residential_available' => $resAvailable,
                    // Commercial (all 4 statuses)
                    'commercial_total'             => $comTotal,
                    'commercial_occupied'          => $comOccupied,
                    'commercial_available'         => $comAvailable,
                    'commercial_under_maintenance' => $comMaintenance,
                    'commercial_reserved'          => $comReserved,
                    // Combined totals
                    'total_units'     => $resTotal + $comTotal,
                    'occupied_units'  => $resOccupied + $comOccupied,
                    'available_units' => $resAvailable + $comAvailable,
                    // Other
                    'total_listings'  => (int)   ($listingCounts[$id] ?? 0),
                    'total_expenses'  => (float) ($expenseTotals[$id] ?? 0),
                ];
            }

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('Failed to load complex stats: ' . $e->getMessage());
            return response()->json([], 500);
        }
    }

    /**
     * Show the setup checklist for a complex before it becomes operational.
     */
    public function setup(Request $request, $slug)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'view'), 403);
        $ownerId = business_owner_id();
        $complex = Complex::with([
            'complex_units',
            'complex_residential_units',
            'commercialUnits',
            'amenities',
        ])->where('slug', $slug)->where('user_id', $ownerId)->firstOrFail();
        // The setup review covers the whole inventory: not for someone who only holds some of its units.
        abort_if(isset(app(\App\Services\Access\PropertyAccess::class)->unitScope()[$complex->id]), 403, 'The setup review is for people who manage the whole property.');

        $steps = app(\App\Services\Properties\ComplexSetupService::class)->steps($complex);
        $activeStep = $request->query('step', collect($steps)->first(fn ($step) => !$step['confirmed'])['key'] ?? 'finish');
        abort_unless($activeStep === 'finish' || isset($steps[$activeStep]), 404);
        $inventoryData = in_array($activeStep, ['residential', 'houses', 'commercial'], true) ? $steps[$activeStep]['data'] : [];
        $page = max(1, (int) $request->query('page', 1));
        if ($request->filled('unit') && $inventoryData) {
            $position = array_search((int) $request->query('unit'), array_column($inventoryData, 'id'), true);
            if ($position !== false) $page = intdiv($position, 25) + 1;
        }
        $inventory = new \Illuminate\Pagination\LengthAwarePaginator(array_slice($inventoryData, ($page - 1) * 25, 25), count($inventoryData), 25, $page, ['path' => $request->url(), 'query' => ['step' => $activeStep]]);
        if (in_array($activeStep, ['residential', 'houses'], true) && $request->filled('unit')) {
            $unit = collect($inventoryData)->firstWhere('id', (int) $request->query('unit'));
            abort_unless($unit, 404);
            $step = $steps[$activeStep];
            // The fingerprint leaves out what a house is offered for; the form still shows it.
            $record = \App\Models\ComplexResidentialUnit::find($unit['id']);
            $liveSale = null;
            if ($record->isHouse()) {
                $unit += $record->only(['offer', 'sale_price']);
                $liveSale = $record->liveSale;
            }
            $canEdit = app(\App\Services\Access\BusinessPermissionService::class)->can($request->user(), 'complex_management', 'edit');
            $hasActiveTenancy = \App\Models\ComplexResidentialUnit::whereKey($unit['id'])->whereHas('activeTenancies')->exists();
            return view('pages.business.complex.refine-residential', compact('complex', 'unit', 'step', 'canEdit', 'hasActiveTenancy', 'activeStep', 'liveSale'));
        }
        $canEdit = app(\App\Services\Access\BusinessPermissionService::class)->can($request->user(), 'complex_management', 'edit');
        return view('pages.business.complex.journey', compact('complex', 'steps', 'activeStep', 'inventory', 'canEdit'));
    }

    /**
     * Confirm that the complex has passed the required setup checks.
     */
    /**
     * Who manages a property: company or primary manager, each agent with their units, unassigned units and
     * recent changes. Counts follow what the viewer may see (unit-only members see their own units).
     */
    private function assignmentSummary(Complex $complex): array
    {
        $agents = [];
        $unassigned = [];
        foreach (['residential' => $complex->complex_residential_units(), 'commercial' => $complex->commercialUnits()] as $kind => $relation) {
            $unassigned[$kind] = (clone $relation)->whereNull('assigned_agent_id')->count();
            foreach ((clone $relation)->whereNotNull('assigned_agent_id')->selectRaw('assigned_agent_id, count(*) as total')->groupBy('assigned_agent_id')->pluck('total', 'assigned_agent_id') as $agentId => $total) {
                $agents[$agentId][$kind] = (int) $total;
            }
        }
        if ($complex->assigned_agent_id && !$complex->isCompanyManaged()) {
            $agents[$complex->assigned_agent_id] ??= [];
        }
        $people = \App\Models\Agent::whereIn('id', array_keys($agents))->get(['id', 'first_name', 'last_name', 'user_id', 'is_active'])->keyBy('id');
        $rows = collect($agents)->map(fn ($units, $agentId) => [
            'agent' => $people->get($agentId),
            'role' => !$complex->isCompanyManaged() && (int) $complex->assigned_agent_id === (int) $agentId ? 'primary' : 'sub_agent',
            'residential' => $units['residential'] ?? 0, 'commercial' => $units['commercial'] ?? 0,
        ])->filter(fn ($r) => $r['agent'])->sortBy(fn ($r) => ($r['role'] === 'primary' ? '0' : '1') . $r['agent']->first_name)->values();

        // Someone who only holds units here sees property-level changes and those of their own units.
        $unitOnly = app(\App\Services\Access\PropertyAccess::class)->unitScope()[$complex->id] ?? null;
        $history = \App\Models\AssignmentHistory::with(['agent:id,first_name,last_name', 'assignedBy:id,first_name,last_name'])
            ->where('complex_id', $complex->id)
            ->when($unitOnly, fn ($q) => $q->where(fn ($w) => $w->where('scope', 'property')
                ->orWhere(fn ($r) => $r->where('scope', 'residential_unit')->whereIn('unit_id', $unitOnly['residential'] ?: [0]))
                ->orWhere(fn ($c) => $c->where('scope', 'commercial_unit')->whereIn('unit_id', $unitOnly['commercial'] ?: [0]))))
            ->latest('starts_at')->latest('id')->limit(12)->get();
        $unitNumbers = [
            'residential_unit' => $complex->complex_residential_units()->whereIn('id', $history->where('scope', 'residential_unit')->pluck('unit_id'))->pluck('unit_number', 'id'),
            'commercial_unit' => $complex->commercialUnits()->whereIn('id', $history->where('scope', 'commercial_unit')->pluck('unit_id'))->pluck('unit_number', 'id'),
        ];
        $assignments = app(\App\Services\Agents\AgentAssignmentService::class);

        return [
            'company' => $complex->isCompanyManaged(),
            'primary' => $complex->isCompanyManaged() ? null : $complex->assignedAgent,
            'agents' => $rows,
            'unassigned' => $unassigned,
            'history' => $history,
            'unit_numbers' => $unitNumbers,
            'can_set_management' => $assignments->isAdmin(auth()->user()),
            'business_name' => auth()->user()->business->business_name ?? config('app.name'),
        ];
    }

    public function confirmSetup(Request $request, $slug)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit'), 403);
        $request->validate(['review_acknowledged' => ['accepted']]);
        $ownerId = business_owner_id();
        return \Illuminate\Support\Facades\DB::transaction(function () use ($slug, $ownerId, $request) {
        $complex = Complex::with([
            'complex_units',
            'complex_residential_units',
            'commercialUnits',
            'amenities',
        ])->where('slug', $slug)->where('user_id', $ownerId)->lockForUpdate()->firstOrFail();

        $setupChecklist = app(\App\Services\Access\PropertyAccess::class)->unrestricted(fn () => $this->getSetupChecklist((clone $complex)->setRelations([])));

        if (!$setupChecklist['ready'] || !app(\App\Services\Properties\ComplexSetupService::class)->ready($complex)) {
            if (!$request->expectsJson()) return back()->withErrors(['setup' => 'Complete the required setup reviews before confirming this property.']);
            return response()->json([
                'success' => false,
                'message' => 'Complete the required setup steps before confirming this complex.',
                'checklist' => $setupChecklist,
            ], 422);
        }

        $complex->update([
            'setup_status' => 'ready',
            'setup_confirmed_at' => now(),
            'setup_confirmed_by' => auth()->id(),
        ]);

        // A draft listing for every unit type and commercial space (kept even while the Listings module is off).
        app(\App\Services\Listings\UnitListingService::class)->sync($complex);

        $confirmedMessage = app(\App\Services\Access\ModuleService::class)->enabled('listings')
            ? 'Property setup confirmed. It is now ready for listings and tenant operations.'
            : 'Property setup confirmed. It is now ready for tenant operations.';
        if (!$request->expectsJson()) return redirect()->route('complex.show', $complex->slug)->with('success', $confirmedMessage);

        // Shown if the page falls back to a full load instead of an in-place refresh.
        session()->flash('success', $confirmedMessage);
        return response()->json([
            'success' => true,
            'message' => $confirmedMessage,
            'redirect' => route('complex.show', $complex->slug),
        ]);
        });
    }

    private function getSetupChecklist(Complex $complex): array
    {
        $hasResidential = \App\Services\Properties\ComplexInventoryService::hasResidential($complex);
        $hasCommercial = \App\Services\Properties\ComplexInventoryService::hasCommercial($complex);

        $checks = [
            [
                'key' => 'identity',
                'label' => 'Property identity and location confirmed',
                'detail' => trim(collect([
                    $complex->title,
                    $complex->county,
                    $complex->location_description,
                ])->filter()->implode(' · ')),
                'complete' => filled($complex->title)
                    && filled($complex->type)
                    && filled($complex->county)
                    && filled($complex->location_description),
                'url' => route('complex.show', $complex->slug).'#settings',
            ],
            [
                'key' => 'structure',
                'label' => 'Apartments, houses and/or commercial spaces selected',
                'detail' => $complex->type ?: 'No property composition selected',
                'complete' => $hasResidential || $hasCommercial,
                'url' => route('complex.show', $complex->slug).'#settings',
            ],
            [
                'key' => 'residential_units',
                'label' => 'Apartments and houses generated and manually reviewed',
                'detail' => $hasResidential
                    ? sprintf('%d generated unit(s); %d with unit numbers', $complex->complex_residential_units->count(), $complex->complex_residential_units->filter(fn ($unit) => filled($unit->unit_number))->count())
                    : 'Not required for this property structure',
                'complete' => !$hasResidential
                    || ($complex->complex_residential_units->isNotEmpty()
                        && $complex->complex_residential_units->every(fn ($unit) => filled($unit->unit_number))),
                'url' => route('complex.show', $complex->slug) . '#residential_units',
                'optional' => !$hasResidential,
            ],
            [
                'key' => 'commercial_units',
                'label' => 'Commercial units created and reviewed',
                'detail' => $hasCommercial
                    ? sprintf('%d commercial unit(s) recorded', $complex->commercialUnits->count())
                    : 'Not required for this property structure',
                'complete' => !$hasCommercial || $complex->commercialUnits->isNotEmpty(),
                'url' => route('commercial-units.index', ['complex_id' => $complex->id]),
                'optional' => !$hasCommercial,
            ],
        ];

        return [
            'status' => $complex->setup_status ?? 'draft',
            'ready' => collect($checks)->every(fn ($check) => $check['complete']),
            'checks' => $checks,
        ];
    }


    /**
     * Display the specified resource.
     *
     * @param  \App\Complex  $complex
     * @return \Illuminate\Http\Response
     */
    public function show($slug)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'view'), 403);
        $ownerId = business_owner_id();
        $complex = Complex::with([
            'complex_units',
            'complex_residential_units',
            'commercialUnits',
            'amenities',
            'assignedAgent',
        ])->where('slug', $slug)->where('user_id', $ownerId)->firstOrFail();
        $inventories = [];
        // Apartments and houses share the residential table; each gets its own tab and filters.
        foreach (['residential', 'houses', 'commercial'] as $kind) {
            if ($kind === 'houses' && !\App\Services\Properties\ComplexInventoryService::hasHouses($complex)) continue;
            $query = match ($kind) {
                'residential' => $complex->complex_residential_units()->where('unit_category', '!=', 'house'),
                'houses' => $complex->complex_residential_units()->where('unit_category', 'house')->with('liveSale'),
                default => $complex->commercialUnits(),
            };
            $totals = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $search = trim((string) request($kind.'_q', ''));
            $status = (string) request($kind.'_status', '');
            if ($search !== '') $query->whereRaw('LOWER(unit_number) LIKE ?', ['%'.mb_strtolower($search).'%']);
            if ($status !== '') $query->where('status', $status);
            // Agent filter: "none" = units nobody is assigned to, or one agent's units.
            $agentFilter = (string) request($kind.'_agent', '');
            if ($agentFilter === 'none') $query->whereNull('assigned_agent_id');
            elseif (ctype_digit($agentFilter)) $query->where('assigned_agent_id', (int) $agentFilter);
            $query->with($kind !== 'commercial' ? ['tenantManagement.tenantProfile', 'assignedAgent'] : ['activeTenants.tenantProfile', 'assignedAgent']);
            if ($kind !== 'commercial') $query->with(['tenantManagement' => fn ($tenancy) => $tenancy->where('status', 'Active')->with('tenantProfile')]);
            $units = $query->orderBy('unit_number')->orderBy('id')->paginate(25, ['*'], $kind.'_page')->withQueryString()->fragment($kind === 'houses' ? 'houses' : $kind.'_units');
            $inventories[$kind] = compact('units', 'totals', 'search', 'status');
        }
        $setupSteps = app(\App\Services\Properties\ComplexSetupService::class)->steps($complex);
        $inventoryReady = $complex->setup_status === 'ready' && collect($setupSteps)->every(fn ($step) => $step['confirmed']);
        $canEditInventory = app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit');
        // Unit-type listings of this property (one per unit type and commercial space) with live availability.
        $listings = Listing::with('photos')->where('complex_id', $complex->id)
            ->orderByRaw('commercial_unit_id is not null')->orderBy('id')->get();
        $listingAvailability = app(\App\Services\Listings\UnitListingService::class)->availability($listings, true);
                
        $complexAmenities = $complex->amenities->sortBy('amenity_type')->values();


        $property_types = DB::table('property_types')->where('type', '3')->get();

        $user_id = business_owner_id();
        $images = ImageUpload::where([['user_id', $user_id], ['type', 'Head']])->get();

        $links = ComplexLink::where('complex_id', $complex->id)->get();

        $managers = $complex->managers()->orderBy('first_name')->get(['users.id', 'first_name', 'last_name', 'email']);
        $canAssignManagers = auth()->user()->hasBusinessPermission('complex_management', 'assign');
        $assignableMembers = $canAssignManagers ? app(\App\Services\Agents\PropertyAssignmentService::class)->assignableMembers() : collect();
        // Unit agents: administrators, or this property's lead agent (for themselves and agents who report to them).
        $canAssignUnitAgents = app(\App\Services\Agents\AgentAssignmentService::class)->canAssignUnits(auth()->user(), $complex);
        $assignment = $this->assignmentSummary($complex);

       return view('pages.business.complex.show', compact('complex', 'property_types', 'listings', 'listingAvailability', 'images', 'links', 'complexAmenities', 'inventories', 'inventoryReady', 'canEditInventory', 'setupSteps', 'managers', 'canAssignManagers', 'assignableMembers', 'canAssignUnitAgents', 'assignment'));

    }
}
