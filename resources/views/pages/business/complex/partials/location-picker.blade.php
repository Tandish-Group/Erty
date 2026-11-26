@php($locationId = $locationId ?? 'property-location')
<section class="property-location col-12" data-location-picker data-location-saved="{{ isset($complex) ? 'true' : 'false' }}" aria-label="Property map location">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div><h3 class="fs-5 mb-1">Pin the property on the map</h3><p class="text-muted mb-0">Click the building or drag the pin to its entrance. You can also enter exact coordinates below.</p></div>
        <button type="button" class="btn btn-sm btn-light-primary" data-locate>Use my current location</button>
    </div>
    <div class="property-location-map" data-location-map aria-label="Click to select the property location"></div>
    <p class="form-text mt-3" data-location-status role="status">Choose a location to record its latitude and longitude.</p>
    <div class="row g-4">
        @foreach(['latitude' => ['Latitude', -90, 90], 'longitude' => ['Longitude', -180, 180]] as $field => $coordinate)
            <div class="col-sm-6"><label for="{{ $locationId }}-{{ $field }}" class="form-label">{{ $coordinate[0] }}</label><input required type="number" step="any" min="{{ $coordinate[1] }}" max="{{ $coordinate[2] }}" id="{{ $locationId }}-{{ $field }}" name="{{ $field }}" value="{{ old($field, isset($complex) ? $complex->$field : '') }}" class="form-control" data-coordinate="{{ $field }}"></div>
        @endforeach
    </div>
    <noscript><p class="text-muted mt-3">Enable JavaScript to place a map pin, or enter the coordinates directly.</p></noscript>
</section>
