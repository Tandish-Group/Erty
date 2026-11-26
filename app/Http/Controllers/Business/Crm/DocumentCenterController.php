<?php

namespace App\Http\Controllers\Business\Crm;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Complex;
use App\Models\ComplexHistory;
use App\Models\TenantManagement;
use App\Services\Documents\DocumentCentre;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CRM → Document centre: every file Erty keeps for the business, in one searchable list.
 * The page needs the CRM right (route map); each kind of file also needs the right that opens it in its own
 * section (DocumentCentre::SOURCES), and property-bound files follow the person's property assignments.
 */
class DocumentCenterController extends Controller
{
    private const PER_PAGE = 25;
    private const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'name' => 'Name A–Z', 'largest' => 'Largest first'];

    public function __construct(private DocumentCentre $centre)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $sources = $this->centre->sourcesFor($user);
        $filters = [
            'source' => array_key_exists($request->query('source'), $sources) ? $request->query('source') : '',
            'property' => (int) $request->query('property') ?: null,
            'type' => array_key_exists($request->query('type'), DocumentCentre::TYPES) ? $request->query('type') : '',
            'q' => trim((string) $request->query('q')),
            'sort' => array_key_exists($request->query('sort'), self::SORTS) ? $request->query('sort') : 'newest',
        ];

        // Everything the person may see, narrowed by property, type and search; the source tabs count from here.
        $all = $this->centre->documents($user, array_keys($sources))
            ->when($filters['property'], fn ($c) => $c->where('complex_id', $filters['property']))
            ->when($filters['type'], fn ($c) => $c->where('type', $filters['type']))
            ->when($filters['q'] !== '', fn ($c) => $c->filter(fn ($d) => Str::contains(
                mb_strtolower(implode(' ', [$d['name'], $d['kind'], $d['related'], $d['reference'], $d['property']])),
                mb_strtolower($filters['q']))));
        $counts = $all->countBy('source');
        $shown = $filters['source'] ? $all->where('source', $filters['source']) : $all;
        $shown = match ($filters['sort']) {
            'oldest' => $shown->sortBy(fn ($d) => $d['date']?->timestamp ?? 0),
            'name' => $shown->sortBy(fn ($d) => mb_strtolower($d['name'])),
            'largest' => $shown->sortByDesc(fn ($d) => $d['size'] ?? -1),
            default => $shown,
        };

        $page = LengthAwarePaginator::resolveCurrentPage();
        $documents = new LengthAwarePaginator($shown->forPage($page, self::PER_PAGE)->values(), $shown->count(), self::PER_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('pages.business.crm.document_management.index', [
            'sources' => $sources,
            'counts' => $counts,
            'total' => $all->count(),
            'missing' => $all->where('exists', false)->count(),
            'documents' => $documents,
            'filters' => $filters,
            'sorts' => self::SORTS,
            'types' => DocumentCentre::TYPES,
            'properties' => Complex::forBusiness()->orderBy('title')->pluck('title', 'id'),
        ]);
    }

    /** Download a tenancy file, a history record's file or an agent's CV, after the same checks as the list. */
    public function file(Request $request, string $source, int $id, int $index)
    {
        abort_unless($this->centre->allows($request->user(), $source, true), 403, 'Your role does not include these documents.');

        [$path, $name] = match ($source) {
            'tenancy' => $this->tenancyFile($id, $index),
            'history' => $this->historyFile($id, $index),
            'agent' => $this->agentFile($id),
            default => abort(404),
        };
        $disk = Storage::disk('public');
        abort_unless($path && $disk->exists($path), 404, 'This file is missing from storage.');

        return $disk->download($path, $name, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function tenancyFile(int $id, int $index): array
    {
        $doc = (TenantManagement::forBusiness()->findOrFail($id)->uploaded_documents ?? [])[$index] ?? abort(404);

        return [$doc['path'] ?? $doc['relative_path'] ?? null, $doc['original_name'] ?? null];
    }

    private function historyFile(int $id, int $index): array
    {
        $record = ComplexHistory::query()->whereIn('complex_id', Complex::forBusiness()->select('id'))->findOrFail($id);
        $file = DocumentCentre::historyFiles($record)[$index] ?? abort(404);

        return [$file['path'], $file['name']];
    }

    private function agentFile(int $id): array
    {
        $agent = Agent::where('created_by', business_owner_id())->findOrFail($id);
        $name = Str::slug(trim($agent->first_name . ' ' . $agent->last_name) ?: (string) $agent->company_name) ?: 'agent';

        return [$agent->resume, 'cv-' . $name . '.' . (pathinfo((string) $agent->resume, PATHINFO_EXTENSION) ?: 'pdf')];
    }
}
