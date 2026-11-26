@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Request a transfer'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace transfers-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="{{ route('tenant_movement.index') }}">Unit transfers</a> · Step 1 of 2</span>
                <h1>Which tenancy is moving?</h1>
                <p>Choose the tenant who is moving to another unit in the same property. Only active tenancies, including those on notice, can move.</p>
            </div>
            <div class="tenant-heading-actions"><a href="{{ route('tenant_movement.index') }}" class="btn btn-light">Back to transfers</a></div>
        </header>

        @include('pages.business.tenant.movement.partials.flash')

        <form method="get" class="tenant-panel tenant-panel--padded report-filters mb-6" role="search">
            <div class="docs-search"><label for="tc_q" class="form-label">Search</label>
                <input id="tc_q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Tenant, unit or tenancy reference" autofocus></div>
            <div><label for="tc_property" class="form-label">Property</label>
                <select id="tc_property" name="property" class="form-select form-select-sm">
                    <option value="">All properties</option>
                    @foreach($properties as $id => $title)<option value="{{ $id }}" @selected($filters['property'] === (int) $id)>{{ $title }}</option>@endforeach
                </select></div>
            <div class="report-filter-actions"><button class="btn btn-sm btn-primary">Find</button>@if($filters['q'] !== '' || $filters['property'])<a href="{{ route('tenant_movement.create') }}" class="btn btn-sm btn-light">Reset</a>@endif</div>
        </form>

        <section class="tenant-panel tenant-panel--padded">
            @if($tenancies->isEmpty())
                <div class="tenant-empty"><i class="fas fa-user-friends" aria-hidden="true"></i>
                    <h3>{{ $filters['q'] !== '' || $filters['property'] ? 'No tenancies match' : 'No active tenancies' }}</h3>
                    <p>{{ $filters['q'] !== '' || $filters['property'] ? 'Try another name, unit or property.' : 'A transfer moves an active tenant between units. There are no active tenancies at the properties you manage.' }}</p>
                </div>
            @else
                <div class="tenant-table-scroll" role="region" aria-label="Tenancies" tabindex="0">
                    <table class="tenant-table">
                        <thead><tr><th>Tenant</th><th>Current unit</th><th class="d-none d-md-table-cell">Property</th><th class="d-none d-lg-table-cell text-end">Rent</th><th class="text-end"><span class="visually-hidden">Choose</span></th></tr></thead>
                        <tbody>
                        @foreach($tenancies as $t)
                            <tr>
                                <td>{{ $t->tenantProfile?->display_name ?? 'Tenant' }}<small class="d-block text-muted">{{ $t->tenancy_ref }}@if($t->status !== 'Active') · {{ $t->status }}@endif</small></td>
                                <td>{{ $t->unit_number ?: '—' }}<small class="d-block text-muted">{{ $t->commercial_unit_id ? 'Commercial' : 'Residential' }}</small></td>
                                <td class="d-none d-md-table-cell">{{ $t->complex?->title ?? $t->complex_name }}</td>
                                <td class="d-none d-lg-table-cell text-end text-nowrap">{{ money($t->monthly_payment, $t->currency) }}<small class="d-block text-muted">a month, billed {{ strtolower($t->payment_frequency ?: 'monthly') }}</small></td>
                                <td class="text-end">
                                    @if(isset($open[$t->id]))
                                        <a href="{{ route('tenant_movement.show', $open[$t->id]) }}" class="btn btn-sm btn-light" title="This tenancy already has a transfer in progress">Transfer in progress</a>
                                    @else
                                        <a href="{{ route('tenant_movement.create', ['tenancy_id' => $t->id]) }}" class="btn btn-sm btn-primary">Choose</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $tenancies->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
