@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Maintenance'])
@php
    $tabs = ['open' => 'Open', 'mine' => 'Assigned to me', 'completed' => 'Awaiting sign-off', 'closed' => 'Closed', 'all' => 'All'];
    $priorityBadge = ['urgent' => 'badge-danger', 'high' => 'badge-light-danger', 'normal' => 'badge-light-primary', 'low' => 'badge-light'];
    $statusBadge = ['open' => 'badge-light-warning', 'assigned' => 'badge-light-primary', 'in_progress' => 'badge-light-info', 'completed' => 'badge-light-success', 'closed' => 'badge-light-dark', 'cancelled' => 'badge-light'];
    $tenantName = fn ($t) => $t?->tenantProfile ? ($t->tenantProfile->tenant_type === 'Business' ? ($t->tenantProfile->business_name ?: $t->tenantProfile->tenant_name) : $t->tenantProfile->tenant_name) : null;
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div><span class="tenant-eyebrow">Properties</span><h1>Maintenance</h1><p>Repairs reported at your properties, who is handling them and where each one stands. Costs are booked as property expenses when a request is closed.</p></div>
            <div class="tenant-heading-actions">
                @if($canLog)<a href="{{ route('maintenance.create') }}" class="btn btn-primary" data-maintenance-new><i class="fas fa-plus me-1" aria-hidden="true"></i>Log a request</a>@endif
            </div>
        </header>

        <div class="tenant-metrics mb-7" data-maintenance-counts>
            <div><span>Open</span><strong>{{ $counts['open'] }}</strong><small>Reported, assigned or in progress</small></div>
            <div><span>Assigned to me</span><strong>{{ $counts['mine'] }}</strong><small>Waiting on you</small></div>
            <div><span>Awaiting sign-off</span><strong>{{ $counts['completed'] }}</strong><small>Done; a manager closes and books the cost</small></div>
            <div><span>Closed</span><strong>{{ $counts['closed'] }}</strong><small>Closed or cancelled</small></div>
        </div>

        <section class="tenant-panel">
            <div class="tenant-records-toolbar flex-wrap">
                <nav class="nav tenant-tabs" aria-label="Maintenance views">
                    @foreach($tabs as $key => $label)
                        <a class="nav-link {{ $filter === $key ? 'active' : '' }}" href="{{ route('maintenance.index', array_filter(['show' => $key, 'complex_id' => $complexFilter])) }}" @if($filter === $key) aria-current="page" @endif>{{ $label }}@if(isset($counts[$key]) && $counts[$key] > 0) <span class="badge badge-light ms-1">{{ $counts[$key] }}</span>@endif</a>
                    @endforeach
                </nav>
                @if($complexes->count() > 1)
                <form method="get" class="d-flex align-items-center gap-2 py-3">
                    <input type="hidden" name="show" value="{{ $filter }}">
                    <label for="mr_complex_filter" class="text-muted fs-7 text-nowrap">Property</label>
                    <select id="mr_complex_filter" name="complex_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All properties</option>
                        @foreach($complexes as $c)<option value="{{ $c->id }}" @selected((string) $complexFilter === (string) $c->id)>{{ $c->title }}</option>@endforeach
                    </select>
                </form>
                @endif
            </div>

            @if($requests->isEmpty())
                <div class="tenant-empty"><i class="fas fa-tools" aria-hidden="true"></i><h3>Nothing here</h3><p>{{ $filter === 'mine' ? 'No open requests are assigned to you.' : 'No maintenance requests match this view.' }}</p>@if($canLog && $filter === 'open')<a href="{{ route('maintenance.create') }}" class="btn btn-primary">Log a request</a>@endif</div>
            @else
            <div class="tenant-table-scroll mt-5" role="region" aria-label="Maintenance requests" tabindex="0">
                <table class="tenant-table" data-maintenance-table>
                    <thead><tr><th>Request</th><th>Where</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Reported</th><th></th></tr></thead>
                    <tbody>
                    @foreach($requests as $r)
                        <tr data-maintenance-row="{{ $r->reference }}">
                            <td><strong>{{ $r->title }}</strong><small>{{ $r->reference }} · {{ \App\Models\MaintenanceRequest::CATEGORIES[$r->category] ?? $r->category }}</small></td>
                            <td>{{ $r->complex?->title }}@if($r->unit_label) · {{ $r->unit_label }}@endif @if($name = $tenantName($r->tenancy))<small>{{ $name }}</small>@endif</td>
                            <td><span class="badge {{ $priorityBadge[$r->priority] ?? 'badge-light' }}">{{ \App\Models\MaintenanceRequest::PRIORITIES[$r->priority] ?? $r->priority }}</span></td>
                            <td><span class="badge {{ $statusBadge[$r->status] ?? 'badge-light' }}">{{ \App\Models\MaintenanceRequest::STATUSES[$r->status] ?? $r->status }}</span></td>
                            <td>{{ $r->assignee ? trim($r->assignee->first_name.' '.$r->assignee->last_name) : '—' }}</td>
                            <td class="text-nowrap">{{ $r->reported_at?->format('j M Y') }}<small>{{ $r->reported_at?->diffForHumans() }}</small></td>
                            <td class="text-end"><a class="btn btn-sm btn-light-primary" href="{{ route('maintenance.show', $r->reference) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $requests->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
