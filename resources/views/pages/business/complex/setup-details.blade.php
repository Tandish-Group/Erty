@if($inWorkspace ?? false)
<div class="mb-5">
@else
<details class="border rounded p-5 mb-5" open>
    <summary class="fw-bold">Edit property details</summary>
@endif
    <p class="text-muted mt-3">Save corrections here, then review the saved information. Saving does not confirm this section.</p>
    <form data-live-form @if($inWorkspace ?? false) data-refresh-details @endif method="POST" action="{{ route('complex.workflow.details', $complex->slug) }}">
        @csrf
        <input type="hidden" name="fingerprint" value="{{ $step['fingerprint'] }}">
        <div class="row g-4">
            @foreach(['title' => 'Property name', 'ref_code' => 'Property reference', 'email' => 'Management email', 'mobile' => 'Management phone', 'county' => 'County', 'county_specific' => 'Area / neighbourhood'] as $field => $label)
                <div class="col-md-6"><label class="form-label" for="details-{{ $field }}">{{ $label }}</label><input required id="details-{{ $field }}" name="{{ $field }}" class="form-control" value="{{ old($field, $complex->$field) }}" type="{{ $field === 'email' ? 'email' : (in_array($field, ['latitude', 'longitude']) ? 'number' : 'text') }}" @if(in_array($field, ['latitude', 'longitude'])) step="any" @endif></div>
            @endforeach
            <div class="col-12"><label class="form-label" for="details-principal">Management relationship</label><select class="form-select" id="details-principal" name="principal"><option value="1" @selected((string) old('principal', $complex->principal) === '1')>Owner / principal</option><option value="0" @selected((string) old('principal', $complex->principal) === '0')>Managing on behalf of an owner</option></select></div>
            @foreach(['location_description' => 'Directions / physical address', 'description' => 'Property description'] as $field => $label)
                <div class="col-md-6"><label class="form-label" for="details-{{ $field }}">{{ $label }}</label><textarea required rows="4" class="form-control" id="details-{{ $field }}" name="{{ $field }}">{{ old($field, $complex->$field) }}</textarea></div>
            @endforeach
            @include('pages.business.complex.partials.location-picker', ['locationId' => 'details-location'])
        </div>
        <div class="property-save-bar"><button class="btn btn-primary">Save property details</button><span class="text-muted" data-save-state>Changes need a new setup review.</span></div>
    </form>
@if($inWorkspace ?? false)</div>@else</details>@endif
