@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'CRM reports'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace reports-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">@businessCan('crm_management', 'view')<a href="/customer_relationship">CRM</a>@else CRM @endbusinessCan · Reports</span>
                <h1>{{ $report->title() }}</h1>
                <p>{{ $report->description() }}<br>
                    <strong>{{ $filters->label() }}{{ $filters->propertyName() ? ' · ' . $filters->propertyName() : '' }}</strong></p>
            </div>
            <div class="tenant-heading-actions">
                @if($agentsReport)
                    <a class="btn btn-light" href="{{ route('reports.show', ['key' => 'agents'] + $filters->query()) }}"><i class="fas fa-user-tie me-1" aria-hidden="true"></i>{{ $agentsReport->title() }}</a>
                @endif
                @if($canDownload)
                    <a class="btn btn-light" href="{{ route('crm.reports.index', $filters->query(['format' => 'xlsx'])) }}"><i class="fas fa-file-excel me-1" aria-hidden="true"></i>Excel</a>
                    <a class="btn btn-light" href="{{ route('crm.reports.index', $filters->query(['format' => 'pdf'])) }}"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i>PDF</a>
                @endif
            </div>
        </header>

        @include('pages.business.reports.partials.filters', ['action' => route('crm.reports.index'), 'usesPeriod' => true])
        @include('pages.business.reports.partials.result', ['result' => $result])
    </div>
</div>
@endsection
