@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Reports'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace reports-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Reports</span>
                <h1>How the business is doing</h1>
                <p>Figures from properties, tenants, rent, expenses, agents, sales and repairs, combined. Choose a period and a property, or open a report for the detail.</p>
            </div>
            @if($report && $canDownload)
                <div class="tenant-heading-actions">
                    <a class="btn btn-light" href="{{ route('reports.show', ['key' => 'overview'] + $filters->query(['format' => 'xlsx'])) }}"><i class="fas fa-file-excel me-1" aria-hidden="true"></i>Excel</a>
                    <a class="btn btn-light" href="{{ route('reports.show', ['key' => 'overview'] + $filters->query(['format' => 'pdf'])) }}"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i>PDF</a>
                </div>
            @endif
        </header>

        @if($reports->isNotEmpty())
            <nav class="report-cards mb-6" aria-label="Reports">
                @foreach($reports as $r)
                    <a href="{{ route('reports.show', ['key' => $r->key()] + $filters->query()) }}" class="report-card">
                        <i class="fas {{ $r->icon() }}" aria-hidden="true"></i>
                        <span><strong>{{ $r->title() }}</strong><small>{{ $r->description() }}</small></span>
                        <i class="fas fa-chevron-right report-card-arrow" aria-hidden="true"></i>
                    </a>
                @endforeach
            </nav>
        @endif

        @if($report)
            <h2 class="report-heading">{{ $report->title() }} <span>{{ $filters->label() }}{{ $filters->propertyName() ? ' · ' . $filters->propertyName() : '' }}</span></h2>
            @include('pages.business.reports.partials.filters', ['action' => route('reports.index'), 'usesPeriod' => true])
            @include('pages.business.reports.partials.result', ['result' => $result])
        @endif
    </div>
</div>
@endsection
