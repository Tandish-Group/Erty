<?php

namespace App\Http\Controllers\Business\Crm;

use App\Exports\ReportExport;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\Complex;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportRegistry;
use App\Services\Reports\Reports\LeadsReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * CRM → Reports: the Leads & pipeline report, inside CRM. It is the same report as Reports → Leads & pipeline
 * (one set of figures), opened here with the CRM right (crm_management.view, checked by the route).
 * Downloading needs the Reports → Download right, as everywhere else.
 */
class CrmReportsController extends Controller
{
    public function index(Request $request, LeadsReport $report)
    {
        $filters = ReportFilters::fromRequest($request);
        $result = $report->build($filters, $request->user());
        $canDownload = $request->user()->isBusinessOwner() || $request->user()->hasBusinessPermission('reports', 'export');

        $format = $request->query('format');
        if (in_array($format, ['xlsx', 'pdf'], true)) {
            abort_unless($canDownload, 403, 'Your role does not include downloading reports.');
            $name = Str::slug($report->title()) . '-' . $filters->from . '-to-' . $filters->to . '.' . $format;

            return $format === 'xlsx'
                ? Excel::download(new ReportExport($report, $result, $filters), $name)
                : Pdf::loadView('pdf.report', [
                    'report' => $report, 'result' => $result, 'filters' => $filters,
                    'business' => $request->user()->business?->business_name ?? config('app.name'),
                ])->setPaper('a4', 'landscape')->download($name);
        }

        $reports = app(ReportRegistry::class)->availableTo($request->user());

        return view('pages.business.crm.reports.index', [
            'report' => $report,
            'result' => $result,
            'filters' => $filters,
            'canDownload' => $canDownload,
            'agentsReport' => $reports->get('agents'),
            'properties' => Complex::forBusiness()->orderBy('title')->pluck('title', 'id'),
            'agents' => Agent::where('created_by', business_owner_id())->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'company_name']),
        ]);
    }
}
