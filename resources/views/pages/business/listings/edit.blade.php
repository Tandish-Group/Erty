@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Listings'])
@php
    $canEdit = auth()->user()->hasBusinessPermission('listings_management', 'edit');
    $missing = $listing->missingForPublishing();
    $type = $listing->complexUnit;
    $space = $listing->commercialUnit;
    $total = $listing->isCommercial() ? 1 : (int) $listing->units;
    $forRent = in_array($listing->offer ?: 'rent', ['rent', 'both'], true);
    $forSale = in_array($listing->offer, ['sale', 'both'], true);
    $offerCounts = $type ? (app(\App\Services\Listings\UnitListingService::class)->offerCounts([$listing])[$listing->id] ?? ['rent' => 0, 'sale' => 0]) : null;
    $statusBadge = [\App\Models\Listing::DRAFT => 'badge-light', \App\Models\Listing::PUBLISHED => 'badge-light-success', \App\Models\Listing::UNPUBLISHED => 'badge-light-warning'];
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="{{ route('listings.index') }}">Listings</a> · {{ $listing->complex?->title }}</span>
                <h1>{{ $listing->name }} <span class="badge {{ $statusBadge[$listing->status] ?? 'badge-light' }} align-middle fs-7">{{ $listing->status }}</span></h1>
                <p>Ref. {{ $listing->ref }}@if($listing->published_at) · first published {{ $listing->published_at->format('j M Y') }}@endif</p>
            </div>
            @if($canPublish)
            <div class="tenant-heading-actions">
                @if($listing->isPublished())
                    <form method="post" action="{{ route('listings.unpublish', $listing->id) }}">@csrf<button class="btn btn-light">Unpublish</button></form>
                @else
                    <form method="post" action="{{ route('listings.publish', $listing->id) }}">@csrf<button class="btn btn-primary" @disabled($missing)>Publish</button></form>
                @endif
            </div>
            @endif
        </header>

        @if($errors->any())
            <div class="alert alert-danger" role="alert" data-listing-errors>
                @foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach
            </div>
        @endif
        @if(!$listing->isPublished() && $missing)
            <div class="alert alert-light-warning border border-warning" role="status">To publish, add {{ implode(', ', $missing) }}.</div>
        @elseif($listing->isPublished() && $available === 0)
            <div class="alert alert-light-info border border-info" role="status">Published, but renters cannot see it right now: no {{ $listing->isCommercial() ? 'space is free' : 'unit of this type is vacant' }}. It shows again as soon as one is.</div>
        @endif

        <div class="tenant-metrics mb-7">
            <div><span>Available now</span><strong>{{ $available }}</strong><small>of {{ $total }} {{ $listing->isCommercial() ? 'space' : ($listing->type === 'House' ? \Illuminate\Support\Str::plural('house', $total) : ($total === 1 ? 'unit' : 'units')) }}@if($listing->type === 'House' && $offerCounts) · {{ $offerCounts['rent'] }} to let, {{ $offerCounts['sale'] }} for sale @endif</small></div>
            @if($type)
                <div><span>Bedrooms</span><strong>{{ (int) $type->bedrooms ?: 'Studio' }}</strong><small>{{ (int) $type->bathrooms }} bathroom{{ (int) $type->bathrooms === 1 ? '' : 's' }}</small></div>
                <div><span>Size</span><strong>{{ number_format((float) $type->sq_ft) }}</strong><small>sq ft per unit</small></div>
            @elseif($space)
                <div><span>Space</span><strong>{{ $space->unit_number }}</strong><small>Floor {{ $space->custom_floors ?: $space->floors ?: '—' }}</small></div>
                <div><span>Size</span><strong>{{ number_format((float) $space->unit_sq_ft) }}</strong><small>sq ft (minimum let {{ number_format((float) $space->min_sq_ft) }})</small></div>
            @endif
            <div><span>Property</span><strong class="fs-5">{{ $listing->complex?->title }}</strong><small>{{ collect([$listing->complex?->county_specific, $listing->complex?->county])->filter()->implode(', ') ?: 'Location not recorded' }}</small></div>
        </div>

        <div class="row g-6">
            <div class="col-lg-7">
                <section class="tenant-panel h-100">
                    <h2 class="fs-4 fw-bolder mb-1">{{ $forSale && !$forRent ? 'Details for buyers' : ($forSale ? 'Details for renters and buyers' : 'Details for renters') }}</h2>
                    @if($listing->type === 'House')<p class="mb-2"><span class="badge badge-light-primary">{{ config('property.offers')[$listing->offer ?: 'rent'] }}</span> <span class="text-muted fs-8">Set per house type in the property's setup.</span></p>@endif
                    <p class="text-muted fs-7 mb-6">The unit details, location and facilities come from the property. Change those in Property portfolio.</p>
                    <form method="post" action="{{ route('listings.update', $listing->id) }}" data-listing-form>
                        @csrf @method('put')
                        <fieldset @disabled(!$canEdit)>
                        <div class="row g-4">
                            @if($forSale)
                            <div class="col-sm-6">
                                <label for="listing_sale_price" class="form-label required">Asking price ({{ $listing->currency }})</label>
                                <input id="listing_sale_price" name="sale_price" type="number" min="1" step="1" inputmode="numeric" class="form-control @error('sale_price') is-invalid @enderror" value="{{ old('sale_price', $listing->sale_price ? (int) $listing->sale_price : null) }}">
                                @error('sale_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @endif
                            @if($forRent)
                            <div class="col-sm-6">
                                <label for="listing_amount" class="form-label required">Monthly rent ({{ $listing->currency }})</label>
                                <input id="listing_amount" name="amount" type="number" min="1" step="1" inputmode="numeric" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $listing->amount) }}">
                                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-6">
                                <label for="listing_deposit" class="form-label required">Deposit ({{ $listing->currency }})</label>
                                <input id="listing_deposit" name="deposit" type="number" min="0" step="1" inputmode="numeric" class="form-control @error('deposit') is-invalid @enderror" value="{{ old('deposit', $listing->deposit) }}" aria-describedby="listing_deposit_help">
                                <div id="listing_deposit_help" class="form-text">Enter 0 if there is no deposit.</div>
                                @error('deposit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            @endif
                            @unless($listing->isCommercial())
                            <div class="col-sm-6">
                                <label for="listing_furnishing" class="form-label">Furnishing</label>
                                <select id="listing_furnishing" name="furnishing" class="form-select">
                                    <option value="">Not stated</option>
                                    @foreach($furnishing as $option)<option value="{{ $option }}" @selected(old('furnishing', $listing->furnishing) === $option)>{{ $option }}</option>@endforeach
                                </select>
                            </div>
                            @endunless
                            <div class="col-12">
                                <label for="listing_description" class="form-label required">Description</label>
                                <textarea id="listing_description" name="property_description" rows="7" maxlength="5000" class="form-control @error('property_description') is-invalid @enderror" placeholder="What renters should know: layout, finishes, views, what is included in the rent.">{{ old('property_description', $listing->property_description) }}</textarea>
                                @error('property_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        @if($canEdit)<div class="mt-6"><button class="btn btn-primary">Save</button></div>@endif
                        </fieldset>
                    </form>
                </section>
            </div>

            <div class="col-lg-5">
                <section class="tenant-panel h-100" data-listing-photos>
                    <h2 class="fs-4 fw-bolder mb-1">Photos</h2>
                    <p class="text-muted fs-7 mb-5">{{ $listing->photos->count() }} of {{ $maxPhotos }}. JPEG, PNG or WebP, up to 8 MB each. The first photo is the cover.</p>
                    @if($listing->photos->isNotEmpty())
                        <div class="row g-3 mb-5">
                            @foreach($listing->photos as $photo)
                                <div class="col-6">
                                    <figure class="position-relative m-0">
                                        <img src="{{ $photo->url() }}" alt="Photo {{ $loop->iteration }} of {{ $listing->name }}" class="w-100 rounded" style="aspect-ratio:4/3;object-fit:cover;" loading="lazy">
                                        @if($loop->first)<figcaption class="badge badge-dark position-absolute top-0 start-0 m-2">Cover</figcaption>@endif
                                        @if($canEdit)
                                        <form method="post" action="{{ route('listings.photos.destroy', [$listing->id, $photo->id]) }}" class="position-absolute top-0 end-0 m-2">
                                            @csrf @method('delete')
                                            <button class="btn btn-sm btn-light" title="Remove this photo" aria-label="Remove photo {{ $loop->iteration }}">&times;</button>
                                        </form>
                                        @endif
                                    </figure>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if($canEdit && $listing->photos->count() < $maxPhotos)
                        <form method="post" action="{{ route('listings.photos.store', $listing->id) }}" enctype="multipart/form-data" data-listing-photo-form>
                            @csrf
                            <label for="listing_photos" class="form-label">Add photos</label>
                            <input id="listing_photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                            @error('photos')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('photos.*')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <button class="btn btn-light-primary mt-3">Upload</button>
                        </form>
                    @endif
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
