@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Transfer ' . $movement->ref])
@php
    use App\Http\Controllers\Business\Tenancies\TenantMovementController as Transfers;
    [$statusLabel, $tone] = Transfers::STATUSES[$movement->status] ?? [ucfirst($movement->status), 'secondary'];
    $cur = $movement->currency;
    $who = fn ($id) => ($u = $people[$id] ?? null) ? (trim($u->first_name . ' ' . $u->last_name) ?: $u->email) : 'someone no longer on the team';
    $canAct = can_open('/tenant_management/movement/' . $movement->ref . '/approve', 'POST');
    $open = in_array($movement->status, ['pending', 'approved'], true);
    $lease = $movement->lease_data ?? [];
    $oldUrl = tenancy_profile_url($movement->oldTenancy);
    $newUrl = tenancy_profile_url($movement->newTenancy);
    $proration = (float) $movement->proration_amount;
    $parking = ['keep' => 'Keeps slot ' . ($parkingLabels[$movement->old_parking_slot_id] ?? ''), 'release' => 'Gives up slot ' . ($parkingLabels[$movement->old_parking_slot_id] ?? ''),
        'replace' => 'Moves from slot ' . ($parkingLabels[$movement->old_parking_slot_id] ?? '') . ' to ' . ($parkingLabels[$movement->new_parking_slot_id] ?? 'a new slot'), 'none' => null][$movement->parking_action ?? 'none'] ?? null;
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace transfers-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="{{ route('tenant_movement.index') }}">Unit transfers</a> · {{ $movement->ref }}</span>
                <h1>{{ $movement->tenantProfile?->display_name ?? 'Tenant' }}: {{ $movement->from_unit_label }} <i class="fas fa-arrow-right transfer-arrow" aria-label="to"></i> {{ $movement->to_unit_label }}</h1>
                <p><span class="badge badge-light-{{ $tone }} me-2">{{ $statusLabel }}</span>
                    {{ $movement->oldTenancy?->complex?->title ?? $movement->oldTenancy?->complex_name }} ·
                    {{ $movement->status === 'completed' ? 'Moved on' : 'Moves on' }} {{ $movement->transfer_date?->format('j M Y') }}
                    ({{ $movement->move_type === 'mid_month' ? 'mid-period, rent prorated' : 'start of a billing period' }})</p>
            </div>
            <div class="tenant-heading-actions"><a href="{{ route('tenant_movement.index') }}" class="btn btn-light">Back to transfers</a></div>
        </header>

        @include('pages.business.tenant.movement.partials.flash')

        <div class="transfer-layout">
            <div class="transfer-main">
                <section class="tenant-panel tenant-panel--padded mb-5">
                    <h2 class="report-section-title">The move</h2>
                    <dl class="transfer-facts">
                        <div><dt>From</dt><dd>Unit {{ $movement->from_unit_label }}@if($oldUrl)<small><a href="{{ $oldUrl }}">Tenancy {{ $movement->oldTenancy->tenancy_ref }}</a>{{ $movement->status === 'completed' ? ' (ended)' : '' }}</small>@endif</dd></div>
                        <div><dt>To</dt><dd>Unit {{ $movement->to_unit_label }}@if($newUrl)<small><a href="{{ $newUrl }}">Tenancy {{ $movement->newTenancy->tenancy_ref }}</a> (new)</small>@elseif($movement->status !== 'completed')<small>A new tenancy opens on the move date</small>@endif</dd></div>
                        <div><dt>Rent, a month</dt><dd>{{ money($movement->new_rent, $cur) }}<small>was {{ money($movement->old_rent, $cur) }}</small></dd></div>
                        <div><dt>Deposit</dt><dd>{{ money($movement->new_deposit, $cur) }}<small>
                            @if($movement->deposit_adjustment_type === 'top_up'){{ money($movement->deposit_adjustment, $cur) }} top-up billed on the move
                            @elseif($movement->deposit_adjustment_type === 'refund'){{ money($movement->deposit_adjustment, $cur) }} returned as credit (up to what is held)
                            @else unchanged @endif</small></dd></div>
                        @if($movement->move_type === 'mid_month')
                            <div><dt>Rent adjustment</dt><dd>
                                @if($proration > 0){{ money($proration, $cur) }}<small>invoiced: the new rent for the rest of the period is higher</small>
                                @elseif($proration < 0){{ money(abs($proration), $cur) }}<small>credited: the new rent for the rest of the period is lower</small>
                                @else None<small>same rent for the rest of the period</small>@endif</dd></div>
                        @endif
                        @if($movement->new_unit_type === 'commercial' && isset($lease['occupied_sq_area']))
                            <div><dt>Area</dt><dd>{{ number_format((float) $lease['occupied_sq_area']) }} sq ft<small>at {{ money($lease['rate_per_sq_ft'] ?? 0, $cur) }} per sq ft @if(!empty($lease['service_charge_per_sq_ft'])), service charge {{ money($lease['service_charge_per_sq_ft'], $cur) }} per sq ft @endif</small></dd></div>
                        @endif
                        @if(!empty($lease['furnishing']) || !empty($lease['number_of_occupants']))
                            <div><dt>Occupancy</dt><dd>{{ collect([$lease['furnishing'] ?? null, !empty($lease['number_of_occupants']) ? $lease['number_of_occupants'] . ' ' . \Illuminate\Support\Str::plural('occupant', (int) $lease['number_of_occupants']) : null])->filter()->implode(' · ') }}</dd></div>
                        @endif
                        <div><dt>Lease</dt><dd>{{ !empty($lease['lease_end_date']) ? 'Fixed term to ' . \Carbon\Carbon::parse($lease['lease_end_date'])->format('j M Y') : 'Month to month' }}@if(!empty($lease['special_terms']))<small>{{ $lease['special_terms'] }}</small>@endif</dd></div>
                        @if($parking)<div><dt>Parking</dt><dd>{{ $parking }}</dd></div>@endif
                        @if($movement->arrears_amount > 0)<div><dt>Arrears when requested</dt><dd>{{ money($movement->arrears_amount, $cur) }}<small>{{ $movement->arrears_acknowledged ? 'Acknowledged by the requester' : '' }}; they stay on the account</small></dd></div>@endif
                    </dl>
                    @if($movement->notes)<div class="transfer-notes"><strong>Notes</strong><p>{{ $movement->notes }}</p></div>@endif
                </section>

                <section class="tenant-panel tenant-panel--padded mb-5">
                    <h2 class="report-section-title">History</h2>
                    <ol class="transfer-timeline">
                        <li><strong>Requested</strong> by {{ $who($movement->initiated_by) }}<small>{{ $movement->created_at?->format('j M Y, H:i') }}</small></li>
                        @if($movement->approved_by)<li><strong>Approved</strong> by {{ $who($movement->approved_by) }}@if($movement->status === 'approved')<small>Carried out by the nightly billing run on {{ $movement->transfer_date?->format('j M Y') }}</small>@endif</li>@endif
                        @if($movement->executed_at)<li class="is-done"><strong>Completed</strong>: the tenant moved to unit {{ $movement->to_unit_label }}<small>{{ $movement->executed_at->format('j M Y, H:i') }}</small></li>@endif
                        @if($movement->status === 'cancelled')<li class="is-cancelled"><strong>Cancelled</strong> by {{ $who($movement->cancelled_by) }}<small>{{ $movement->updated_at?->format('j M Y, H:i') }}@if($movement->cancellation_reason): {{ $movement->cancellation_reason }}@endif</small></li>@endif
                    </ol>
                </section>
            </div>

            <aside class="transfer-side">
                @if($open && $canAct)
                    <section class="tenant-panel tenant-panel--padded mb-5">
                        @if($movement->status === 'pending')
                            <h2 class="report-section-title">Approve</h2>
                            @if($arrears && $arrears['type'] === 'block')
                                <p class="text-danger small">{{ $arrears['message'] }}</p>
                            @else
                                <p class="report-section-desc">
                                    @if($movement->transfer_date && $movement->transfer_date->isFuture())
                                        Approving schedules the move for {{ $movement->transfer_date->format('j M Y') }}. Nothing changes until then.
                                    @else
                                        Approving moves the tenant now: the old tenancy ends, a new one starts, and the rent and deposit changes are billed.
                                    @endif
                                </p>
                                @if($account)<p class="small text-muted mb-3">The tenant's balance is {{ money(abs($account['balance']), $cur) }} {{ $account['balance'] > 0 ? 'owed' : ($account['balance'] < 0 ? 'in credit' : '') }}; it moves with them.</p>@endif
                                <form method="post" action="{{ route('tenant_movement.approve', $movement->ref) }}" data-confirm="Approve transfer {{ $movement->ref }}?">
                                    @csrf
                                    <button class="btn btn-primary w-100">{{ $movement->transfer_date && $movement->transfer_date->isFuture() ? 'Approve and schedule' : 'Approve and move now' }}</button>
                                </form>
                            @endif
                            <hr class="my-5">
                        @endif
                        <h2 class="report-section-title">Cancel</h2>
                        <form method="post" action="{{ route('tenant_movement.cancel', $movement->ref) }}">
                            @csrf
                            <label for="tr_reason" class="form-label small">Reason</label>
                            <textarea id="tr_reason" name="reason" rows="2" maxlength="500" class="form-control mb-3" required placeholder="Why the transfer is not going ahead">{{ old('reason') }}</textarea>
                            <button class="btn btn-light-danger w-100">Cancel the transfer</button>
                        </form>
                    </section>
                @elseif($open)
                    <section class="tenant-panel tenant-panel--padded mb-5"><p class="mb-0 small text-muted">Approving or cancelling a transfer needs the Tenants → Transfers right.</p></section>
                @endif
            </aside>
        </div>
    </div>
</div>
@endsection
