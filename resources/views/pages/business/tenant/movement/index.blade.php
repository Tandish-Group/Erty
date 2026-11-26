@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Unit transfers'])
@php
    use App\Http\Controllers\Business\Tenancies\TenantMovementController as Transfers;
    $tab = fn (string $status) => route('tenant_movement.index', array_filter(array_merge(request()->except('page', 'status'), ['status' => $status])));
    $total = $counts->sum();
    $canTransfer = can_open('/tenant_management/movement/create');
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace transfers-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="/tenant_management">Tenants</a> · Transfers</span>
                <h1>Unit transfers</h1>
                <p>Move a tenant to another unit in the same property. The tenant keeps their account, agent and billing cycle; the move ends the old tenancy and opens a new one on the transfer date.</p>
            </div>
            <div class="tenant-heading-actions">
                @if($canTransfer)<a href="{{ route('tenant_movement.create') }}" class="btn btn-primary"><i class="fas fa-exchange-alt me-1" aria-hidden="true"></i>Request a transfer</a>@endif
            </div>
        </header>

        @include('pages.business.tenant.movement.partials.flash')

        <nav class="docs-tabs mb-5" aria-label="Transfers by status">
            <a href="{{ $tab('') }}" class="docs-tab {{ $filters['status'] === '' ? 'is-active' : '' }}" @if($filters['status'] === '') aria-current="page" @endif><span>All</span><em>{{ $total }}</em></a>
            @foreach(Transfers::STATUSES as $key => [$label])
                <a href="{{ $tab($key) }}" class="docs-tab {{ $filters['status'] === $key ? 'is-active' : '' }}" @if($filters['status'] === $key) aria-current="page" @endif><span>{{ $label }}</span><em>{{ $counts[$key] ?? 0 }}</em></a>
            @endforeach
        </nav>

        @if(($counts['pending'] ?? 0) || $dueSoon)
            <p class="docs-note docs-note--warn"><i class="fas fa-info-circle" aria-hidden="true"></i>
                @if($counts['pending'] ?? 0){{ $counts['pending'] }} {{ \Illuminate\Support\Str::plural('transfer', $counts['pending']) }} waiting for approval.@endif
                @if($dueSoon) {{ $dueSoon }} approved {{ \Illuminate\Support\Str::plural('transfer', $dueSoon) }} {{ $dueSoon === 1 ? 'happens' : 'happen' }} in the next 7 days.@endif
            </p>
        @endif

        <form method="get" class="tenant-panel tenant-panel--padded report-filters mb-6" role="search">
            @if($filters['status'])<input type="hidden" name="status" value="{{ $filters['status'] }}">@endif
            <div class="docs-search"><label for="tr_q" class="form-label">Search</label>
                <input id="tr_q" type="search" name="q" value="{{ $filters['q'] }}" class="form-control form-control-sm" placeholder="Reference, tenant or unit"></div>
            <div><label for="tr_property" class="form-label">Property</label>
                <select id="tr_property" name="property" class="form-select form-select-sm">
                    <option value="">All properties</option>
                    @foreach($properties as $id => $title)<option value="{{ $id }}" @selected($filters['property'] === (int) $id)>{{ $title }}</option>@endforeach
                </select></div>
            <div class="report-filter-actions"><button class="btn btn-sm btn-primary">Show</button>@if($filters['q'] !== '' || $filters['property'])<a href="{{ route('tenant_movement.index', array_filter(['status' => $filters['status']])) }}" class="btn btn-sm btn-light">Reset</a>@endif</div>
        </form>

        <section class="tenant-panel tenant-panel--padded">
            @if($movements->isEmpty())
                <div class="tenant-empty"><i class="fas fa-exchange-alt" aria-hidden="true"></i>
                    @if($total === 0 && $filters['q'] === '' && !$filters['property'])
                        <h3>No transfers yet</h3><p>When a tenant moves to another unit in the same property, request a transfer: Erty works out the rent adjustment and deposit change, and keeps their account.</p>
                        @if($canTransfer)<a href="{{ route('tenant_movement.create') }}" class="btn btn-primary">Request a transfer</a>@endif
                    @else
                        <h3>No transfers match</h3><p>Try another status, search or property.</p>
                    @endif
                </div>
            @else
                <div class="tenant-table-scroll" role="region" aria-label="Unit transfers" tabindex="0">
                    <table class="tenant-table transfers-table">
                        <thead><tr><th>Transfer</th><th>Tenant</th><th>Move</th><th class="d-none d-md-table-cell">Date</th><th class="d-none d-lg-table-cell text-end">Rent</th><th>Status</th><th class="text-end"><span class="visually-hidden">Open</span></th></tr></thead>
                        <tbody>
                        @foreach($movements as $m)
                            @php [$statusLabel, $tone] = Transfers::STATUSES[$m->status] ?? [ucfirst($m->status), 'secondary']; @endphp
                            <tr>
                                <td><a href="{{ route('tenant_movement.show', $m->ref) }}" class="fw-semibold">{{ $m->ref }}</a><small class="d-block text-muted">Requested {{ $m->created_at?->format('j M Y') }}</small></td>
                                <td>{{ $m->tenantProfile?->display_name ?? '—' }}<small class="d-block text-muted">{{ $m->oldTenancy?->complex_name }}</small></td>
                                <td class="text-nowrap"><span class="transfer-units">{{ $m->from_unit_label }} <i class="fas fa-arrow-right" aria-label="to"></i> {{ $m->to_unit_label }}</span></td>
                                <td class="d-none d-md-table-cell text-nowrap">{{ $m->transfer_date?->format('j M Y') }}<small class="d-block text-muted">{{ $m->move_type === 'mid_month' ? 'Mid-period' : 'Next billing period' }}</small></td>
                                <td class="d-none d-lg-table-cell text-end text-nowrap">{{ money($m->new_rent, $m->currency) }}
                                    @if((float) $m->new_rent !== (float) $m->old_rent)<small class="d-block text-muted">was {{ money($m->old_rent, $m->currency) }}</small>@endif</td>
                                <td><span class="badge badge-light-{{ $tone }}">{{ $statusLabel }}</span></td>
                                <td class="text-end"><a href="{{ route('tenant_movement.show', $m->ref) }}" class="btn btn-sm btn-light">{{ $m->status === 'pending' && $canTransfer ? 'Review' : 'Open' }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">{{ $movements->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
