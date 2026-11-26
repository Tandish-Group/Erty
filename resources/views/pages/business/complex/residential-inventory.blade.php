@extends('pages.business.layouts.app')
@section('content')
@php
    $labels = \App\Services\Properties\ComplexInventoryService::RESIDENTIAL_STATUS_LABELS;
    $statusTone = ['available' => 'is-free', 'occupied' => 'is-let', 'reserved' => 'is-held', 'maintenance' => 'is-repair', 'under maintenance' => 'is-repair', 'under_offer' => 'is-held', 'sold' => 'is-let'];
    $category = request('category');
    $filtered = $search !== '' || request()->filled('status') || $category || request()->filled('offer');
    $total = array_sum($totals);
    $free = $totals['available'] ?? 0;
    $noun = $category === 'house' ? 'house' : ($category === 'apartment' ? 'apartment' : 'home');
    $cur = default_currency();
    $canSell = $houses && app(\App\Services\Access\ModuleService::class)->enabled('sales') && auth()->user()->hasBusinessPermission('sales_management', 'create');
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">Residential inventory</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1">
                <li class="breadcrumb-item text-muted"><a href="{{ route('complex.index') }}" class="text-muted text-hover-primary">Property portfolio</a></li>
                @if($selectedProperty)<li class="breadcrumb-item text-muted"><a href="{{ route('complex.show', $selectedProperty->slug) }}#residential_units" class="text-muted text-hover-primary">{{ $selectedProperty->title }}</a></li>@endif
                <li class="breadcrumb-item text-dark">{{ $category === 'house' ? 'Houses' : ($category === 'apartment' ? 'Apartments' : 'Apartments & houses') }}</li>
            </ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page" id="kt_content_container" data-residential-inventory>
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }} @if($selectedProperty)<a href="{{ route('complex.setup', [$selectedProperty->slug, 'step' => $category === 'house' ? 'houses' : 'residential']) }}">Review inventory →</a>@endif</div>
    @endif

    <header class="inv-head">
        <div>
            <span class="inv-eyebrow">Residential inventory</span>
            <h2>{{ $selectedProperty?->title ?? 'Apartments and houses across your portfolio' }}</h2>
            <p>Find a unit or house, check whether it is free, let or sold, and open its details.</p>
        </div>
        @if($selectedProperty && $canEdit)
        <div class="inv-actions">
            <a class="btn btn-primary" href="{{ route('complex.setup', [$selectedProperty->slug, 'step' => $category === 'house' ? 'houses' : 'residential']) }}"><i class="bi bi-sliders me-2" aria-hidden="true"></i>Manage {{ $category === 'house' ? 'houses' : 'unit setup' }}</a>
        </div>
        @endif
    </header>

    <div class="inv-stats" data-inventory-stats>
        <div><span>{{ ucfirst(\Illuminate\Support\Str::plural($noun)) }}</span><strong>{{ $total }}</strong><small>{{ $selectedProperty ? 'at this property' : 'across ' . $properties->count() . ' ' . \Illuminate\Support\Str::plural('property', $properties->count()) }}{{ $houses && !$category ? ' · ' . $houses . ' ' . \Illuminate\Support\Str::plural('house', $houses) : '' }}</small></div>
        <div><span>Available</span><strong class="is-free">{{ $free }}</strong><small>recorded as free</small></div>
        <div><span>Occupied</span><strong>{{ $totals['occupied'] ?? 0 }}</strong><small>{{ ($totals['reserved'] ?? 0) + ($totals['maintenance'] ?? 0) ? ($totals['reserved'] ?? 0) . ' reserved · ' . ($totals['maintenance'] ?? 0) . ' under maintenance' : 'none reserved or under repair' }}</small></div>
        <div class="inv-stat-area">
            @php $letPct = $total ? round(($totals['occupied'] ?? 0) / $total * 100) : 0; @endphp
            <span>{{ $houses ? 'Let · under offer · sold' : 'Occupancy' }}</span><strong>{{ $letPct }}% <em>let</em></strong>
            <div class="inv-bar" role="img" aria-label="{{ $letPct }}% let"><i style="width: {{ $letPct }}%"></i></div>
            <small>{{ $houses ? ($totals['under_offer'] ?? 0) . ' under offer · ' . ($totals['sold'] ?? 0) . ' sold' : $free . ' free of ' . $total }}</small>
        </div>
    </div>

    <section class="inv-panel">
        <form method="GET" class="inv-toolbar" role="search" aria-label="Filter residential inventory">
            <div class="inv-search"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control form-control-sm" id="residential-q" name="q" value="{{ $search }}" maxlength="200" placeholder="Unit, house or property" aria-label="Search units, houses or properties"></div>
            <select class="form-select form-select-sm" id="residential-property" name="complex_id" aria-label="Property"><option value="">All properties</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected($selectedProperty?->id === $property->id)>{{ $property->title }}</option>@endforeach</select>
            @if($houses)
            <select class="form-select form-select-sm" name="category" aria-label="Apartments or houses"><option value="">Apartments & houses</option><option value="apartment" @selected($category === 'apartment')>Apartments</option><option value="house" @selected($category === 'house')>Houses</option></select>
            <select class="form-select form-select-sm" name="offer" aria-label="Offered for"><option value="">Any offer</option>@foreach(config('property.offers') as $v => $l)<option value="{{ $v }}" @selected(request('offer') === $v)>{{ $l }}</option>@endforeach</select>
            @endif
            <select class="form-select form-select-sm" id="residential-status" name="status" aria-label="Recorded status"><option value="">Any status</option>@foreach(['available', 'occupied', 'reserved', 'maintenance'] + ($houses ? [4 => 'under_offer', 5 => 'sold'] : []) as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $labels[$status] }}</option>@endforeach</select>
            <button class="btn btn-sm btn-primary">Apply</button>
            @if($filtered || $selectedProperty)<a class="btn btn-sm btn-light" href="{{ route('residential-units.index') }}">Reset</a>@endif
            <span class="inv-count">{{ number_format($units->total()) }} {{ \Illuminate\Support\Str::plural($noun, $units->total()) }}</span>
        </form>

        <p class="inv-note"><i class="bi bi-info-circle" aria-hidden="true"></i>Recorded availability is not approval to lease. Check the property's setup and active tenancies before onboarding a tenant.</p>

        <div class="table-responsive">
            <table class="table inv-table align-middle mb-0">
                <caption class="visually-hidden">{{ number_format($units->total()) }} matching · {{ $units->firstItem() ?? 0 }}–{{ $units->lastItem() ?? 0 }} shown</caption>
                <thead><tr><th>{{ $category === 'house' ? 'House' : 'Unit' }}</th><th>Property</th><th>Layout & area</th><th>Status</th><th>Tenancy{{ $houses ? ' or sale' : '' }}</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                @forelse($units as $unit)
                    @php $isHouse = ($unit->unit_category ?? null) === 'house'; $st = strtolower((string) $unit->status); @endphp
                    <tr data-unit="{{ $unit->id }}">
                        <td>
                            <div class="inv-space">{{ $unit->unit_number ?: 'Number required' }}</div>
                            <div class="inv-sub">@if($isHouse){{ $unit->house_type ?: 'House' }}{{ $unit->plot_number ? ' · plot ' . $unit->plot_number : '' }}@else Floor {{ $unit->floor ?? 'not recorded' }}@endif</div>
                        </td>
                        <td><a class="inv-link" href="{{ route('complex.show', $unit->complex->slug) }}#{{ $isHouse ? 'houses' : 'residential_units' }}">{{ $unit->complex->title }}</a></td>
                        <td class="inv-area">
                            <div><strong>{{ $unit->bedrooms }}</strong> {{ \Illuminate\Support\Str::plural('bed', (int) $unit->bedrooms) }} · <strong>{{ $unit->bathrooms }}</strong> {{ \Illuminate\Support\Str::plural('bath', (int) $unit->bathrooms) }}</div>
                            <div class="inv-sub">{{ $unit->sq_ft === null ? 'Area not recorded' : number_format((float) $unit->sq_ft) . ' sq ft' }}{{ $isHouse && $unit->plotLabel() ? ' · plot ' . $unit->plotLabel() : '' }}</div>
                            @if($isHouse)<div class="inv-sub">{{ config('property.offers')[$unit->offer ?? 'rent'] }}{{ $unit->forSale() && $unit->sale_price ? ' · ' . $cur . ' ' . number_format($unit->sale_price) : '' }}</div>@endif
                        </td>
                        <td><span class="inv-status {{ $statusTone[$st] ?? '' }}">{{ $labels[$st] ?? ucfirst($unit->status ?? 'Not recorded') }}</span>@if($st === 'available' && $unit->activeTenancies->isNotEmpty())<div class="text-danger fs-8 mt-1">Check status: active tenancy recorded</div>@endif</td>
                        <td class="inv-tenancy">
                            @forelse($unit->activeTenancies as $tenancy)
                                @include('pages.business.complex.partials.inventory-tenant', ['tenancy' => $tenancy])
                            @empty
                                @if($isHouse && $unit->relationLoaded('liveSale') && $unit->liveSale)
                                    <div>{{ $unit->liveSale->buyer_name }}</div><span class="inv-sub">Sale {{ $unit->liveSale->reference }}</span>
                                @else<span class="inv-sub">{{ $st === 'sold' ? 'Sold' : 'No active tenancy' }}</span>@endif
                            @endforelse
                        </td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-light" href="{{ route('complex.setup', [$unit->complex->slug, 'step' => $isHouse ? 'houses' : 'residential', 'unit' => $unit->id]) }}">{{ $canEdit ? 'Refine' : 'View' }}</a>
                            @if($canSell && $isHouse && $unit->forSale() && $st === 'available' && $unit->activeTenancies->isEmpty())<a class="btn btn-sm btn-light-primary ms-1" href="{{ route('sales.create', ['unit' => $unit->id]) }}">Sell</a>@endif
                            @if($isHouse && $unit->relationLoaded('liveSale') && $unit->liveSale && auth()->user()->hasBusinessPermission('sales_management', 'view'))<a class="btn btn-sm btn-light-primary ms-1" href="{{ route('sales.show', $unit->liveSale->reference) }}">Sale</a>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="inv-empty">
                        <i class="bi bi-house" aria-hidden="true"></i>
                        <strong>{{ $filtered ? 'Nothing matches' : 'No apartments or houses yet' }}</strong>
                        <span>{{ $filtered ? 'Change the search or filters.' : 'Add apartments or houses to a property from its setup.' }}</span>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($units->hasPages())<div class="inv-pages">{{ $units->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
</div>
@endsection
