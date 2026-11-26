@extends('pages.business.layouts.app')
@section('content')
<div class="content"><div class="container-xxl py-7 property-flow" id="kt_content_container" data-property-flow>
    <a href="{{ route('complex.index') }}">← Property portfolio</a>
    <h1 class="mt-5">Add a property</h1>
    <p class="text-muted">Start with the property itself. Save this draft, then prepare units, amenities, charges and documents in separate steps.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form data-live-form method="POST" action="{{ route('complex.workflow.draft') }}" class="card"><div class="card-body">
        @csrf
        <section class="property-form-section"><h2>1. Identity & responsibility</h2>
        <div class="row g-5 mb-7">
            @foreach(['title' => 'Property name', 'ref_code' => 'Your property reference', 'email' => 'Management email', 'mobile' => 'Management phone'] as $key => $label)
                <div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ $label }}</label><input required maxlength="255" class="form-control" id="{{ $key }}" name="{{ $key }}" type="{{ $key === 'email' ? 'email' : 'text' }}" value="{{ old($key) }}"></div>
            @endforeach
            <div class="col-md-6"><label for="principal" class="form-label">Your relationship to this property</label><select required id="principal" name="principal" class="form-select"><option value="">Select relationship</option><option value="1" @selected(old('principal') === '1')>Owner / principal</option><option value="0" @selected(old('principal') === '0')>Managing on behalf of an owner</option></select></div>
        </div>
        </section><section class="property-form-section"><h2>2. What does this property contain?</h2>
        <p class="text-muted mb-4" id="kinds-help">Choose everything it holds. A single house is a property with one house; an estate or compound can mix houses, apartments and shops.</p>
        <fieldset class="row g-4 mb-7" aria-describedby="kinds-help"><legend class="visually-hidden">Property contents</legend>
            @foreach([
                'apartments' => ['Apartments', 'Flats in one or more blocks. Generate them from a unit layout, then give each its number and floor.', 'bi-building'],
                'houses' => ['Houses', 'Bungalows, maisonettes, villas, townhouses and so on. Each house can be rented out, sold, or either.', 'bi-house-door'],
                'commercial' => ['Commercial spaces', 'Shops, offices and other spaces, added one by one with their area.', 'bi-shop'],
            ] as $value => [$title, $help, $icon])
                <div class="col-md-4"><label class="property-choice border rounded p-5 d-block h-100"><input type="checkbox" name="kinds[]" value="{{ $value }}" @checked(in_array($value, (array) old('kinds', []), true))> <i class="bi {{ $icon }} ms-1 me-1" aria-hidden="true"></i><strong>{{ $title }}</strong><span class="text-muted d-block mt-3">{{ $help }}</span></label></div>
            @endforeach
        </fieldset>
        </section><section class="property-form-section"><h2>3. Location & description</h2>
        <div class="row g-5">
            @foreach(['county' => 'County', 'county_specific' => 'Area / neighbourhood'] as $key => $label)
                <div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ $label }}</label><input required class="form-control" id="{{ $key }}" name="{{ $key }}" value="{{ old($key) }}" @if(in_array($key, ['latitude', 'longitude'])) type="number" step="any" @endif></div>
            @endforeach
            @include('pages.business.complex.partials.location-picker')
            @foreach(['location_description' => 'Directions / physical address', 'description' => 'Property description'] as $key => $label)
                <div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ $label }}</label><textarea required class="form-control" rows="3" id="{{ $key }}" name="{{ $key }}">{{ old($key) }}</textarea></div>
            @endforeach
        </div>
        </section><section class="property-form-section"><h2>4. Team &amp; responsibility</h2>
        <p class="text-muted">Optional. You can change these later from the property page.</p>
        <div class="row g-5">
            @if($canAssignManagers)
            <div class="col-md-6">
                <label class="form-label" for="draft-managers">Team members who manage it</label>
                @if($assignableMembers->isEmpty())
                    <p class="text-muted fs-7 mb-0">There are no active team members yet. Invite them from <a href="{{ route('business.team.index') }}" data-flow-link>Team &amp; roles</a>.</p>
                @else
                <select class="form-select" id="draft-managers" name="manager_ids[]" multiple data-draft-managers data-placeholder="Choose team members">
                    @foreach($assignableMembers as $member)
                        <option value="{{ $member->id }}" @selected(in_array($member->id, (array) old('manager_ids', [])))>{{ trim($member->first_name.' '.$member->last_name) }} — {{ $member->businessRole->name ?? 'No role' }}{{ $member->businessRole?->limitedToAssignedProperties() ? ' (assigned properties only)' : '' }}</option>
                    @endforeach
                </select>
                <div class="form-text">Members whose role is limited to assigned properties will see this property. Others see the whole portfolio; this records who is responsible.</div>
                @endif
            </div>
            @endif
            <div class="col-md-6">
                <label class="form-label" for="draft-agent">Responsible agent</label>
                @include('pages.business.agents.partials.picker', ['name' => 'assigned_agent_id', 'id' => 'draft-agent', 'selected' => null, 'query' => ['signin' => 1], 'placeholder' => 'Search agents who sign in'])
                <div class="form-text">The lead agent for this property. New leads go to them, and they can hand units to agents who report to them. Only agents who sign in can be chosen.</div>
            </div>
        </div>
        </section><div class="property-save-bar justify-content-between"><a href="{{ route('complex.index') }}" class="btn btn-light">Cancel</a><button type="submit" class="btn btn-primary">Save draft & start setup</button></div>
    </div></form>
</div></div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const managers = document.querySelector('[data-draft-managers]');
        if (managers && window.jQuery && jQuery.fn.select2) {
            jQuery(managers).select2({ width: '100%', placeholder: managers.dataset.placeholder })
                .on('change', () => managers.dispatchEvent(new Event('input', { bubbles: true })));
        }
    });
</script>
@endsection
