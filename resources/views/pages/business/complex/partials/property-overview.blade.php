<div data-property-overview>
    <div class="property-overview-grid">
        <section class="property-panel property-profile" aria-labelledby="property-profile-heading">
            <div class="property-panel-heading"><h2 id="property-profile-heading">Property profile</h2>@if($canEditInventory)<a href="#settings" data-property-tab>Edit details <span aria-hidden="true">↗</span></a>@endif</div>
            <p class="property-description">{{ $complex->description ?: 'Add a description to help your team understand this property.' }}</p>
            <dl class="property-facts">
                <div><dt>Property composition</dt><dd>{{ collect(\App\Services\Properties\ComplexInventoryService::kindsOf($complex))->map(fn ($k) => \App\Services\Properties\ComplexInventoryService::KIND_LABELS[$k])->implode(', ') ?: 'Not chosen' }}</dd></div>
                <div><dt>Classification</dt><dd>{{ $complex->class ?: 'Not recorded' }}</dd></div>
                <div><dt>Management relationship</dt><dd>{{ $complex->principal === null ? 'Not recorded' : ($complex->principal ? 'Owner / principal' : 'Managed on behalf of owner') }}</dd></div>
                <div><dt>Property reference</dt><dd>{{ $complex->ref_code ?: 'Not recorded' }}</dd></div>
            </dl>
            <div class="property-contact">
                <h3>Management contact</h3>
                <dl class="property-facts">
                    <div><dt>Email address</dt><dd>@if($complex->email)<a href="mailto:{{ $complex->email }}">{{ $complex->email }}</a>@else Not recorded @endif</dd></div>
                    <div><dt>Phone number</dt><dd>@if($complex->mobile)<a href="tel:{{ preg_replace('/[^+0-9]/', '', $complex->mobile) }}">{{ $complex->mobile }}</a>@else Not recorded @endif</dd></div>
                <div class="property-fact-wide" data-property-management><dt>Management <a href="#assignments" data-property-tab class="btn btn-link btn-sm p-0 ms-2 align-baseline">Assignments</a></dt><dd>@if($complex->isCompanyManaged())<span class="badge badge-light-info">Company</span> {{ auth()->user()->business->business_name ?? config('app.name') }}@else<span class="badge badge-light-primary">Primary manager</span> {{ profile_link(agent_profile_url($complex->assignedAgent), $complex->assignedAgent?->full_name) }}@endif</dd></div>
                <div class="property-fact-wide" data-property-managers><dt>Property team @if($canAssignManagers)<button type="button" class="btn btn-link btn-sm p-0 ms-2 align-baseline" data-bs-toggle="modal" data-bs-target="#property-managers-modal">Change</button>@endif</dt><dd data-managers-list>@forelse($managers as $manager)<span class="badge badge-light-primary me-1 mb-1">{{ trim($manager->first_name.' '.$manager->last_name) }}</span>@empty<span class="property-muted">No team members assigned</span>@endforelse</dd></div>
                </dl>
            </div>
        </section>
        <section class="property-panel property-location-panel" aria-labelledby="property-map-heading">
            <div class="property-panel-heading"><h2 id="property-map-heading">Location</h2>@if($canEditInventory)<a href="#settings" data-property-tab>Update pin <span aria-hidden="true">↗</span></a>@endif</div>
            @php($hasCoordinates = is_numeric($complex->latitude) && is_numeric($complex->longitude) && abs((float) $complex->latitude) <= 90 && abs((float) $complex->longitude) <= 180)
            @if($hasCoordinates)
                <div class="property-overview-map" data-property-map data-lat="{{ $complex->latitude }}" data-long="{{ $complex->longitude }}" aria-label="Saved property location"></div>
            @else
                <div class="property-map-empty"><span aria-hidden="true">⌖</span><h3>Pin the property’s location</h3><p>Add a map pin in the property editor to record its exact location.</p>@if($canEditInventory)<a href="#settings" data-property-tab class="btn btn-light">Add location</a>@endif</div>
            @endif
            <div class="property-location-caption">
                <p>{{ $complex->location_description ?: 'Directions have not been recorded.' }}</p>
                @if($hasCoordinates)
                    <div><span>{{ number_format((float) $complex->latitude, 6) }}, {{ number_format((float) $complex->longitude, 6) }}</span><a href="https://www.openstreetmap.org/?mlat={{ $complex->latitude }}&mlon={{ $complex->longitude }}#map=18/{{ $complex->latitude }}/{{ $complex->longitude }}" target="_blank" rel="noopener noreferrer">Open map <span aria-hidden="true">↗</span></a></div>
                @endif
            </div>
        </section>
    </div>
    <section class="property-panel property-layouts" aria-labelledby="property-layouts-heading">
        <div class="property-panel-heading"><div><h2 id="property-layouts-heading">Unit layouts</h2><p>Recorded specifications for this property. Open an inventory tab to see individual units.</p></div>@unless($unitOnlyView ?? false)<a href="{{ route('complex.setup', [$complex->slug, 'step' => 'structure']) }}">Review inventory plan <span aria-hidden="true">↗</span></a>@endunless</div>
        @if($complex->complex_units->isNotEmpty())
            <div class="table-responsive"><table class="table property-layout-table"><thead><tr><th scope="col">Layout</th><th scope="col">Area per unit</th><th scope="col">Planned units</th><th scope="col">Details</th></tr></thead><tbody>
                @foreach($complex->complex_units as $specification)
                    <tr><td><strong>{{ $specification->unit_category === 'commercial_unit' ? ucfirst(str_replace('_', ' ', $specification->unit_type ?? 'Commercial space')) : ($specification->unit_category === 'house' ? $specification->layoutLabel() : ($specification->bedrooms ? $specification->bedrooms.' bedroom' : 'Studio')) }}</strong><span>{{ ['commercial_unit' => 'Commercial', 'house' => 'House · ' . (config('property.offers')[$specification->offer ?? 'rent'] ?? 'For rent')][$specification->unit_category] ?? 'Apartment' }}</span></td><td>{{ number_format($specification->sq_ft ?? 0) }} sq ft</td><td>{{ number_format($specification->unit_count ?? 0) }}</td><td>{{ $specification->unit_category === 'commercial_unit' ? ($specification->unit_subdivision ? $specification->unit_subdivision.' subdivisions' : 'No subdivision recorded') : ($specification->bathrooms ?? 0).' bathrooms' }}</td></tr>
                @endforeach
            </tbody></table></div>
        @else
            <div class="property-empty-inline"><span aria-hidden="true">▤</span><div><h3>No unit specifications recorded</h3><p>Residential layouts are added during setup. Commercial spaces can be entered individually.</p></div></div>
        @endif
    </section>
</div>
