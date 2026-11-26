@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Listings'])
@php
    $tabs = ['' => 'All', \App\Models\Listing::PUBLISHED => 'Published', \App\Models\Listing::DRAFT => 'Drafts', \App\Models\Listing::UNPUBLISHED => 'Unpublished'];
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">

        @if($errors->any())
            <div class="alert alert-danger" role="alert" data-listing-errors>@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>
        @endif

        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Listings</span>
                <h1>Listings</h1>
                <p>Each property has one listing per unit type, such as its 2-bedroom flats, and one per commercial space. Drafts appear when a property's setup is confirmed. Add the rent, deposit, a description and photos, then publish. Renters see a published listing while at least one unit of that type is vacant.</p>
            </div>
        </header>

        <div class="tenant-metrics mb-7" data-listing-counts>
            <div><span>Published</span><strong>{{ $counts[\App\Models\Listing::PUBLISHED] }}</strong><small>Shown to renters while units are vacant</small></div>
            <div><span>Units on offer</span><strong>{{ $counts['available_units'] }}</strong><small>Vacant units in published listings</small></div>
            <div><span>Drafts</span><strong>{{ $counts[\App\Models\Listing::DRAFT] }}</strong><small>Waiting for rent, deposit or a description</small></div>
            <div><span>Unpublished</span><strong>{{ $counts[\App\Models\Listing::UNPUBLISHED] }}</strong><small>Taken down by your team</small></div>
        </div>

        <section class="tenant-panel">
            <div class="tenant-records-toolbar flex-wrap">
                <nav class="nav tenant-tabs" aria-label="Listing status">
                    @foreach($tabs as $key => $label)
                        @php $active = (string) $status === (string) $key; @endphp
                        <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ route('listings.index', array_filter(['status' => $key, 'complex_id' => $complexId])) }}" @if($active) aria-current="page" @endif>{{ $label }} <span class="badge badge-light ms-1">{{ $key === '' ? $counts['all'] : $counts[$key] }}</span></a>
                    @endforeach
                </nav>
                @if($complexes->count() > 1)
                <form method="get" class="d-flex align-items-center gap-2 py-3">
                    @if($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                    <label for="listing_complex_filter" class="text-muted fs-7 text-nowrap">Property</label>
                    <select id="listing_complex_filter" name="complex_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All properties</option>
                        @foreach($complexes as $c)<option value="{{ $c->id }}" @selected((string) $complexId === (string) $c->id)>{{ $c->title }}</option>@endforeach
                    </select>
                </form>
                @endif
            </div>

            @if($listings->isEmpty())
                <div class="tenant-empty">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <h3>{{ $counts['all'] ? 'No listings match this view' : 'No listings yet' }}</h3>
                    <p>{{ $counts['all'] ? 'Try another status or property.' : 'Confirm a property\'s setup in Property portfolio and its listings appear here as drafts.' }}</p>
                    @unless($counts['all'])<a href="{{ url('/complex') }}" class="btn btn-primary">Open Property portfolio</a>@endunless
                </div>
            @else
                @foreach($listings as $group)
                    @php $property = $group->first()->complex; @endphp
                    <h2 class="fs-5 fw-bolder mt-6 mb-3">
                        @if($property)<a href="{{ route('complex.show', $property->slug) }}" class="text-dark text-hover-primary">{{ $property->title }}</a>@else Property @endif
                    </h2>
                    @include('pages.business.listings.partials.table', ['rows' => $group])
                @endforeach
            @endif
        </section>
    </div>
</div>
@endsection
