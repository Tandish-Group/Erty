<section class="property-summary" data-property-summary aria-label="Property summary">
    <div class="property-summary-main">
        <div class="property-identity">
            <span class="property-kind">{{ $compositionLabel }}</span>
            <h1>{{ $complex->title }}</h1>
            <p class="property-address">{{ collect([$complex->county_specific, $complex->county])->filter()->implode(', ') ?: 'Location not recorded' }}<span aria-hidden="true"> · </span><span>Ref. {{ $complex->ref_code ?: 'Not recorded' }}</span></p>
        </div>
        <div class="property-primary-actions">
            @if($canEditInventory)<a href="#settings" data-property-tab class="btn btn-light">Edit property</a>@endif
            @unless($unitOnlyView ?? false)
            <a href="{{ route('complex.setup', $complex->slug) }}" class="btn btn-primary">{{ $setupReady ? 'View setup review' : 'Continue setup' }} <span aria-hidden="true">→</span></a>
            @endunless
        </div>
    </div>
    <div class="property-setup-strip {{ $setupReady ? 'is-ready' : '' }}">
        <span class="property-status-dot" aria-hidden="true"></span>
        <strong>{{ $setupReady ? 'Setup confirmed' : 'Setup needs review' }}</strong>
        <span>{{ $setupReady ? 'Property setup has been reviewed. Check unit availability before leasing.' : (app(\App\Services\Access\ModuleService::class)->enabled('listings') ? 'Complete the property review before publishing listings or onboarding tenants.' : 'Complete the property review before onboarding tenants.') }}</span>
    </div>
    <dl class="property-metrics">
        <div><dt>Total units</dt><dd>{{ number_format($residentialCount + $commercialCount) }}</dd><span>Physical inventory</span></div>
        @if($hasApartments || $apartmentCount)<div><dt>{{ $hasHouses ? 'Apartments' : 'Residential' }}</dt><dd>{{ number_format($apartmentCount) }}</dd><a href="#residential_units" data-property-tab>View {{ $hasHouses ? 'apartments' : 'residential units' }} <span aria-hidden="true">↗</span></a></div>@endif
        @if($hasHouses)<div><dt>Houses</dt><dd>{{ number_format($houseCount) }}</dd><a href="#houses" data-property-tab>View houses <span aria-hidden="true">↗</span></a></div>@endif
        @if($hasCommercial)<div><dt>Commercial</dt><dd>{{ number_format($commercialCount) }}</dd><a href="#commercial_units" data-property-tab>View commercial spaces <span aria-hidden="true">↗</span></a></div>@endif
        @module('listings')
        @php $publishedHere = $listings->where('status', \App\Models\Listing::PUBLISHED)->count(); @endphp
        <div><dt>Listings</dt><dd>{{ number_format($listings->count()) }}</dd><a href="#listings" data-property-tab>{{ $publishedHere }} published · {{ array_sum($listingAvailability) }} units free <span aria-hidden="true">↗</span></a></div>
        @endmodule
    </dl>
</section>
