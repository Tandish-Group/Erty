@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Request a transfer'])
@php
    use App\Http\Controllers\Business\Tenancies\TenantMovementController as Transfers;
    $name = $tenancy->tenantProfile?->display_name ?? 'The tenant';
    $blocked = $arrears['type'] === 'block';
    $currentLeaseEnd = $lease?->lease_end_date && $lease->lease_end_date->isFuture() ? $lease->lease_end_date->toDateString() : null;
    $frequency = strtolower($tenancy->payment_frequency ?: 'monthly');
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace transfers-page" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow"><a href="{{ route('tenant_movement.index') }}">Unit transfers</a> · Step 2 of 2</span>
                <h1>Move {{ $name }}</h1>
                <p>From unit <strong>{{ $tenancy->unit_number }}</strong> at {{ $tenancy->complex?->title ?? $tenancy->complex_name }} to another unit in the same property. The request waits for approval before anything changes.</p>
            </div>
            <div class="tenant-heading-actions"><a href="{{ route('tenant_movement.create') }}" class="btn btn-light">Choose another tenancy</a></div>
        </header>

        @include('pages.business.tenant.movement.partials.flash')

        <div class="tenant-metrics report-metrics mb-6" style="--report-cols: 4">
            <div><span>Current rent</span><strong>{{ money($tenancy->monthly_payment, $currency) }}</strong><small>a month, billed {{ $frequency }}</small></div>
            <div><span>Deposit held</span><strong>{{ money($account['deposit_held'], $currency) }}</strong><small>of {{ money($tenancy->security_deposit, $currency) }} agreed</small></div>
            <div><span>Balance</span><strong class="{{ $account['balance'] > 0 ? 'crm-figure-warn' : '' }}">{{ money(abs($account['balance']), $currency) }}</strong><small>{{ $account['balance'] > 0 ? 'owed by the tenant' : ($account['balance'] < 0 ? 'in credit' : 'nothing owed') }}@if($account['overdue'] > 0) · {{ money($account['overdue'], $currency) }} overdue @endif</small></div>
            <div><span>Tenancy</span><strong class="transfer-ref">{{ $tenancy->tenancy_ref }}</strong><small>{{ $tenancy->status }}@if(tenancy_profile_url($tenancy)) · <a href="{{ tenancy_profile_url($tenancy) }}">open</a>@endif</small></div>
        </div>

        @if($blocked)
            <div class="alert alert-danger"><strong>This tenant cannot be transferred yet.</strong> {{ $arrears['message'] }}</div>
        @elseif($units->isEmpty())
            <section class="tenant-panel">
                <div class="tenant-empty"><i class="fas fa-door-closed" aria-hidden="true"></i><h3>No free {{ $commercial ? 'commercial space' : 'units' }} at this property</h3>
                    <p>A transfer needs a {{ $commercial ? 'commercial unit with free area' : 'vacant unit that is not reserved, under maintenance or for sale only' }}. Free one up, or check the unit's status under the property.</p></div>
            </section>
        @else
        <form method="post" action="{{ route('tenant_movement.store') }}" class="transfer-layout" data-transfer-form data-preview-url="{{ route('tenant_movement.preview') }}" data-currency="{{ $currency }}" novalidate>
            @csrf
            <input type="hidden" name="tenancy_id" value="{{ $tenancy->id }}">
            <div class="transfer-main">
                <section class="tenant-panel tenant-panel--padded mb-5">
                    <h2 class="report-section-title">1. The new unit</h2>
                    <p class="report-section-desc">{{ $units->count() }} free {{ $commercial ? \Illuminate\Support\Str::plural('space', $units->count()) . ' with room' : \Illuminate\Support\Str::plural('unit', $units->count()) }} at this property.</p>
                    <div class="transfer-units-grid" role="radiogroup" aria-label="New unit">
                        @foreach($units as $unit)
                            <label class="transfer-unit">
                                <input type="radio" name="new_unit_id" value="{{ $unit['id'] }}" @checked((int) old('new_unit_id') === (int) $unit['id']) data-free-sqft="{{ $unit['remaining_sq_ft'] ?? '' }}" required>
                                <span>
                                    <strong>{{ $unit['label'] }}</strong>
                                    <small>
                                        @if($commercial)
                                            {{ number_format((float) ($unit['remaining_sq_ft'] ?? 0)) }} sq ft free @if($unit['unit_sq_ft']) of {{ number_format((float) $unit['unit_sq_ft']) }} @endif
                                            @if($unit['min_sq_ft']) · min {{ number_format((float) $unit['min_sq_ft']) }} sq ft @endif
                                        @else
                                            {{ collect([$unit['bedrooms'] !== null ? $unit['bedrooms'] . ' bed' : null, $unit['bathrooms'] !== null ? $unit['bathrooms'] . ' bath' : null, $unit['sq_area'] ? number_format((float) $unit['sq_area']) . ' sq ft' : null])->filter()->implode(' · ') ?: 'Vacant' }}
                                        @endif
                                    </small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </section>

                <section class="tenant-panel tenant-panel--padded mb-5">
                    <h2 class="report-section-title">2. When</h2>
                    <div class="transfer-choice">
                        <label><input type="radio" name="move_type" value="end_of_month" @checked(old('move_type', 'end_of_month') === 'end_of_month')>
                            <span><strong>At the start of the next billing period, {{ $nextPeriod->format('j M Y') }}</strong><small>No proration: the current period is already billed on the old unit, and rent for the new unit starts with the next period.</small></span></label>
                        <label><input type="radio" name="move_type" value="mid_month" @checked(old('move_type') === 'mid_month')>
                            <span><strong>On a date I choose</strong><small>The unused days of the old rent are set against the new rent for the rest of the period: an adjustment invoice if the tenant owes, credit if they are owed.</small></span></label>
                    </div>
                    <div class="transfer-date" data-mid-only @if(old('move_type') !== 'mid_month') hidden @endif>
                        <label for="tr_date" class="form-label">Move date</label>
                        <input id="tr_date" type="date" name="transfer_date" value="{{ old('transfer_date', today()->toDateString()) }}" min="{{ today()->toDateString() }}" max="{{ today()->addYear()->toDateString() }}" class="form-control">
                    </div>
                </section>

                <section class="tenant-panel tenant-panel--padded mb-5">
                    <h2 class="report-section-title">3. Rent and terms in the new unit</h2>
                    <p class="report-section-desc">Billing continues on the same cycle ({{ $frequency }}, due on day {{ $tenancy->payment_due_day ?: 1 }}), with the same late fee and grace period.</p>
                    <div class="transfer-fields">
                        @if($commercial)
                            <div><label for="tr_area" class="form-label required">Area taken (sq ft)</label>
                                <input id="tr_area" type="number" name="occupied_sq_area" step="0.01" min="0" value="{{ old('occupied_sq_area', $tenancy->occupied_sq_area) }}" class="form-control" data-calc required>
                                <small class="form-text" data-free-note></small></div>
                            <div><label for="tr_rate" class="form-label required">Rent per sq ft, a month</label>
                                <input id="tr_rate" type="number" name="rate_per_sq_ft" step="0.01" min="0" value="{{ old('rate_per_sq_ft', $tenancy->rate_per_sq_ft) }}" class="form-control" data-calc required></div>
                            <div><label for="tr_sc" class="form-label">Service charge per sq ft</label>
                                <input id="tr_sc" type="number" name="service_charge_per_sq_ft" step="0.01" min="0" value="{{ old('service_charge_per_sq_ft', $tenancy->service_charge_per_sq_ft) }}" class="form-control">
                                <small class="form-text">Billed separately from rent, as now.</small></div>
                        @else
                            <div><label for="tr_rent" class="form-label required">New rent, a month</label>
                                <div class="input-group"><span class="input-group-text">{{ $currency }}</span><input id="tr_rent" type="number" name="new_rent" step="0.01" min="0" value="{{ old('new_rent', $tenancy->monthly_payment) }}" class="form-control" required></div></div>
                            <div><label for="tr_furnishing" class="form-label">Furnishing</label>
                                <select id="tr_furnishing" name="furnishing" class="form-select">
                                    @foreach(Transfers::FURNISHING as $option)<option value="{{ $option }}" @selected(old('furnishing', $tenancy->furnishing ?: 'Unfurnished') === $option)>{{ $option }}</option>@endforeach
                                </select></div>
                        @endif
                        <div><label for="tr_deposit" class="form-label required">Deposit for the new unit</label>
                            <div class="input-group"><span class="input-group-text">{{ $currency }}</span><input id="tr_deposit" type="number" name="new_deposit" step="0.01" min="0" value="{{ old('new_deposit', $tenancy->security_deposit) }}" class="form-control" required></div>
                            <small class="form-text">A higher deposit is billed as a top-up; a lower one is returned as credit.</small></div>
                        <div><label for="tr_occupants" class="form-label">Occupants</label>
                            <input id="tr_occupants" type="number" name="number_of_occupants" min="1" max="100" value="{{ old('number_of_occupants', $tenancy->number_of_occupants) }}" class="form-control"></div>
                        <div><label for="tr_end" class="form-label">Lease ends</label>
                            <input id="tr_end" type="date" name="lease_end_date" value="{{ old('lease_end_date', $currentLeaseEnd) }}" min="{{ today()->addDay()->toDateString() }}" class="form-control">
                            <small class="form-text">Leave empty for a month-to-month lease.</small></div>
                    </div>
                    <div class="mt-4"><label for="tr_terms" class="form-label">Special terms</label>
                        <textarea id="tr_terms" name="special_terms" rows="2" maxlength="2000" class="form-control" placeholder="Anything particular to the new unit">{{ old('special_terms', $lease?->special_terms) }}</textarea></div>
                </section>

                @if($slot)
                    <section class="tenant-panel tenant-panel--padded mb-5">
                        <h2 class="report-section-title">4. Parking</h2>
                        <p class="report-section-desc">The tenant has parking slot <strong>{{ $slot->parking_number ?? '#' . $slot->id }}</strong>.</p>
                        <div class="transfer-choice">
                            <label><input type="radio" name="parking_action" value="keep" @checked(old('parking_action', 'keep') === 'keep')><span><strong>Keep the same slot</strong></span></label>
                            <label><input type="radio" name="parking_action" value="release" @checked(old('parking_action') === 'release')><span><strong>Give the slot up</strong></span></label>
                            <label><input type="radio" name="parking_action" value="replace" @checked(old('parking_action') === 'replace') @disabled(!$freeSlots)><span><strong>Move to another slot</strong>@unless($freeSlots)<small>No other slot is free.</small>@endunless</span></label>
                        </div>
                        @if($freeSlots)
                            <div class="transfer-date" data-replace-only @if(old('parking_action') !== 'replace') hidden @endif>
                                <label for="tr_slot" class="form-label">New slot</label>
                                <select id="tr_slot" name="new_parking_slot_id" class="form-select">
                                    @foreach($freeSlots as $free)<option value="{{ $free['id'] }}" @selected((int) old('new_parking_slot_id') === (int) $free['id'])>{{ $free['label'] }}@if($free['type']) ({{ $free['type'] }})@endif</option>@endforeach
                                </select>
                            </div>
                        @endif
                    </section>
                @endif

                <section class="tenant-panel tenant-panel--padded mb-5">
                    <h2 class="report-section-title">{{ $slot ? 5 : 4 }}. Notes</h2>
                    <label for="tr_notes" class="visually-hidden">Notes</label>
                    <textarea id="tr_notes" name="notes" rows="2" maxlength="1000" class="form-control" placeholder="Why the tenant is moving, or anything the approver should know">{{ old('notes') }}</textarea>
                    @if($arrears['type'] === 'warning')
                        <div class="alert alert-warning mt-4 mb-0">
                            <p class="mb-2">{{ $arrears['message'] }} The arrears stay on the tenant's account after the move.</p>
                            <label class="form-check"><input class="form-check-input" type="checkbox" name="arrears_acknowledged" value="1" @checked(old('arrears_acknowledged'))> <span class="form-check-label">I have seen the arrears and want to request the transfer.</span></label>
                        </div>
                    @endif
                </section>
            </div>

            <aside class="transfer-side">
                <section class="tenant-panel tenant-panel--padded transfer-summary" aria-live="polite">
                    <h2 class="report-section-title">What will happen</h2>
                    <dl data-summary>
                        <div><dt>Move date</dt><dd data-s="date">{{ $nextPeriod->format('j M Y') }}</dd></div>
                        <div><dt>New rent, a month</dt><dd data-s="rent">—</dd></div>
                        <div data-s-row="proration" hidden><dt>Rent adjustment</dt><dd data-s="proration">—</dd></div>
                        <div><dt>Deposit</dt><dd data-s="deposit">—</dd></div>
                    </dl>
                    <p class="transfer-summary-note" data-s="note">On approval, the old tenancy ends and a new one starts on the move date, keeping the account, agent and billing cycle.</p>
                    <button type="submit" class="btn btn-primary w-100 mt-3">Request transfer</button>
                    <a href="{{ route('tenant_movement.index') }}" class="btn btn-light w-100 mt-2">Cancel</a>
                </section>
            </aside>
        </form>
        @endif
    </div>
</div>
@endsection
