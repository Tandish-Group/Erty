@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Log a maintenance request'])
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div><span class="tenant-eyebrow">Maintenance</span><h1>Log a request</h1><p>Record a repair reported by a tenant, found at an inspection or noticed by the team. Choose the unit and the current tenant is linked automatically.</p></div>
            <div class="tenant-heading-actions"><a href="{{ $tenancy ? '/tenant_management/tenancy/'.urlencode($tenancy->tenancy_ref).'/view' : route('maintenance.index') }}" class="btn btn-light">← Back</a></div>
        </header>

        @if($complexes->isEmpty())
            <section class="tenant-panel"><div class="tenant-empty"><i class="fas fa-building" aria-hidden="true"></i><h3>No properties yet</h3><p>Add a property first; requests are logged against a property and unit.</p></div></section>
        @else
        <section class="tenant-panel pt-6">
            @if($errors->any())<div class="alert alert-danger fs-7" role="alert">{{ $errors->first() }}</div>@endif
            <form method="post" action="{{ route('maintenance.store') }}" data-maintenance-form>
                @csrf
                @if($tenancy)<input type="hidden" name="tenancy_id" value="{{ $tenancy->id }}">@endif
                <div class="row g-5">
                    <div class="col-md-6"><label class="form-label required" for="mr_complex">Property</label>
                        <select class="form-select" id="mr_complex" name="complex_id" required data-units-url="{{ url('/maintenance/units') }}" @disabled($tenancy)>
                            <option value="">Choose a property</option>
                            @foreach($complexes as $c)<option value="{{ $c->id }}" @selected(old('complex_id', $selectedComplex) == $c->id)>{{ $c->title }}</option>@endforeach
                        </select>
                        @if($tenancy)<input type="hidden" name="complex_id" value="{{ $tenancy->complex_id }}">@endif
                    </div>
                    <div class="col-md-6"><label class="form-label" for="mr_unit">Unit</label>
                        @if($tenancy)
                            <input type="text" class="form-control" value="{{ $tenancy->unit_number }} · {{ $tenancy->tenantProfile?->tenant_type === 'Business' ? $tenancy->tenantProfile?->business_name : $tenancy->tenantProfile?->tenant_name }}" disabled>
                        @else
                        <select class="form-select" id="mr_unit" name="unit">
                            <option value="">Common area / whole property</option>
                            @foreach($units as $u)<option value="{{ $u['value'] }}" @selected(old('unit') === $u['value'])>{{ $u['label'] }}</option>@endforeach
                        </select>
                        @endif
                    </div>
                    <div class="col-md-8"><label class="form-label required" for="mr_title">What needs fixing</label><input type="text" class="form-control" id="mr_title" name="title" maxlength="150" value="{{ old('title') }}" placeholder="e.g. Kitchen tap leaking" required></div>
                    <div class="col-md-4"><label class="form-label required" for="mr_category">Category</label>
                        <select class="form-select" id="mr_category" name="category">@foreach(\App\Models\MaintenanceRequest::CATEGORIES as $v => $l)<option value="{{ $v }}" @selected(old('category', 'other') === $v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label" for="mr_description">Details</label><textarea class="form-control" id="mr_description" name="description" rows="3" maxlength="3000">{{ old('description') }}</textarea></div>
                    <div class="col-md-4"><label class="form-label required" for="mr_priority">Priority</label>
                        <select class="form-select" id="mr_priority" name="priority">@foreach(\App\Models\MaintenanceRequest::PRIORITIES as $v => $l)<option value="{{ $v }}" @selected(old('priority', 'normal') === $v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="mr_reported_by">Reported by</label><input type="text" class="form-control" id="mr_reported_by" name="reported_by" maxlength="120" value="{{ old('reported_by', $tenancy?->tenantProfile?->tenant_name) }}" placeholder="e.g. the tenant"></div>
                    <div class="col-md-4"><label class="form-label" for="mr_reported_via">How it was reported</label>
                        <select class="form-select" id="mr_reported_via" name="reported_via"><option value="">—</option>@foreach(['phone' => 'Phone', 'email' => 'Email', 'visit' => 'In person', 'inspection' => 'Inspection', 'other' => 'Other'] as $v => $l)<option value="{{ $v }}" @selected(old('reported_via') === $v)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" for="mr_reported_at">Reported on</label><input type="datetime-local" class="form-control" id="mr_reported_at" name="reported_at" value="{{ old('reported_at', now()->format('Y-m-d\TH:i')) }}" max="{{ now()->format('Y-m-d\TH:i') }}"></div>
                    @if($canAssign)
                    <div class="col-md-8"><label class="form-label" for="mr_assignee">Assign to (optional)</label>
                        <select class="form-select" id="mr_assignee" name="assigned_user_id"><option value="">Not yet</option>@foreach($assignees as $a)<option value="{{ $a['id'] }}" @selected(old('assigned_user_id') == $a['id'])>{{ $a['name'] }}{{ $a['is_agent'] ? ' (agent)' : '' }}</option>@endforeach</select></div>
                    @endif
                </div>
                <div class="d-flex justify-content-end gap-3 mt-8">
                    <a href="{{ route('maintenance.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Log request</button>
                </div>
            </form>
        </section>
        @endif
    </div>
</div>
<script>
(function () {
    const complex = document.getElementById('mr_complex');
    const unit = document.getElementById('mr_unit');
    if (!complex || !unit) return;
    complex.addEventListener('change', function () {
        unit.innerHTML = '<option value="">Common area / whole property</option>';
        if (!complex.value) return;
        fetch(complex.dataset.unitsUrl + '/' + complex.value, {headers: {'Accept': 'application/json'}})
            .then(r => r.json()).then(json => (json.units || []).forEach(u => unit.add(new Option(u.label, u.value))));
    });
})();
</script>
@endsection
