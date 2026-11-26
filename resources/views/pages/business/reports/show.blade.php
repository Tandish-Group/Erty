@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Reports'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace reports-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="{{ route('reports.index', $filters->query()) }}">Reports</a></span>
                <h1>{{ $report->title() }}</h1>
                <p>{{ $report->description() }}<br>
                    <strong>{{ $report->usesPeriod() ? $filters->label() : 'As of today, ' . now()->format('j M Y') }}{{ $filters->propertyName() ? ' · ' . $filters->propertyName() : '' }}</strong></p>
            </div>
            @if($canDownload)
            <div class="tenant-heading-actions">
                <a class="btn btn-light" href="{{ route('reports.show', ['key' => $report->key()] + $filters->query(['format' => 'xlsx'])) }}"><i class="fas fa-file-excel me-1" aria-hidden="true"></i>Excel</a>
                <a class="btn btn-light" href="{{ route('reports.show', ['key' => $report->key()] + $filters->query(['format' => 'pdf'])) }}"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i>PDF</a>
            </div>
            @endif
        </header>

        @if($reports->count() > 1)
            <nav class="support-tabs report-switch" aria-label="Other reports">
                @foreach($reports as $r)
                    <a href="{{ $r->key() === 'overview' ? route('reports.index', $filters->query()) : route('reports.show', ['key' => $r->key()] + $filters->query()) }}" class="{{ $r->key() === $report->key() ? 'is-active' : '' }}"><i class="fas {{ $r->icon() }}" aria-hidden="true"></i>{{ $r->key() === 'overview' ? 'Overview' : $r->title() }}</a>
                @endforeach
            </nav>
        @endif

        @include('pages.business.reports.partials.filters', ['action' => route('reports.show', $report->key()), 'usesPeriod' => $report->usesPeriod()])
        @include('pages.business.reports.partials.result', ['result' => $result])
    </div>
</div>
@endsection
