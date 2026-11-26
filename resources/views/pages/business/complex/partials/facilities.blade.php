@php
    $catalogue = config('property_facilities');
    $recordedCosts = $complexAmenities->filter(fn ($facility) => $facility->annual_price !== null && $facility->currency);
@endphp
<div data-facilities-panel data-facility-count="{{ $complexAmenities->count() }}" data-facility-refresh="{{ route('complex.show', $complex->slug) }}">
    <div class="property-section-heading facility-heading">
        <div><h2>Facilities & services</h2><p>Shared spaces, utilities and services available at {{ $complex->title }}.</p></div>
        @if($canEditInventory)<a class="btn btn-primary" href="{{ route('complex.amenities.create', $complex->slug) }}" data-facility-add>＋ Add facility</a>@endif
    </div>
    <div data-facility-notice role="status" hidden></div>
    @if($complexAmenities->isEmpty())
        <div class="facility-empty"><span class="facility-icon"><i class="fas fa-building" aria-hidden="true"></i></span><h3>What does this property provide?</h3><p>Add shared facilities such as parking, water supply or security. Their details help with property setup and tenant management.</p>@if($canEditInventory)<a href="{{ route('complex.amenities.create', $complex->slug) }}" data-facility-add class="btn btn-light-primary">Add the first facility</a>@endif</div>
    @else
        <div class="facility-summary">
            <div><span>Facilities recorded</span><strong>{{ $complexAmenities->count() }}</strong></div>
            <div><span>Facility types</span><strong>{{ $complexAmenities->pluck('amenity_type')->unique()->count() }}</strong></div>
            <div class="facility-cost-summary"><span>Recorded annual costs</span>@forelse($recordedCosts->groupBy('currency') as $currency => $records)<strong>{{ $currency }} {{ number_format($records->sum('annual_price'), 2) }}</strong>@empty<strong>Not recorded</strong>@endforelse<small>{{ $recordedCosts->count() }} of {{ $complexAmenities->count() }} facilities costed · tenant charges managed separately</small></div>
        </div>
        <div class="facility-filter"><label for="facility-filter">Show facilities</label><select id="facility-filter" class="form-select" data-facility-filter><option value="">All types</option>@foreach($complexAmenities->groupBy('amenity_type') as $key => $records)<option value="{{ $key }}">{{ $catalogue[$key]['label'] ?? ucwords(str_replace('_', ' ', $key)) }} ({{ $records->count() }})</option>@endforeach</select></div>
        <div class="facility-table-scroll" role="region" aria-label="Facility records" tabindex="0">
            <table class="facility-table">
                <caption class="visually-hidden">Facilities grouped by type. Expand or collapse each type to view its records.</caption>
                <thead><tr><th scope="col">Facility / details</th><th scope="col">Records</th><th scope="col">Annual cost</th><th scope="col" class="text-end">Actions</th></tr></thead>
                @foreach($complexAmenities->groupBy('amenity_type') as $type => $facilities)
                    @php
                        $definition = $catalogue[$type] ?? ['label' => ucwords(str_replace('_', ' ', $type)), 'group' => 'Facility', 'icon' => 'building', 'fields' => []];
                        $costs = $facilities->filter(fn ($facility) => $facility->annual_price !== null && $facility->currency)->groupBy('currency');
                    @endphp
                    <tbody data-facility-group="{{ $type }}">
                        <tr class="facility-group-row">
                            <th scope="row"><button class="facility-group-toggle" type="button" data-facility-toggle aria-expanded="true" aria-controls="{{ $facilities->map(fn ($facility) => 'facility-record-'.$facility->id)->implode(' ') }}"><span class="facility-chevron" aria-hidden="true">⌄</span><span class="facility-icon"><i class="fas fa-{{ $definition['icon'] }}" aria-hidden="true"></i></span><span><strong>{{ $definition['label'] }}</strong><small>{{ $definition['group'] }}</small></span></button></th>
                            <td><span class="facility-record-count">{{ $facilities->count() }}</span></td>
                            <td>@forelse($costs as $currency => $records)<span class="facility-table-cost">{{ $currency }} {{ number_format($records->sum('annual_price'), 2) }}</span>@empty<span class="facility-cost-unknown">Not recorded</span>@endforelse</td>
                            <td></td>
                        </tr>
                        @foreach($facilities as $facility)
                            <tr class="facility-record-row" data-facility-record id="facility-record-{{ $facility->id }}">
                                <td colspan="2"><div class="facility-nested-record"><h3>{{ $definition['label'] }}@if($facilities->count() > 1) · {{ $loop->iteration }}@endif</h3><dl class="facility-facts">
                                    @php($hasDetails = false)
                                    @foreach($definition['fields'] as $field => $meta)
                                        @if($facility->$field !== null && $facility->$field !== '')
                                            @php($hasDetails = true)
                                            <div><dt>{{ $meta['label'] }}</dt><dd>{{ $meta['options'][$facility->$field] ?? $facility->$field }}</dd></div>
                                        @endif
                                    @endforeach
                                </dl>@unless($hasDetails)<p class="facility-no-details">No additional details recorded.</p>@endunless</div></td>
                                <td><span class="{{ $facility->annual_price !== null ? 'facility-table-cost' : 'facility-cost-unknown' }}">{{ $facility->annual_price !== null ? ($facility->currency ? $facility->currency.' ' : '').number_format($facility->annual_price, 2) : 'Not recorded' }}</span></td>
                                <td>@if($canEditInventory)<div class="facility-actions"><button class="btn btn-sm btn-light" type="button" data-facility-edit="{{ route('complex.amenities.edit', $facility->id) }}" data-facility-update="{{ route('complex.amenities.update', $facility->id) }}" aria-label="Edit {{ $definition['label'] }}">Edit</button><button class="btn btn-sm facility-remove" type="button" data-facility-delete="{{ $facility->id }}" data-facility-label="{{ $definition['label'] }}" data-facility-delete-url="{{ route('complex-amenities.single-delete') }}" aria-label="Remove {{ $definition['label'] }}">Remove</button></div>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
    @endif
</div>
