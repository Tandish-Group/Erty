@extends('pages.business.layouts.app')
@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Tenants'])
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
<div class="container-xxl" id="kt_content_container" data-tenant-dashboard>
    <header class="tenant-page-heading">
        <div><span class="tenant-eyebrow">Tenant workspace</span><h1>Every tenancy, in view.</h1><p>Manage who occupies your properties, their agreements and the work that follows.</p></div>
        <div class="tenant-heading-actions"><a href="/tenant_management/tenants" class="btn btn-light">All tenant profiles</a>
@businessCan('tenant_management', 'create')<a href="/tenant_management/onboarding/tenant" class="btn btn-primary">＋ Onboard tenant</a>@endbusinessCan
</div>
    </header>
    <div data-tenant-dashboard-summary>
        <div class="tenant-metrics">
            <div><span>Active tenancies</span><strong>{{ $stats['overview']['active_tenants'] }}</strong><small>{{ $stats['overview']['total_tenants'] }} agreements across all statuses</small></div>
            <div><span>Occupancy</span><strong>{{ $stats['occupancy']['occupancy_percentage'] }}<em>%</em></strong><small>Residential units + commercial capacity</small></div>
            <div><span>Ending within 30 days</span><strong>{{ $tenants->filter(fn ($tenancy) => $tenancy->status === 'Active' && $tenancy->lease_end_date && $tenancy->lease_end_date->between(now()->startOfDay(), now()->addDays(30)->endOfDay()))->count() }}</strong><small>Active tenancies with a scheduled end date</small></div>
            <div><span>Properties</span><strong>{{ $stats['overview']['total_complexes'] }}</strong><small>In your managed portfolio</small></div>
        </div>
        <div class="tenant-status-strip"><span>Tenancy status</span><span><i class="tenant-status-dot"></i>{{ $stats['lease_status']['active'] }} active</span><span>{{ $stats['lease_status']['pending'] }} pending</span><span>{{ $stats['lease_status']['overdue'] }} marked overdue</span><span>{{ $stats['lease_status']['terminated'] }} terminated</span></div>
    </div>
    <nav class="tenant-task-links" aria-label="Tenant tasks">
        @businessCan('tenant_management', 'billing')<a href="/tenant_management/billings"><i class="fas fa-file-invoice" aria-hidden="true"></i><span><strong>Billing & payments</strong><small>Periods, invoices and collections</small></span><span aria-hidden="true">↗</span></a>@endbusinessCan
        <a href="/tenant_management/movement"><i class="fas fa-exchange-alt" aria-hidden="true"></i><span><strong>Unit transfers</strong><small>Move an existing tenancy</small></span><span aria-hidden="true">↗</span></a>
        @businessCan('tenant_management', 'payments')<a href="/tenant_management/payment-reconciliation"><i class="fas fa-receipt" aria-hidden="true"></i><span><strong>POP reconciliation</strong><small>Match receipts to payments</small></span><span aria-hidden="true">↗</span></a>@endbusinessCan
        @businessCan('agent_management', 'view')<a href="{{ route('tenant_management.agent_commissions') }}"><i class="fas fa-user-tie" aria-hidden="true"></i><span><strong>Agent commissions</strong><small>Review calculated earnings</small></span><span aria-hidden="true">↗</span></a>@endbusinessCan
    </nav>
    <section class="tenant-panel">
        <div class="tenant-records-toolbar">
            <nav class="nav tenant-tabs" role="tablist" aria-label="Tenant records">
                <button class="nav-link active" id="tenancy-tab" data-bs-toggle="tab" data-bs-target="#tenancy" type="button" role="tab" aria-controls="tenancy" aria-selected="true">Tenancies</button>
                <button class="nav-link" id="lease-tab" data-bs-toggle="tab" data-bs-target="#lease" type="button" role="tab" aria-controls="lease" aria-selected="false">Leases</button>
                <button class="nav-link" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="false">Occupancy</button>
            </nav>
            <button type="button" class="btn btn-sm btn-light" data-tenant-refresh>Refresh data</button>
        </div>
        <p class="tenant-feedback" data-tenant-feedback role="status" hidden></p>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tenancy" role="tabpanel" aria-labelledby="tenancy-tab">
                <div class="tenant-section-heading"><div><h2>Recent tenancies</h2><p>A tenancy connects a tenant profile to a property and an agreement.</p></div><a href="/tenant_management/tenants">Browse all tenants →</a></div>
                <div data-tenant-recent>
                    @if($tenants->isEmpty())
                        <div class="tenant-empty"><i class="fas fa-house-user" aria-hidden="true"></i><h3>Your first tenancy starts here</h3><p>Choose a tenant, select an available property and confirm the agreement.</p>
@businessCan('tenant_management', 'create')<a href="/tenant_management/onboarding/tenant" class="btn btn-primary">Onboard a tenant</a>@endbusinessCan
</div>
                    @else
                        <div class="tenant-table-scroll" role="region" aria-label="Recent tenancies" tabindex="0"><table class="tenant-table"><thead><tr><th>Tenant</th><th>Property / unit</th><th>Agreement</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead><tbody>
                        @foreach($tenants->take(10) as $tenancy)
                            <tr><td><strong>{{ $tenancy->tenantProfile?->display_name ?? 'Tenant profile unavailable' }}</strong><small>{{ $tenancy->tenancy_ref }}</small></td><td>{{ $tenancy->complex?->title ?? $tenancy->complex_name }}<small>{{ $tenancy->unit_number ? 'Unit '.$tenancy->unit_number : 'Whole property' }}</small></td><td>{{ ucwords(str_replace('_', ' ', $tenancy->agreement_type ?? 'Agreement')) }}<small>{{ $tenancy->currency }} {{ number_format($tenancy->monthly_payment, 2) }} · {{ strtolower($tenancy->payment_frequency ?? 'monthly') }}</small></td><td><span class="tenant-badge {{ $tenancy->status === 'Active' ? 'is-active' : '' }}">{{ $tenancy->status }}</span></td><td><a class="tenant-row-link" href="{{ url('/tenant_management/tenancy/'.rawurlencode($tenancy->tenancy_ref).'/view') }}" aria-label="View tenancy {{ $tenancy->tenancy_ref }}">View →</a></td></tr>
                        @endforeach
                        </tbody></table></div><p class="tenant-table-note">Showing the latest {{ min(10, $tenants->count()) }} of {{ $tenants->count() }} tenancies. Browse tenant profiles for their full agreement history.</p>
                    @endif
                </div>
            </div>
            <div class="tab-pane fade" id="lease" role="tabpanel" aria-labelledby="lease-tab">
                <div class="tenant-section-heading"><div><h2>Lease agreements</h2><p>Review terms, end dates and upcoming renewals.</p></div><div><label class="visually-hidden" for="lease_search">Search leases</label><input type="search" id="lease_search" class="form-control" placeholder="Search tenant or property"></div></div>
                <div class="tenant-table-scroll"><table class="table tenant-data-table" id="kt_table_leases"><thead><tr><th>Tenant</th><th>Property</th><th>Lease period</th><th>Rent</th><th>Status</th><th>Expiry</th><th>Actions</th></tr></thead><tbody id="leases-table-body"></tbody></table></div>
            </div>
            <div class="tab-pane fade" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                <div class="tenant-section-heading"><div><h2>Property occupancy</h2><p>Capacity combines residential units and commercial subdivisions. Commercial occupancy is estimated from occupied area.</p></div><div><label class="visually-hidden" for="complex_search">Search properties</label><input type="search" id="complex_search" class="form-control" placeholder="Search properties"></div></div>
                <div class="tenant-table-scroll"><table class="table tenant-data-table" id="kt_table_complex_occupancy"><thead><tr><th>Property</th><th>Type</th><th>Capacity</th><th>Occupied</th><th>Occupancy</th><th>Commercial space</th><th>Monthly rent equivalent</th></tr></thead><tbody></tbody></table></div>
            </div>
        </div>
    </section>
    <section class="tenant-import-strip"><div><h2>Bringing existing records across?</h2><p>Upload tenant data, review staged records or export your information.</p></div><div class="tenant-heading-actions">
@businessCan('tenant_management', 'import')<button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#kt_modal_bulk_upload" data-upload-type="tenants">Import data</button>@endbusinessCan
@businessCan('tenant_management', 'import')<a href="/tenant_management/bulk-upload/mapping" class="btn btn-sm btn-light">Review imports</a>@endbusinessCan
@businessCan('tenant_management', 'export')<button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#kt_modal_export_data">Export</button>@endbusinessCan
</div></section>
    @include('pages.business.tenant.include.modals')
</div>
</div>
@endsection
