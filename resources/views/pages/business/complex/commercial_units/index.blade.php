@extends('pages.business.layouts.app')
@section('content')
@php
    $statusTone = ['Available' => 'is-free', 'Occupied' => 'is-let', 'Reserved' => 'is-held', 'Under Maintenance' => 'is-repair'];
    $sqft = fn ($v) => number_format((float) $v, (float) $v == floor((float) $v) ? 0 : 1);
    $filtered = $search !== '' || request()->filled('status');
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">Commercial inventory</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1">
                <li class="breadcrumb-item text-muted"><a href="{{ route('complex.index') }}" class="text-muted text-hover-primary">Property portfolio</a></li>
                @if($selectedProperty)<li class="breadcrumb-item text-muted"><a href="{{ route('complex.show', $selectedProperty->slug) }}#commercial_units" class="text-muted text-hover-primary">{{ $selectedProperty->title }}</a></li>@endif
                <li class="breadcrumb-item text-dark">Commercial spaces</li>
            </ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page" id="kt_content_container" data-commercial-inventory>

    <header class="inv-head">
        <div>
            <span class="inv-eyebrow">Commercial inventory</span>
            <h2>{{ $selectedProperty?->title ?? 'Commercial spaces across your portfolio' }}</h2>
            <p>Find a space, see how much of it is let and who holds it, then open its property or its details.</p>
        </div>
        @if($canEdit)
        <div class="inv-actions">
            <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#kt_modal_upload_commercial_units"><i class="bi bi-upload me-2" aria-hidden="true"></i>Import spaces</button>
            <a class="btn btn-primary" href="{{ route('commercial-units.create', $selectedProperty ? ['complex_id' => $selectedProperty->id] : []) }}"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Add commercial spaces</a>
        </div>
        @endif
    </header>

    <div class="inv-stats" data-inventory-stats>
        <div><span>Spaces</span><strong>{{ $totals->sum() }}</strong><small>{{ $selectedProperty ? 'at this property' : 'across ' . $complexes->count() . ' ' . \Illuminate\Support\Str::plural('property', $complexes->count()) }}</small></div>
        <div><span>Available</span><strong class="is-free">{{ $totals['Available'] ?? 0 }}</strong><small>recorded as free to let</small></div>
        <div><span>Occupied</span><strong>{{ $totals['Occupied'] ?? 0 }}</strong><small>{{ ($totals['Reserved'] ?? 0) + ($totals['Under Maintenance'] ?? 0) ? (($totals['Reserved'] ?? 0) . ' reserved · ' . ($totals['Under Maintenance'] ?? 0) . ' under maintenance') : 'none reserved or under repair' }}</small></div>
        <div class="inv-stat-area">
            <span>Floor area</span><strong>{{ $sqft($area['total']) }} <em>sq ft</em></strong>
            @php $letPct = $area['total'] > 0 ? round($area['let'] / $area['total'] * 100) : 0; @endphp
            <div class="inv-bar" role="img" aria-label="{{ $letPct }}% of floor area let"><i style="width: {{ $letPct }}%"></i></div>
            <small>{{ $sqft($area['let']) }} let · {{ $sqft($area['free']) }} free ({{ 100 - $letPct }}%)</small>
        </div>
    </div>

    <section class="inv-panel">
        <form method="GET" class="inv-toolbar" role="search" aria-label="Filter commercial inventory">
            <div class="inv-search"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control form-control-sm" id="inventory-q" name="q" value="{{ $search }}" placeholder="Unit number or property name" aria-label="Search units or properties"></div>
            <select class="form-select form-select-sm" id="inventory-property" name="complex_id" aria-label="Property">
                <option value="">All properties</option>
                @foreach($complexes as $property)<option value="{{ $property->id }}" @selected($selectedProperty?->id === $property->id)>{{ $property->title }}</option>@endforeach
            </select>
            <select class="form-select form-select-sm" id="inventory-status" name="status" aria-label="Recorded status">
                <option value="">Any status</option>
                @foreach(['Available', 'Occupied', 'Reserved', 'Under Maintenance'] as $status)<option @selected(request('status') === $status)>{{ $status }}</option>@endforeach
            </select>
            <button class="btn btn-sm btn-primary">Apply</button>
            @if($filtered || $selectedProperty)<a class="btn btn-sm btn-light" href="{{ route('commercial-units.index') }}">Reset</a>@endif
            <span class="inv-count">{{ $units->total() }} {{ \Illuminate\Support\Str::plural('space', $units->total()) }}</span>
        </form>

        <p class="inv-note"><i class="bi bi-info-circle" aria-hidden="true"></i>Recorded availability is not approval to lease. Free area counts active tenancies only: check reservations and the property's setup before onboarding a tenant.</p>

        <div class="table-responsive">
            <table class="table inv-table align-middle mb-0">
                <thead><tr><th>Space</th><th>Property</th><th>Floor area</th><th>Status</th><th>Tenancy</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                @forelse($units as $unit)
                    @php
                        $let = min((float) $unit->unit_sq_ft, (float) $unit->activeTenants->sum(fn ($t) => $t->occupied_sq_area ?? $t->sq_area ?? 0));
                        $pct = $unit->unit_sq_ft > 0 ? round($let / $unit->unit_sq_ft * 100) : 0;
                    @endphp
                    <tr data-space="{{ $unit->id }}">
                        <td>
                            <div class="inv-space">{{ $unit->unit_number ?: 'Number required' }}</div>
                            <div class="inv-sub">{{ $unit->effective_floor ?: 'Floor not recorded' }}</div>
                        </td>
                        <td><a class="inv-link" href="{{ route('complex.show', $unit->complex->slug) }}#commercial_units">{{ $unit->complex->title }}</a></td>
                        <td class="inv-area">
                            <div><strong>{{ $sqft($unit->unit_sq_ft) }}</strong> sq ft</div>
                            <div class="inv-bar is-small" role="img" aria-label="{{ $pct }}% let"><i style="width: {{ $pct }}%"></i></div>
                            <div class="inv-sub">{{ $sqft(max(0, $unit->unit_sq_ft - $let)) }} free · min. {{ $sqft($unit->min_sq_ft) }} · up to {{ $unit->max_subdivisions }} {{ \Illuminate\Support\Str::plural('part', (int) $unit->max_subdivisions) }}</div>
                        </td>
                        <td><span class="inv-status {{ $statusTone[$unit->status] ?? '' }}">{{ $unit->status }}</span></td>
                        <td class="inv-tenancy">
                            @forelse($unit->activeTenants as $tenancy)
                                @include('pages.business.complex.partials.inventory-tenant', ['tenancy' => $tenancy])
                            @empty
                                <span class="inv-sub">No active tenancy</span>
                            @endforelse
                        </td>
                        <td class="text-end text-nowrap">
                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#space-details-{{ $unit->id }}">Details</button>
                            @if($canEdit)<a class="btn btn-sm btn-icon btn-light ms-1" href="{{ route('commercial-units.edit', encrypt($unit->id)) }}" title="Edit {{ $unit->unit_number }}" aria-label="Edit {{ $unit->unit_number }}"><i class="bi bi-pencil" aria-hidden="true"></i></a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="inv-empty">
                        <i class="bi bi-shop" aria-hidden="true"></i>
                        <strong>{{ $filtered ? 'No spaces match' : 'No commercial spaces yet' }}</strong>
                        <span>{{ $filtered ? 'Change the search or filters.' : 'Add spaces to a commercial or mixed-use property.' }}</span>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($units->hasPages())<div class="inv-pages">{{ $units->links('pagination::bootstrap-5') }}</div>@endif
    </section>

    @foreach($units as $unit)
        @include('pages.business.complex.commercial_units.details-modal')
    @endforeach
    @if($canEdit)<div class="modal fade" id="kt_modal_upload_commercial_units" tabindex="-1" aria-label="Import commercial units" aria-hidden="true"><div class="modal-dialog modal-dialog-centered mw-650px"><div class="modal-content"><div class="modal-header"><h2>Import commercial spaces</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p>Imported spaces still need the property's setup review before leasing.</p><div id="units-drag-drop-area"></div></div></div></div></div>@endif
</div>
</div>
@endsection
