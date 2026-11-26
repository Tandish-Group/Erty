<?php

namespace App\Http\Controllers\Business\Properties;

use App\Models\Complex;
use App\Models\ComplexUnit;
use App\Models\CommercialUnit;
use App\Services\Properties\ComplexInventoryService;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class CommercialUnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'view'), 403);
        $ownerId = business_owner_id();
        $complexes = Complex::forBusiness($ownerId)->whereLike('type', '%Commercial Unit%')->orderBy('title')->get(['id', 'title', 'slug']);
        $selectedProperty = $request->filled('complex_id') ? $complexes->firstWhere('id', $request->input('complex_id')) : null;
        abort_if($request->filled('complex_id') && !$selectedProperty, 404);
        $query = CommercialUnit::whereHas('complex', fn ($q) => $q->where('user_id', $ownerId));
        if ($selectedProperty) $query->where('complex_id', $selectedProperty->id);
        $totals = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        // Floor area across the spaces shown (before search): total, let to active tenancies, and free.
        $areaTotal = (float) (clone $query)->sum('unit_sq_ft');
        $areaLet = (float) \App\Models\TenantManagement::where('status', 'Active')->whereIn('commercial_unit_id', (clone $query)->select('id'))
            ->sum(DB::raw('COALESCE(occupied_sq_area, sq_area, 0)'));
        $area = ['total' => $areaTotal, 'let' => min($areaLet, $areaTotal), 'free' => max(0, $areaTotal - $areaLet)];
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') $query->where(function ($q) use ($search) {
            $q->whereRaw('LOWER(unit_number) LIKE ?', ['%'.mb_strtolower($search).'%'])
                ->orWhereHas('complex', fn ($property) => $property->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%']));
        });
        if ($request->filled('status')) $query->where('status', $request->input('status'));
        $units = $query->with(['complex', 'activeTenants.tenantProfile'])->orderBy('complex_id')->orderBy('unit_number')->orderBy('id')->paginate(25)->withQueryString();
        $canEdit = app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit');
        return view('pages.business.complex.commercial_units.index', compact('units', 'complexes', 'selectedProperty', 'totals', 'search', 'canEdit', 'area'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit'), 403);
        $user_id = business_owner_id();
        $complexes = Complex::forBusiness($user_id)->whereLike('type', '%Commercial Unit%')->get();
        $setupComplex = request()->filled('complex_id') ? $complexes->firstWhere('id', request('complex_id')) : null;
        abort_if(request()->filled('complex_id') && !$setupComplex, 404);
        
        return view('pages.business.complex.commercial_units.create', compact('complexes', 'setupComplex'));
    }

    /**
     * Store commercial spaces for a property (one validated batch, all-or-nothing).
     */
    public function store(Request $request)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit'), 403);
        $complex = Complex::forBusiness()->findOrFail($request->input('complex_id'));
        abort_unless(ComplexInventoryService::hasCommercial($complex), 422, 'This property has no commercial inventory.');
        $rows = $request->input('kt_commercial_units_repeater');
        if (!is_array($rows) || !$rows) {
            return response()->json(['success' => false, 'message' => 'Add at least one commercial space.', 'errors' => ['kt_commercial_units_repeater' => ['Add at least one commercial space.']]], 422);
        }

        $inventory = app(ComplexInventoryService::class);
        return DB::transaction(function () use ($complex, $rows, $inventory) {
            $complex = Complex::whereKey($complex->id)->lockForUpdate()->firstOrFail();
            [$errors, $clean] = $inventory->validateCommercialRows($complex, $rows);
            if ($errors) {
                $keyed = collect($errors)->mapWithKeys(fn ($message, $key) => ['kt_commercial_units_repeater.' . $key => [$message]])->all();
                return response()->json(['success' => false, 'message' => 'Check the highlighted inventory information.', 'errors' => $keyed], 422);
            }
            $inventory->createCommercial($complex, $clean);

            return response()->json([
                'success' => true,
                'message' => 'Commercial units added successfully',
                'units_count' => count($clean),
                'redirect' => route('complex.setup', [$complex->slug, 'step' => 'commercial']),
            ]);
        });
    }

    /** Template for the commercial space import, generated so it always matches the importer. */
    public function downloadUnitSample()
    {
        $path = app(ComplexInventoryService::class)->template(
            ['complex_ref_code', 'unit_number', 'unit_sq_ft', 'min_sq_ft', 'max_subdivisions', 'floors', 'monthly_rent', 'status', 'description'],
            ['MRB-001', 'G-01', 1200, 300, 4, 'Ground Floor', 85000, 'Available', 'Corner shop facing the road'],
            [
                'One row per commercial space. complex_ref_code must match one of your commercial or mixed-use properties.',
                'unit_number must be unique within its property. min_sq_ft cannot exceed unit_sq_ft.',
                'max_subdivisions: 0-20 (how many tenants can share the space). floors: e.g. Ground Floor, 1st Floor.',
                'status: Available, Under Maintenance or Reserved (Occupied is set by tenant onboarding).',
                'Nothing is imported unless every row is valid. Imported spaces return the property to setup review.',
            ]
        );
        return response()->download($path, 'erty-commercial-spaces-template.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Import commercial spaces from a spreadsheet. Rows are matched to the business's own
     * commercial/mixed-use properties by reference and validated like manual entry; all-or-nothing.
     */
    public function unit_upload(Request $request)
    {
        $permissions = app(\App\Services\Access\BusinessPermissionService::class);
        abort_unless($permissions->can(auth()->user(), 'complex_management', 'edit') && $permissions->can(auth()->user(), 'complex_management', 'import'), 403);

        $file = collect($request->allFiles())->flatten()->first();
        if (!$file || !in_array(strtolower($file->getClientOriginalExtension()), ['xls', 'xlsx', 'csv'], true)) {
            return response()->json(['success' => false, 'message' => 'Upload the Excel template (.xlsx, .xls or .csv).'], 422);
        }

        $inventory = app(ComplexInventoryService::class);
        $rows = $inventory->readSheet($file->getRealPath());
        if (!$rows) {
            return response()->json(['success' => false, 'message' => 'The file has no commercial space rows.'], 422);
        }

        $properties = Complex::forBusiness()->get()->filter(fn ($c) => ComplexInventoryService::hasCommercial($c))->keyBy(fn ($c) => mb_strtolower(trim((string) $c->ref_code)));

        return DB::transaction(function () use ($rows, $properties, $inventory) {
            $errors = [];
            $batches = [];
            $lines = [];
            foreach ($rows as $line => $row) {
                $property = $properties->get(mb_strtolower(trim((string) ($row['complex_ref_code'] ?? ''))));
                if (!$property) {
                    $errors[] = 'Row ' . $line . ': complex_ref_code does not match one of your commercial or mixed-use properties.';
                    continue;
                }
                $row['status'] = $row['status'] ?? 'Available';
                $batches[$property->id][] = $row;
                $lines[$property->id][] = $line;
            }
            foreach ($batches as $propertyId => $propertyRows) {
                $property = Complex::whereKey($propertyId)->lockForUpdate()->firstOrFail();
                [$rowErrors, $clean] = $inventory->validateCommercialRows($property, $propertyRows);
                foreach ($rowErrors as $key => $message) {
                    [$index] = explode('.', $key);
                    $errors[] = 'Row ' . $lines[$propertyId][(int) $index] . ': ' . $message;
                }
                $batches[$propertyId] = [$property, $clean];
            }
            if ($errors) {
                return response()->json(['success' => false, 'message' => 'No spaces were imported. ' . implode(' ', array_slice($errors, 0, 10)), 'errors' => $errors], 422);
            }

            $count = 0;
            foreach ($batches as [$property, $clean]) {
                $inventory->createCommercial($property, $clean);
                $count += count($clean);
            }

            return response()->json(['success' => true, 'count' => $count, 'data' => [], 'message' => $count . ' commercial spaces imported. Review commercial inventory in property setup.']);
        });
    }

    /**
     * Get complex information with units for breakdown display
     */
    public function getComplexInfo($id)
    {
        try {
            $complex = Complex::where('id', $id)
                ->where('user_id', business_owner_id())
                ->first();
            
            if (!$complex) {
                return response()->json([
                    'success' => false,
                    'message' => 'Complex not found'
                ], 404);
            }
            
            // Get unit specifications for this complex from ComplexUnit
            $complexUnits = ComplexUnit::where('complex_id', $id)
                ->select('id', 'unit_category', 'unit_type', 'bedrooms', 'bathrooms', 'unit_subdivision', 'unit_count', 'sq_ft')
                ->get()
                ->map(function($unit) {
                    // Format the unit display based on category
                    if ($unit->unit_category === 'apartment') {
                        $unit->display_name = $unit->bedrooms . ' Bedroom' . ($unit->bedrooms > 1 ? 's' : '') . 
                                            ', ' . $unit->bathrooms . ' Bathroom' . ($unit->bathrooms > 1 ? 's' : '');
                        $unit->unit_type_display = 'Apartment';
                    } elseif ($unit->unit_category === 'house') {
                        $unit->display_name = $unit->layoutLabel() . ', ' . $unit->bathrooms . ' Bathroom' . ($unit->bathrooms > 1 ? 's' : '');
                        $unit->unit_type_display = 'House';
                    } else {
                        $unit->display_name = $unit->unit_type . 
                                            ($unit->unit_subdivision ? ' - ' . $unit->unit_subdivision : '');
                        $unit->unit_type_display = 'Commercial Unit';
                    }
                    return $unit;
                });
            
            // Get count of actual commercial units created
            $createdCommercialUnits = CommercialUnit::where('complex_id', $id)->count();
            
            return response()->json([
                'success' => true,
                'complex' => [
                    'id' => $complex->id,
                    'title' => $complex->title,
                    'ref_code' => $complex->ref_code,
                    'county' => $complex->county,
                    'sub_county' => $complex->sub_county,
                    'type' => $complex->type,
                    'unit_specifications' => $complexUnits,
                    'created_units_count' => $createdCommercialUnits,
                    'total_planned_units' => $complexUnits->sum('unit_count')
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching complex info: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching complex information'
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit'), 403);
        try {
            $id = decrypt($id);
        } catch (\Exception $e) {
            abort(404, 'Invalid commercial unit.');
        }
        
        $unit = CommercialUnit::with('complex')->findOrFail($id);

        // Verify that the unit belongs to the current user's complex
        if ((int) $unit->complex->user_id !== business_owner_id()) {
            abort(403, 'Unauthorized access to this commercial unit.');
        }
        
        return view('pages.business.complex.commercial_units.edit', compact('unit'));
    }

    /**
     * Update one commercial space. Uses the same validation as creation, and cannot shrink the
     * space below what active tenancies occupy. Changes are audited and return the property to setup review.
     */
    public function update(Request $request, string $id)
    {
        abort_unless(app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'complex_management', 'edit'), 403);
        try {
            $id = decrypt($id);
        } catch (\Exception $e) {
            abort(404, 'Invalid commercial unit.');
        }

        $inventory = app(ComplexInventoryService::class);
        $result = DB::transaction(function () use ($request, $id, $inventory) {
            $unit = CommercialUnit::lockForUpdate()->findOrFail($id);
            $complex = Complex::forBusiness()->whereKey($unit->complex_id)->lockForUpdate()->first();
            abort_unless($complex, 403, 'Unauthorized access to this commercial unit.');

            // Fields the form does not send keep their stored values (an edit never clears data it did not show).
            $fields = ['unit_number', 'unit_sq_ft', 'min_sq_ft', 'max_subdivisions', 'floors', 'custom_floors', 'monthly_rent', 'status', 'description'];
            $row = array_merge($unit->only($fields), $request->only(array_filter($fields, fn ($field) => $request->exists($field))));
            [$errors, $clean] = $inventory->validateCommercialRows($complex, [$row], $unit->id);
            if ($errors) {
                return ['errors' => collect($errors)->mapWithKeys(fn ($message, $key) => [explode('.', $key, 2)[1] => $message])->all()];
            }

            $fields = array_keys($clean[0]);
            $before = $unit->only($fields);
            $unit->fill($clean[0]);
            if ($unit->isDirty()) {
                $unit->save();
                $inventory->invalidate($complex, 'commercial_unit:' . $unit->id, $before, $unit->only($fields));
            }
            return ['complex' => $complex];
        });

        if (isset($result['errors'])) {
            return redirect()->back()->withErrors($result['errors'])->withInput();
        }

        return redirect()->to(route('complex.show', $result['complex']->slug) . '#commercial_units')
            ->with('success', 'Commercial unit updated. Review commercial inventory in setup before leasing.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
