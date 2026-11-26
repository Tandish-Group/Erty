@extends('pages.business.layouts.app')
@section('content')
@php
    $house = ($unit['unit_category'] ?? null) === 'house';
    $noun = $house ? 'house' : 'unit';
    $stepKey = $activeStep ?? ($house ? 'houses' : 'residential');
    $saleStatus = in_array($unit['status'] ?? null, \App\Services\Properties\ComplexInventoryService::SALE_STATUSES, true);
    $statusLabels = \App\Services\Properties\ComplexInventoryService::RESIDENTIAL_STATUS_LABELS;
@endphp
<div class="header" id="kt_header"><div class="container-xxl d-flex justify-content-between align-items-center"><h1>{{ $house ? 'Houses' : 'Residential inventory' }}</h1>@include('pages.business.include.topbar.index')</div></div>
<div class="content"><div class="container-xxl py-6 property-flow" style="max-width:1080px" data-property-flow>
    <nav aria-label="Breadcrumb" class="mb-6"><a href="{{ route('residential-units.index', ['complex_id' => $complex->id] + ($house ? ['category' => 'house'] : [])) }}">← {{ $house ? 'Houses' : 'Residential inventory' }}</a><span class="text-muted mx-3">/</span>{{ $complex->title }}</nav>
    <div class="mb-7"><span class="text-muted text-uppercase fs-8 fw-bold">{{ $house ? 'House details' : 'Unit details' }}</span><h2 class="fs-1 mt-2">{{ $canEdit ? 'Refine' : 'View' }} {{ $unit['unit_number'] ?: 'unnumbered ' . $noun . ' #'.$unit['id'] }}</h2><p class="text-muted mb-0">{{ $house ? 'Update this house’s number, plot, layout and what it is offered for.' : 'Update this unit’s reference, floor and measured area. Layout and tenancy details are shown for context.' }}</p></div>
    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Please check these details</strong>@foreach($errors->all() as $error)<p class="mb-0 mt-2">{{ $error }}</p>@endforeach</div>@endif
    <div class="row g-6">
        <div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-body p-6 p-md-8">
            <h3 class="mb-6">Identity & measurements</h3>
            <form data-live-form data-save-stay="true" method="POST" action="{{ route('complex.workflow.residential', [$complex->slug, $unit['id']]) }}">
                @csrf<input type="hidden" name="fingerprint" value="{{ $step['fingerprint'] }}"><input type="hidden" name="return_to" value="inventory">
                <fieldset @disabled(!$canEdit)>
                    <div class="row g-5 mb-6">
                        <div class="{{ $house ? 'col-sm-6' : 'col-12' }}"><label for="refine-number" class="form-label required">{{ $house ? 'House number' : 'Unit number / reference' }}</label><input id="refine-number" name="unit_number" maxlength="255" required class="form-control form-control-lg" value="{{ old('unit_number', $unit['unit_number']) }}" placeholder="{{ $house ? 'e.g. 12 or Villa 3' : 'e.g. A-101' }}"><div class="form-text">Use a reference unique to this property.</div></div>
                        @if($house)
                        <div class="col-sm-6"><label for="refine-plot" class="form-label">Plot / LR number</label><input id="refine-plot" name="plot_number" maxlength="60" class="form-control form-control-lg" value="{{ old('plot_number', $unit['plot_number'] ?? '') }}" placeholder="Optional"></div>
                        @endif
                    </div>
                    @if($house)
                    <div class="row g-5 mb-6">
                        <div class="col-sm-4"><label for="refine-type" class="form-label">House type</label><select id="refine-type" name="house_type" class="form-select form-select-lg">@foreach(config('property.house_types') as $type)<option @selected(old('house_type', $unit['house_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
                        <div class="col-sm-4"><label for="refine-bedrooms" class="form-label">Bedrooms</label><input id="refine-bedrooms" name="bedrooms" type="number" min="0" max="30" class="form-control form-control-lg" value="{{ old('bedrooms', $unit['bedrooms']) }}"></div>
                        <div class="col-sm-4"><label for="refine-bathrooms" class="form-label">Bathrooms</label><input id="refine-bathrooms" name="bathrooms" type="number" min="1" max="30" class="form-control form-control-lg" value="{{ old('bathrooms', $unit['bathrooms']) }}"></div>
                    </div>
                    <div class="row g-5 mb-6">
                        <div class="col-sm-4"><label for="refine-area" class="form-label required">Built-up area (sq ft)</label><input id="refine-area" name="sq_ft" type="number" min="1" step="any" required class="form-control form-control-lg" value="{{ old('sq_ft', $unit['sq_ft']) }}"></div>
                        <div class="col-sm-4"><label for="refine-plot-size" class="form-label">Plot size</label><div class="input-group input-group-lg"><input id="refine-plot-size" name="plot_size" type="number" min="0.001" step="any" class="form-control" value="{{ old('plot_size', $unit['plot_size'] ?? '') }}"><select class="form-select" name="plot_unit" aria-label="Plot size unit" style="max-width:7.5rem">@foreach(config('property.plot_units') as $value => $label)<option value="{{ $value }}" @selected(old('plot_unit', $unit['plot_unit'] ?? 'acres') === $value)>{{ $label }}</option>@endforeach</select></div></div>
                        <div class="col-sm-4"><label for="refine-title" class="form-label">Title number</label><input id="refine-title" name="title_number" maxlength="120" class="form-control form-control-lg" value="{{ old('title_number', $unit['title_number'] ?? '') }}" placeholder="Optional"></div>
                    </div>
                    <div class="row g-5 mb-6" data-offer-fields>
                        <div class="col-sm-6"><label for="refine-offer" class="form-label">Offered for</label><select id="refine-offer" name="offer" class="form-select form-select-lg" data-house-offer>@foreach(config('property.offers') as $value => $label)<option value="{{ $value }}" @selected(old('offer', $unit['offer'] ?? 'rent') === $value)>{{ $label }}</option>@endforeach</select><div class="form-text">For-sale-only houses are not offered during tenant onboarding.</div></div>
                        <div class="col-sm-6" data-sale-price-field @if(!in_array(old('offer', $unit['offer'] ?? 'rent'), ['sale', 'both'], true)) hidden @endif><label for="refine-price" class="form-label">Asking price ({{ default_currency() }})</label><input id="refine-price" name="sale_price" type="number" min="1" step="any" class="form-control form-control-lg" value="{{ old('sale_price', $unit['sale_price'] ?? '') }}"></div>
                    </div>
                    @else
                    <div class="row g-5 mb-6">
                        <div class="col-sm-6"><label for="refine-floor" class="form-label required">Floor</label><input id="refine-floor" name="floor" type="number" min="-20" max="300" step="1" required class="form-control form-control-lg" value="{{ old('floor', $unit['floor']) }}"><div class="form-text">0 = ground floor; negative values = basement levels.</div></div>
                        <div class="col-sm-6"><label for="refine-area" class="form-label required">Area (sq ft)</label><input id="refine-area" name="sq_ft" type="number" min="1" step="any" required class="form-control form-control-lg" value="{{ old('sq_ft', $unit['sq_ft']) }}"><div class="form-text">Enter this unit’s measured floor area.</div></div>
                    </div>
                    @endif
                    @php $occupied = ($unit['status'] ?? null) === 'occupied' || ($hasActiveTenancy ?? false); @endphp
                    <div class="mb-6">
                        <label for="refine-status" class="form-label">Availability</label>
                        @if($occupied || $saleStatus)
                            <input type="text" class="form-control form-control-lg" value="{{ $occupied ? 'Occupied' : $statusLabels[$unit['status']] }}" disabled>
                            <div class="form-text">{{ $occupied ? 'This ' . $noun . ' has an active tenancy; its status is managed by the tenancy.' : 'This house’s status follows its sale.' }}</div>
                        @else
                            <select id="refine-status" name="status" class="form-select form-select-lg">
                                @foreach(['available' => 'Available', 'reserved' => 'Reserved', 'maintenance' => 'Under maintenance'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $unit['status'] ?? 'available') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Reserved and maintenance {{ \Illuminate\Support\Str::plural($noun) }} are not offered during tenant onboarding. Changing availability alone does not reset setup review.</div>
                        @endif
                    </div>
                </fieldset>
                <div class="rounded bg-light-warning p-4 mb-6"><strong class="d-block mb-1">Setup review follows identity changes</strong>Changing the {{ $house ? 'house number, plot, layout or area' : 'unit number, floor or area' }} returns the property to draft setup. Existing tenants are not changed; review and confirm the updated inventory before leasing.@if($house) What a house is offered for and its price do not reset setup.@endif</div>
                <div class="d-flex flex-wrap gap-3 pt-5 border-top">@if($canEdit)<button class="btn btn-primary">Save {{ $noun }} details</button>@endif<a class="btn btn-light" href="{{ route('residential-units.index', ['complex_id' => $complex->id]) }}">{{ $canEdit ? 'Cancel' : 'Back to inventory' }}</a></div>
            </form>
        </div></div></div>
        <aside class="col-lg-4"><div class="card bg-light border-0"><div class="card-body p-6"><h3 class="fs-5 mb-5">{{ $house ? 'House' : 'Unit' }} context</h3><dl class="mb-0"><dt class="text-muted fw-normal">Property</dt><dd class="mb-5">{{ $complex->title }}</dd><dt class="text-muted fw-normal">Layout</dt><dd class="mb-5">{{ $house ? (($unit['house_type'] ?? 'House') . ' · ') : '' }}{{ $unit['bedrooms'] ?? '—' }} bedrooms · {{ $unit['bathrooms'] ?? '—' }} bathrooms</dd><dt class="text-muted fw-normal">Recorded status</dt><dd class="mb-5">{{ $statusLabels[$unit['status'] ?? ''] ?? 'Not recorded' }}</dd>
            @if($house && ($liveSale ?? null))<dt class="text-muted fw-normal">Sale in progress</dt><dd class="mb-5">@if(auth()->user()->hasBusinessPermission('sales_management', 'view'))<a href="{{ route('sales.show', $liveSale->reference) }}">{{ $liveSale->reference }} · {{ $liveSale->buyer_name }}</a>@else{{ $liveSale->reference }}@endif</dd>@endif
            </dl><a data-flow-link class="d-block" href="{{ route('complex.setup', [$complex->slug, 'step' => $stepKey]) }}">Open full inventory review →</a></div></div></aside>
    </div>
</div></div>
@if($house)
<script>
    document.addEventListener('change', function (e) {
        if (!e.target.matches('[data-house-offer]')) return;
        const field = e.target.closest('form').querySelector('[data-sale-price-field]');
        const sale = ['sale', 'both'].includes(e.target.value);
        field.hidden = !sale; field.querySelector('input').required = sale;
    });
</script>
@endif
@endsection
