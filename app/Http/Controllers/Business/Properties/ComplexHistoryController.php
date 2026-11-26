<?php

namespace App\Http\Controllers\Business\Properties;

use App\Http\Controllers\Controller;
use App\Models\{CommercialUnit, Complex, ComplexHistory, ComplexResidentialUnit, HistoryCategory};
use App\Support\PropertyHistory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

/**
 * Property history: a timeline of events at a property or one of its units (ownership, renovations, legal
 * matters, incidents…). Records are kept as written: a correction is a new record. What each category records
 * is defined in App\Support\PropertyHistory.
 */
class ComplexHistoryController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $action = in_array($request->route()->getActionMethod(), ['store', 'create'], true) ? 'create' : 'view';
            abort_unless(app(\App\Services\Access\BusinessPermissionService::class)
                ->can($request->user(), 'listing_history', $action), 403);
            return $next($request);
        });
    }

    private function canCreate(): bool
    {
        return app(\App\Services\Access\BusinessPermissionService::class)->can(auth()->user(), 'listing_history', 'create');
    }

    /** The timeline, newest event first, with filters. */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:200', 'complex_filter' => 'nullable|string|max:255', 'category' => 'nullable|string|max:40',
            'scope' => 'nullable|in:property,residential,commercial', 'from' => 'nullable|date', 'to' => 'nullable|date',
        ]);
        $complexes = Complex::forBusiness()->orderBy('title')->get();
        $selectedProperty = $request->filled('complex_filter') ? $complexes->firstWhere('slug', $request->complex_filter) : null;
        abort_if($request->filled('complex_filter') && !$selectedProperty, 404);
        $categories = HistoryCategory::active()->get()->keyBy('tag');

        $base = ComplexHistory::with(['complex', 'residentialUnit', 'commercialUnit', 'category', 'creator'])
            ->whereIn('complex_id', $complexes->pluck('id'))
            ->when($selectedProperty, fn ($q) => $q->where('complex_id', $selectedProperty->id));
        $scoped = (clone $base)->get();

        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $category = $filters['category'] ?? null;
        $from = isset($filters['from']) ? \Carbon\Carbon::parse($filters['from'])->startOfDay() : null;
        $to = isset($filters['to']) ? \Carbon\Carbon::parse($filters['to'])->endOfDay() : null;
        // Event dates live in each record's data, so filtering and ordering by them happens here.
        $records = $scoped
            ->when($category, fn ($c) => $c->where('category_tag', $category))
            ->when($filters['scope'] ?? null, fn ($c, $scope) => $c->filter(fn ($r) => match ($scope) {
                'residential' => (bool) $r->complex_residential_unit_id, 'commercial' => (bool) $r->commercial_unit_id,
                default => !$r->complex_residential_unit_id && !$r->commercial_unit_id,
            }))
            ->when($search !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', [
                PropertyHistory::title($r), $r->complex?->title, $r->unit_display, $r->notes, json_encode($r->data),
                trim(($r->creator->first_name ?? '') . ' ' . ($r->creator->last_name ?? '')),
            ])), $search)))
            ->map(fn ($r) => tap($r, fn ($r) => $r->setAttribute('event_date', PropertyHistory::eventDate($r) ?? $r->created_at)))
            ->when($from, fn ($c) => $c->filter(fn ($r) => $r->event_date->gte($from)))
            ->when($to, fn ($c) => $c->filter(fn ($r) => $r->event_date->lte($to)))
            ->sortByDesc(fn ($r) => $r->event_date->timestamp . '.' . str_pad((string) $r->id, 10, '0', STR_PAD_LEFT))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paged = new LengthAwarePaginator($records->forPage($page, 20)->values(), $records->count(), 20, $page, ['path' => $request->url(), 'query' => $request->query()]);

        $stats = [
            'total' => $scoped->count(),
            'properties' => $scoped->pluck('complex_id')->unique()->count(),
            'files' => $scoped->sum(fn ($r) => count(PropertyHistory::files($r))),
            'open_legal' => $scoped->where('category_tag', 'legal')->filter(fn ($r) => in_array($r->data['status'] ?? null, ['active', 'pending'], true))->count(),
            'latest' => $scoped->map(fn ($r) => PropertyHistory::eventDate($r) ?? $r->created_at)->max(),
        ];
        $counts = $scoped->countBy('category_tag');
        $canCreate = $this->canCreate();
        $filtered = $search !== '' || $category || ($filters['scope'] ?? null) || $from || $to;

        return view('pages.business.complex.history.index', [
            'complexes' => $complexes, 'categories' => $categories, 'selectedProperty' => $selectedProperty,
            'records' => $paged, 'stats' => $stats, 'counts' => $counts, 'canCreate' => $canCreate, 'filtered' => $filtered,
            'filters' => $filters + ['q' => '', 'category' => null, 'scope' => null, 'from' => null, 'to' => null],
        ]);
    }

    /** One record, with its facts, notes and files. */
    public function show(int $id)
    {
        $record = ComplexHistory::with(['complex', 'residentialUnit', 'commercialUnit', 'category', 'creator'])
            ->whereHas('complex', fn ($q) => $q->where('user_id', business_owner_id()))->findOrFail($id);
        $related = ComplexHistory::with('category')->where('complex_id', $record->complex_id)->where('id', '!=', $record->id)
            ->latest('id')->limit(5)->get();
        return view('pages.business.complex.history.show', ['record' => $record, 'related' => $related, 'canCreate' => $this->canCreate()]);
    }

    /** The add-record form. Can start from a property, unit and category (?complex=slug&scope=…&unit=…&category=…). */
    public function create(Request $request)
    {
        $complexes = Complex::forBusiness()->orderBy('title')->get(['id', 'title', 'slug', 'type']);
        $selected = $complexes->firstWhere('slug', old('complex', $request->query('complex')));
        return view('pages.business.complex.history.create', [
            'complexes' => $complexes, 'selected' => $selected,
            'categories' => HistoryCategory::active()->get()->keyBy('tag'),
            'preset' => ['category' => old('history_category', $request->query('category')), 'scope' => old('scope', $request->query('scope', 'property')), 'unit' => $request->query('unit')],
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'complex' => 'required|string',
            'history_category' => ['required', 'string', Rule::in(array_keys(PropertyHistory::categories()))],
            'scope' => 'required|in:property,residential,commercial',
            'confirmed' => 'required|accepted',
            'notes' => 'nullable|string|max:10000',
        ], ['confirmed.accepted' => 'Confirm the record is accurate: saved records can\'t be edited.'], ['history_category' => 'record type']);

        $complex = Complex::where('slug', $request->complex)->where('user_id', business_owner_id())->first();
        abort_unless($complex, 404, 'Property not found.');

        $request->validate([
            'residential_unit_id' => ['nullable', 'integer', $request->scope === 'residential' ? 'required' : 'prohibited',
                Rule::exists('complex_residential_units', 'id')->where('complex_id', $complex->id)],
            'commercial_unit_id' => ['nullable', 'integer', $request->scope === 'commercial' ? 'required' : 'prohibited',
                Rule::exists('commercial_units', 'id')->where('complex_id', $complex->id)],
        ], [], ['residential_unit_id' => 'apartment or house', 'commercial_unit_id' => 'commercial space']);

        $tag = $request->history_category;
        $category = HistoryCategory::active()->where('tag', $tag)->first();
        if (!$category) {
            throw \Illuminate\Validation\ValidationException::withMessages(['history_category' => 'This record type is not available.']);
        }
        $request->validate(PropertyHistory::rules($tag), [], collect(PropertyHistory::category($tag)['fields'])->mapWithKeys(fn ($f) => [$f[0] => mb_strtolower($f[2])])->all());

        // Only the selected record type's file field is read; every file field is checked.
        $fileRules = [];
        foreach (PropertyHistory::categories() as $definition) {
            $fileRules[$definition['files']] = 'nullable|array|max:10';
            $fileRules[$definition['files'] . '.*'] = 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,mp4';
        }
        $request->validate($fileRules);

        $filesField = PropertyHistory::category($tag)['files'];
        $files = $request->hasFile($filesField) ? [$filesField => $this->uploadFiles($request->file($filesField), 'history/' . $complex->slug . '/' . $tag)] : [];

        $record = ComplexHistory::create([
            'complex_id' => $complex->id,
            'complex_residential_unit_id' => $request->scope === 'residential' ? $request->residential_unit_id : null,
            'commercial_unit_id' => $request->scope === 'commercial' ? $request->commercial_unit_id : null,
            'history_category_id' => $category->id,
            'category_tag' => $tag,
            'data' => PropertyHistory::structure($tag, $request->all()),
            'files' => $files,
            'notes' => $request->notes,
            'created_by' => auth()->id(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'History record saved.', 'data' => $record]);
        }
        return redirect()->route('history.show', $record->id)->with('success', 'Record saved to ' . $complex->title . '\'s history.');
    }

    private function uploadFiles($files, $directory)
    {
        $uploaded = [];
        $businessName = \App\Models\User::findOrFail(business_owner_id())->business->business_name;
        foreach ($files as $file) {
            if ($file->isValid()) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $uploaded[] = [
                    'original_name' => $file->getClientOriginalName(), 'filename' => $filename,
                    'path' => $file->storeAs('users/' . $businessName . '/' . $directory, $filename, 'public'),
                    'size' => $file->getSize(), 'mime_type' => $file->getMimeType(), 'uploaded_at' => now()->toDateTimeString(),
                ];
            }
        }
        return $uploaded;
    }

    /** Saved history is never deleted: a correction is a new record. */
    public function destroy($id)
    {
        ComplexHistory::whereHas('complex', fn ($query) => $query->where('user_id', business_owner_id()))->findOrFail($id);
        return response()->json(['success' => false, 'message' => 'Saved history is retained. Add a new record to document a correction.'], 409);
    }
}
