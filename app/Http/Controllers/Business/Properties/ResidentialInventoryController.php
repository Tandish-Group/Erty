<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\Complex;
use App\Models\ComplexResidentialUnit;
use App\Services\Access\BusinessPermissionService;
use Illuminate\Http\Request;

class ResidentialInventoryController extends Controller
{
    public function index(Request $request)
    {
        $permissions = app(BusinessPermissionService::class);
        abort_unless($permissions->can($request->user(), 'complex_management', 'view'), 403);
        $filters = $request->validate([
            'q' => 'nullable|string|max:200',
            'complex_id' => 'nullable|integer',
            'status' => 'nullable|in:available,occupied,reserved,under maintenance,maintenance,under_offer,sold',
            'category' => 'nullable|in:apartment,house',
            'offer' => 'nullable|in:rent,sale,both',
        ]);
        $owner = business_owner_id($request->user());
        $properties = Complex::forBusiness($owner)
            ->where(fn ($query) => \App\Services\Properties\ComplexInventoryService::whereKind($query, 'apartments', false, 'type')
                ->orWhere(fn ($h) => \App\Services\Properties\ComplexInventoryService::whereKind($h, 'houses', false, 'type'))->orWhereHas('complex_residential_units'))
            ->orderBy('title')->get(['id', 'title', 'slug']);
        $selectedProperty = $request->filled('complex_id') ? $properties->firstWhere('id', $filters['complex_id']) : null;
        abort_if($request->filled('complex_id') && ! $selectedProperty, 404);
        $query = ComplexResidentialUnit::whereHas('complex', fn ($property) => $property->where('user_id', $owner));
        if ($selectedProperty) $query->where('complex_id', $selectedProperty->id);
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $query->where(fn ($units) => $units->whereRaw('LOWER(unit_number) LIKE ?', ['%'.mb_strtolower($search).'%'])
                ->orWhereHas('complex', fn ($property) => $property->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($search).'%'])));
        }
        if ($request->filled('status')) $query->whereRaw('LOWER(status) = ?', [$filters['status']]);
        // Apartments or houses (they share this table), and what a house is offered for.
        if ($request->filled('category')) $query->where($filters['category'] === 'house' ? fn ($q) => $q->where('unit_category', 'house') : fn ($q) => $q->where('unit_category', '!=', 'house'));
        if ($request->filled('offer')) $query->where('unit_category', 'house')->where('offer', $filters['offer']);
        $totals = (clone $query)->reorder()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')
            ->mapWithKeys(fn ($n, $status) => [strtolower((string) $status) => $n])->all();
        // How many houses are in scope (decides whether house columns show). Older schemas have no category column.
        $houses = \Illuminate\Support\Facades\Schema::hasColumn('complex_residential_units', 'unit_category')
            ? ComplexResidentialUnit::whereHas('complex', fn ($property) => $property->where('user_id', $owner))->when($selectedProperty, fn ($q) => $q->where('complex_id', $selectedProperty->id))->where('unit_category', 'house')->count()
            : 0;
        $units = $query->with(array_merge(['complex', 'activeTenancies.tenantProfile'], $houses ? ['liveSale'] : []))
            ->orderBy('complex_id')->orderBy('unit_number')->orderBy('id')->paginate(25)->withQueryString();
        $canEdit = $permissions->can($request->user(), 'complex_management', 'edit');
        return view('pages.business.complex.residential-inventory', compact('units', 'properties', 'selectedProperty', 'search', 'canEdit', 'totals', 'houses'));
    }
}
