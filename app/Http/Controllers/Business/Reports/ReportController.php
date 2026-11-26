<?php

namespace App\Http\Controllers\Business\Reports;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Complex;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reports: the list and business overview (/reports), and each report on screen, as Excel or as PDF.
 * The route is open to the team; each report checks its own rights (see Report::rights()).
 */
class ReportController extends Controller
{
    public function __construct(private ReportRegistry $registry)
    {
    }

    public function index(Request $request)
    {
        $reports = $this->registry->availableTo($request->user());
        abort_if($reports->isEmpty(), 403, 'Your role does not include any reports.');

        $filters = ReportFilters::fromRequest($request);
        $overview = $reports->get('overview');

        return view('pages.business.reports.index', [
            'reports' => $reports->except('overview'),
            'report' => $overview,
            'result' => $overview?->build($filters, $request->user()),
            'filters' => $filters,
            'canDownload' => $this->canDownload($request),
            'properties' => Complex::forBusiness()->orderBy('title')->pluck('title', 'id'),
        ]);
    }

    private function canDownload(Request $request): bool
    {
        return $request->user()->isBusinessOwner() || $request->user()->hasBusinessPermission('reports', 'export');
    }

    public function show(Request $request, string $key)
    {
        $report = $this->registry->find($key);
        abort_unless($report, 404);
        abort_unless($report->allows($request->user()), 403, 'Your role does not include this report.');
        if ($key === 'overview' && !$request->query('format')) {
            return redirect()->route('reports.index', $request->query()); // the overview is shown on the Reports page
        }

        $format = $request->query('format');
        if (in_array($format, ['xlsx', 'pdf'], true)) {
            abort_unless($this->canDownload($request), 403, 'Your role does not include downloading reports.');
        }

        $filters = ReportFilters::fromRequest($request);
        $result = $report->build($filters, $request->user());

        return match ($format) {
            'xlsx' => Excel::download(new ReportExport($report, $result, $filters), $this->filename($report, $filters, 'xlsx')),
            'pdf' => $this->pdf($report, $result, $filters),
            default => view('pages.business.reports.show', [
                'report' => $report,
                'result' => $result,
                'filters' => $filters,
                'reports' => $this->registry->availableTo($request->user()),
                'canDownload' => $this->canDownload($request),
                'properties' => Complex::forBusiness()->orderBy('title')->pluck('title', 'id'),
                'agents' => $report->usesAgentFilter()
                    ? Agent::where('created_by', business_owner_id())->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name'])
                    : collect(),
            ]),
        };
    }

    private function pdf(Report $report, $result, ReportFilters $filters)
    {
        return Pdf::loadView('pdf.report', [
            'report' => $report,
            'result' => $result,
            'filters' => $filters,
            'business' => auth()->user()->business?->business_name ?? config('app.name'),
        ])->setPaper('a4', 'landscape')->download($this->filename($report, $filters, 'pdf'));
    }

    private function filename(Report $report, ReportFilters $filters, string $extension): string
    {
        return Str::slug($report->title()) . '-' . $filters->from . '-to-' . $filters->to . '.' . $extension;
    }
}
