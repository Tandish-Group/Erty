@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Commissions'])
@php
    $money = fn ($v, $cur = null) => money($v, $cur);
    $filtered = array_filter(\Illuminate\Support\Arr::except($filters, ['from', 'to']));
    $tabUrl = fn ($t) => route('tenant_management.agent_commissions', array_merge(request()->except('page', 'tab'), ['tab' => $t]));
    $usesFilters = in_array($tab, ['summary', 'ledger', 'properties', 'agents', 'units'], true);
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container" data-commissions-page="{{ $tab }}">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Agents</span>
                <h1>Commissions</h1>
                @if($ownAgent)
                <p>Your commission, recorded when rent is collected on the units you are responsible for, with the agreement and rate used at the time.</p>
                @else
                <p>Commission is recorded when rent is collected, with the agreement and rate used at the time. The company earns its fee; agents earn on the units they are responsible for; primary managers earn on units they delegate.</p>
                @endif
            </div>
            @if($tab === 'ledger')
            <div class="tenant-heading-actions"><a class="btn btn-light-primary" href="{{ route('tenant_management.agent_commissions.export', request()->except('page', 'tab')) }}">Export CSV</a></div>
            @endif
        </header>

        @if($errors->any())
            <div class="alert alert-danger" role="alert">@foreach($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>
        @endif

        <nav class="nav tenant-tabs mb-6 flex-nowrap overflow-auto" aria-label="Commission sections">
            @foreach($tabs as $key => $label)
                <a class="nav-link text-nowrap {{ $tab === $key ? 'active' : '' }}" href="{{ $tabUrl($key) }}" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @if($usesFilters)
        <form method="get" class="tenant-panel tenant-panel--padded mb-6" data-commission-filters>
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fs-7" for="cf-period">Period</label>
                    <select class="form-select form-select-sm" id="cf-period" name="period" onchange="this.form.querySelectorAll('[data-custom]').forEach(e => e.hidden = this.value !== 'custom')">
                        @foreach(['this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'this_year' => 'This year', 'custom' => 'Custom range'] as $value => $label)
                            <option value="{{ $value }}" @selected($period['key'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2" data-custom @if($period['key'] !== 'custom') hidden @endif><label class="form-label fs-7" for="cf-from">From</label><input type="date" class="form-control form-control-sm" id="cf-from" name="from" value="{{ $period['from'] }}"></div>
                <div class="col-md-2" data-custom @if($period['key'] !== 'custom') hidden @endif><label class="form-label fs-7" for="cf-to">To</label><input type="date" class="form-control form-control-sm" id="cf-to" name="to" value="{{ $period['to'] }}"></div>
                <div class="col-md-3"><label class="form-label fs-7" for="cf-complex">Property</label>@include('pages.business.complex.partials.picker', ['name' => 'complex_id', 'id' => 'cf-complex', 'selected' => $filterComplex, 'placeholder' => 'All properties'])</div>
                @unless($ownAgent)<div class="col-md-3"><label class="form-label fs-7" for="cf-agent">Agent</label>@include('pages.business.agents.partials.picker', ['name' => 'agent_id', 'id' => 'cf-agent', 'selected' => $filterAgent, 'query' => [], 'placeholder' => 'All agents'])</div>@endunless
                @unless($ownAgent)<div class="col-6 col-md-2"><label class="form-label fs-7" for="cf-beneficiary">Beneficiary</label><select class="form-select form-select-sm" id="cf-beneficiary" name="beneficiary"><option value="">Company and agents</option><option value="company" @selected($filters['beneficiary'] === 'company')>Company</option><option value="agent" @selected($filters['beneficiary'] === 'agent')>Agents</option></select></div>@endunless
                <div class="col-6 col-md-2"><label class="form-label fs-7" for="cf-status">Status</label><select class="form-select form-select-sm" id="cf-status" name="status"><option value="">Any</option>@foreach(['pending' => 'Waiting for approval', 'earned' => 'Earned', 'approved' => 'Approved', 'paid' => 'Paid', 'reversed' => 'Reversed'] as $v => $l)<option value="{{ $v }}" @selected($filters['status'] === $v)>{{ $l }}</option>@endforeach</select></div>
                @unless($ownAgent)<div class="col-6 col-md-2"><label class="form-label fs-7" for="cf-management">Managed by</label><select class="form-select form-select-sm" id="cf-management" name="management_type"><option value="">Either</option><option value="company" @selected($filters['management_type'] === 'company')>Company</option><option value="individual" @selected($filters['management_type'] === 'individual')>Primary manager</option></select></div>@endunless
                <div class="col-6 col-md-2"><label class="form-label fs-7" for="cf-unit">Units</label><select class="form-select form-select-sm" id="cf-unit" name="unit_type"><option value="">All</option><option value="residential" @selected($filters['unit_type'] === 'residential')>Residential</option><option value="commercial" @selected($filters['unit_type'] === 'commercial')>Commercial</option></select></div>
                <div class="col-md-3 d-flex gap-2"><button class="btn btn-sm btn-primary">Show</button>@if($filtered || $period['key'] !== 'this_month')<a class="btn btn-sm btn-light" href="{{ route('tenant_management.agent_commissions', ['tab' => $tab]) }}">Reset</a>@endif</div>
            </div>
            <p class="text-muted fs-8 mt-3 mb-0">{{ $period['label'] }}. Rent counts on the day it was collected; period commission counts in the period it is for.</p>
        </form>
        @endif

        @include('pages.business.tenant.commissions.' . $tab)
    </div>
</div>
@endsection
