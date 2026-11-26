@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Tenant profile</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted"><a href="/tenant_management" class="text-muted text-hover-primary">Tenants</a></li>
                <li class="breadcrumb-item text-muted"><a href="/tenant_management/tenants" class="text-muted text-hover-primary">Tenant profiles</a></li>
                <li class="breadcrumb-item text-dark">{{ $profileData['basic_info']['display_name'] }}</li>
            </ul>
        </div>
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <span class="svg-icon svg-icon-1 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                    </svg>
                </span>
            </div>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">

        @php
            $basic = $profileData['basic_info'];
            $isBusiness = $basic['tenant_type'] === 'Business';
            $nameForInitials = $isBusiness ? ($profileData['business_details']['business_name'] ?? $basic['display_name']) : ($profileData['personal_details']['tenant_name'] ?? $basic['display_name']);
            $initials = collect(preg_split('/\s+/', trim((string) $nameForInitials)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
            $tone = $basic['is_blacklisted'] ? 'danger' : ($isBusiness ? 'info' : 'warning');
            $accountService = app(\App\Services\Rent\TenantAccountService::class);
            $tenancies = $tenantProfile->tenancyAgreements;
            // One account per transfer chain: use the newest tenancy of each chain.
            $accounts = collect();
            foreach ($tenancies as $t) {
                $current = $accountService->currentTenancy($t);
                if (!$accounts->has($current->id)) {
                    $accounts->put($current->id, ['tenancy' => $current, 'summary' => $accountService->summary($current)]);
                }
            }
            $currency = $accounts->first()['summary']['currency'] ?? ($tenancies->first()->currency ?? default_currency());
            $money = fn ($v) => money($v, $currency);
            $balance = round($accounts->sum(fn ($a) => max(0, $a['summary']['balance'])), 2);
            $overdue = round($accounts->sum(fn ($a) => $a['summary']['overdue']), 2);
            $credit = round($accounts->sum(fn ($a) => $a['summary']['credit']), 2);
            $depositHeld = round($accounts->sum(fn ($a) => $a['summary']['deposit_held']), 2);
            $activeTenancies = $tenancies->whereIn('status', ['Active', 'On Notice']);
            $agents = $activeTenancies->pluck('agent')->filter()->unique('id')->values();
            $lastPayment = $allPayments->where('entry_type', 'payment')->where('payment_status', 'confirmed')->sortByDesc('payment_date')->first();
            $tenantSince = $tenancies->pluck('move_in_date')->filter()->min();
            $sourceLeads = \App\Models\Lead::with('profile')->whereIn('converted_tenant_id', $tenancies->pluck('id'))->get();
            $methods = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'check' => 'Cheque', 'card' => 'Card', 'other' => 'Other'];
            $field = fn ($label, $value, $wide = false) => ['label' => $label, 'value' => $value, 'wide' => $wide];
            $render = fn ($rows) => collect($rows)->filter(fn ($r) => filled(strip_tags((string) $r['value'])));
            $overdueAccount = $accounts->first(fn ($a) => $a['summary']['overdue'] > 0);
        @endphp

        {{-- Tenant header --}}
        <section class="lead-profile-hero mb-6" aria-labelledby="tenant-name" data-tenant-profile-hero>
            <div class="lead-profile-hero-top">
                <div class="lead-profile-identity">
                    <span class="lead-profile-avatar is-{{ $tone }}" aria-hidden="true">{{ $initials ?: 'T' }}</span>
                    <div class="min-w-0">
                        <div class="lead-profile-name-row">
                            <h2 id="tenant-name" class="lead-profile-name">{{ $basic['display_name'] }}</h2>
                            <span class="badge badge-light-{{ $isBusiness ? 'success' : 'primary' }}"><i class="bi bi-{{ $isBusiness ? 'building' : 'person' }} me-1" aria-hidden="true"></i>{{ $basic['tenant_type'] }}</span>
                            @if($basic['is_blacklisted'])
                                <span class="badge badge-danger"><i class="bi bi-slash-circle me-1" aria-hidden="true"></i>Blacklisted</span>
                            @else
                                <span class="badge badge-light-{{ $basic['profile_status'] === 'Active' ? 'success' : 'secondary' }}">{{ $basic['profile_status'] }}</span>
                            @endif
                        </div>
                        <ul class="lead-profile-facts">
                            <li><i class="bi bi-person-badge" aria-hidden="true"></i>{{ $basic['tenant_ref'] }}</li>
                            @if($profileData['contact_info']['primary_email'])<li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:{{ $profileData['contact_info']['primary_email'] }}">{{ $profileData['contact_info']['primary_email'] }}</a></li>@endif
                            @if($profileData['contact_info']['primary_phone'])<li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:{{ $profileData['contact_info']['primary_phone'] }}">{{ $profileData['contact_info']['primary_phone'] }}</a></li>@endif
                            @if($tenantSince)<li><i class="bi bi-calendar3" aria-hidden="true"></i>Tenant since {{ \Carbon\Carbon::parse($tenantSince)->format('j M Y') }}</li>@endif
                            @foreach($sourceLeads as $lead)<li data-tenant-source-lead><i class="bi bi-funnel" aria-hidden="true"></i>From lead {{ profile_link(lead_profile_url($lead), $lead->full_name) }}</li>@endforeach
                        </ul>
                    </div>
                </div>
                <div class="lead-profile-actions">
                    @businessCan('tenant_management', 'create')<a href="/tenant_management/onboarding/tenant" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg" aria-hidden="true"></i>New tenancy</a>@endbusinessCan
                    @businessCan('tenant_management', 'edit')<a href="/tenant_management/edit/{{ $basic['tenant_ref'] }}/tenant" class="btn btn-sm btn-light-primary"><i class="bi bi-pencil-square" aria-hidden="true"></i>Edit profile</a>@endbusinessCan
                    @businessCan('tenant_management', 'edit')
                    @if($basic['is_blacklisted'])
                        <button type="button" class="btn btn-sm btn-light-success" onclick="removeFromBlacklist('{{ $basic['tenant_ref'] }}')"><i class="bi bi-check-circle" aria-hidden="true"></i>Remove from blacklist</button>
                    @else
                        <button type="button" class="btn btn-sm btn-light-danger" onclick="addToBlacklist('{{ $basic['tenant_ref'] }}')"><i class="bi bi-slash-circle" aria-hidden="true"></i>Blacklist</button>
                    @endif
                    @endbusinessCan
                </div>
            </div>

            @if($basic['is_blacklisted'])
                <div class="lead-profile-next is-warning" role="status"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i><div class="flex-grow-1"><strong>This tenant is blacklisted</strong><span>Check the reason before offering a new tenancy.</span></div></div>
            @elseif($overdueAccount)
                <div class="lead-profile-next is-warning" role="status" data-tenant-next-step>
                    <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                    <div class="flex-grow-1"><strong>{{ $money($overdue) }} overdue</strong><span>Rent or charges are past their due date. Record a payment or follow up from the tenancy's Billing tab.</span></div>
                    @if($url = tenancy_profile_url($overdueAccount['tenancy']))<a class="btn btn-sm btn-warning" href="{{ $url }}#payments">Open billing</a>@endif
                </div>
            @endif

            <div class="lead-profile-metrics" aria-label="Tenant summary">
                <div><span>Tenancies</span><strong>{{ $activeTenancies->count() }}<small> active</small></strong><small>{{ $tenancies->count() }} in total</small></div>
                <div><span>{{ $balance > 0 ? 'Balance due' : ($credit > 0 ? 'In credit' : 'Balance') }}</span><strong class="{{ $overdue > 0 ? 'text-danger' : '' }}">{{ $money($balance > 0 ? $balance : $credit) }}</strong><small>{{ $lastPayment ? 'Last paid '.$lastPayment->payment_date->format('j M Y').' · '.$money($lastPayment->amount_paid) : 'No payments yet' }}</small></div>
                <div><span>Deposit held</span><strong>{{ $money($depositHeld) }}</strong><small>Refundable under the agreement</small></div>
                <div><span>{{ $agents->count() > 1 ? 'Agents' : 'Agent' }}</span><strong class="fs-6" data-tenant-agents>
                    @forelse($agents as $agent){{ profile_link(agent_profile_url($agent), $agent->full_name, 'lead-profile-link') }}@if(!$loop->last), @endif @empty Not assigned @endforelse
                </strong><small>On active tenancies</small></div>
            </div>

            <nav class="nav lead-profile-tabs" role="tablist" aria-label="Tenant sections">
                <a class="nav-link active" id="tenant-tab-overview" data-bs-toggle="tab" href="#profile_overview" role="tab" aria-controls="profile_overview" aria-selected="true">Overview</a>
                <a class="nav-link" id="tenant-tab-tenancies" data-bs-toggle="tab" href="#profile_tenancies" role="tab" aria-controls="profile_tenancies" aria-selected="false">Tenancies <span class="lead-profile-count">{{ $tenancies->count() }}</span></a>
                <a class="nav-link" id="tenant-tab-financial" data-bs-toggle="tab" href="#profile_financial" role="tab" aria-controls="profile_financial" aria-selected="false">Payments</a>
                <a class="nav-link" id="tenant-tab-activity" data-bs-toggle="tab" href="#profile_activity" role="tab" aria-controls="profile_activity" aria-selected="false">Activity <span class="lead-profile-count">{{ count($activityTimeline) }}</span></a>
                <a class="nav-link" id="tenant-tab-documents" data-bs-toggle="tab" href="#profile_documents" role="tab" aria-controls="profile_documents" aria-selected="false">Documents</a>
            </nav>
        </section>

        @php
            $pd = $profileData['personal_details'] ?? [];
            $bd = $profileData['business_details'] ?? [];
            $ci = $profileData['contact_info'] ?? [];
            $ec = $profileData['emergency_contact'] ?? [];
            $details = $isBusiness ? [
                $field('Business name', $bd['business_name'] ?? null),
                $field('Registration number', $bd['business_registration'] ?? null),
                $field('Contact person', $bd['business_contact_person'] ?? null),
                $field('Business type', $bd['business_type'] ?? null),
                $field('Tax ID', $bd['tax_id_number'] ?? null),
                $field('Annual revenue', !empty($bd['annual_revenue']) ? $currency.' '.number_format((float) $bd['annual_revenue']) : null),
            ] : [
                $field('Full name', $pd['tenant_name'] ?? null),
                $field('ID number', $pd['tenant_id_number'] ?? null),
                $field('Date of birth', !empty($pd['date_of_birth']) ? \Carbon\Carbon::parse($pd['date_of_birth'])->format('j M Y') : null),
                $field('Gender', $pd['gender'] ?? null),
                $field('Occupation', $pd['occupation'] ?? null),
            ];
            $contact = [
                $field('Email', !empty($ci['primary_email']) ? '<a href="mailto:'.e($ci['primary_email']).'">'.e($ci['primary_email']).'</a>' : null),
                $field('Phone', !empty($ci['primary_phone']) ? '<a href="tel:'.e($ci['primary_phone']).'">'.e($ci['primary_phone']).'</a>' : null),
                $field('Address', isset($ci['address']) ? e($ci['address']) : null, true),
                $field('Emergency contact', !empty($ec['emergency_contact_name']) ? e($ec['emergency_contact_name']).(!empty($ec['emergency_contact_relationship']) ? ' · '.e($ec['emergency_contact_relationship']) : '').(!empty($ec['emergency_contact_phone']) ? '<span class="lead-profile-sub"><a href="tel:'.e($ec['emergency_contact_phone']).'">'.e($ec['emergency_contact_phone']).'</a></span>' : '') : '<span class="text-muted">None provided</span>', true),
            ];
        @endphp

        <div class="tab-content" id="profileTabContent">
            {{-- Overview --}}
            <div class="tab-pane fade show active" id="profile_overview" role="tabpanel" aria-labelledby="tenant-tab-overview">
                <div class="row g-6">
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="tenant-details-title">
                            <h3 id="tenant-details-title">{{ $isBusiness ? 'Business' : 'Personal details' }}</h3>
                            <dl class="lead-profile-fields">
                                @foreach($render($details) as $row)<div class="{{ $row['wide'] ? 'is-wide' : '' }}"><dt>{{ $row['label'] }}</dt><dd>{!! e($row['value']) !!}</dd></div>@endforeach
                            </dl>
                        </section>
                    </div>
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="tenant-contact-title">
                            <h3 id="tenant-contact-title">Contact</h3>
                            <dl class="lead-profile-fields">
                                @foreach(collect($contact)->filter(fn ($r) => filled(strip_tags((string) $r['value'])) || str_contains((string) $r['value'], 'text-muted')) as $row)<div class="{{ $row['wide'] ? 'is-wide' : '' }}"><dt>{{ $row['label'] }}</dt><dd>{!! $row['value'] !!}</dd></div>@endforeach
                            </dl>
                        </section>
                    </div>
                    <div class="col-12">
                        <section class="lead-profile-card" aria-labelledby="tenant-glance-title">
                            <h3 id="tenant-glance-title">Tenancies at a glance</h3>
                            @if($tenancies->isEmpty())
                                <p class="lead-profile-empty">No tenancies yet.</p>
                            @else
                            <div class="table-responsive">
                                <table class="table table-row-dashed align-middle fs-7 mb-0 tenant-glance-table">
                                    <thead><tr class="text-muted fw-semibold"><th>Tenancy</th><th>Property · unit</th><th>Agent</th><th class="text-end">Rent</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                                    <tbody>
                                    @foreach($tenancies as $t)
                                        @php $acc = $accounts->get($accountService->currentTenancy($t)->id); $isCurrent = $acc && $acc['tenancy']->id === $t->id; @endphp
                                        <tr data-glance-tenancy="{{ $t->tenancy_ref }}">
                                            <td class="fw-semibold">{{ profile_link(tenancy_profile_url($t), $t->tenancy_ref) }}</td>
                                            <td>{{ $t->complex->title ?? $t->complex_name }}{{ ($t->unit_number ?? $t->specific_unit_number) ? ' · '.($t->unit_number ?? $t->specific_unit_number) : '' }}</td>
                                            <td>@if($t->agent){{ profile_link(agent_profile_url($t->agent), $t->agent->full_name) }}@else<span class="text-muted">Not assigned</span>@endif</td>
                                            <td class="text-end text-nowrap">{{ ($t->currency ?: default_currency()).' '.number_format((float) $t->monthly_payment, 2) }}<span class="text-muted"> / {{ strtolower($t->payment_frequency ?: 'month') }}</span></td>
                                            <td class="text-end text-nowrap">@if($isCurrent){!! $acc['summary']['overdue'] > 0 ? '<span class="text-danger fw-semibold">'.e($money($acc['summary']['balance'])).'</span>' : e($money(max(0, $acc['summary']['balance']))) !!}@else<span class="text-muted">Moved to {{ $acc['tenancy']->tenancy_ref ?? '—' }}</span>@endif</td>
                                            <td><span class="badge badge-light-{{ ['Active' => 'success', 'On Notice' => 'warning', 'Terminated' => 'danger'][$t->status] ?? 'dark' }}">{{ $t->status }}</span></td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif
                        </section>
                    </div>
                </div>
            </div>

            {{-- Tenancies --}}
            <div class="tab-pane fade" id="profile_tenancies" role="tabpanel" aria-labelledby="tenant-tab-tenancies">
                <section class="lead-profile-card mb-5">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label class="visually-hidden" for="tenancy_search">Search tenancies</label>
                            <input type="text" id="tenancy_search" class="form-control" placeholder="Search by reference, property or unit" />
                        </div>
                        <div class="col-md-3">
                            <label class="visually-hidden" for="filter_complex">Property</label>
                            <select id="filter_complex" class="form-select" data-control="select2" data-placeholder="All properties" data-allow-clear="true">
                                <option></option>
                                @foreach(collect($tenanciesWithStats)->pluck('tenancy.complex')->filter()->unique('id')->sortBy('title') as $complex)
                                    <option value="{{ $complex->id }}">{{ $complex->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="visually-hidden" for="filter_status">Status</label>
                            <select id="filter_status" class="form-select" data-control="select2" data-placeholder="All statuses" data-allow-clear="true">
                                <option></option><option value="Active">Active</option><option value="On Notice">On notice</option><option value="Transferred">Transferred</option><option value="Terminated">Terminated</option><option value="Pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="visually-hidden" for="filter_listing_type">Type</label>
                            <select id="filter_listing_type" class="form-select" data-control="select2" data-placeholder="All types" data-allow-clear="true">
                                <option></option><option value="residential">Residential</option><option value="commercial">Commercial</option>
                            </select>
                        </div>
                        <div class="col-md-1">
                            <button type="button" id="reset_tenancy_filters" class="btn btn-light w-100" aria-label="Reset filters"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></button>
                        </div>
                    </div>
                </section>
                <p id="tenancies_info" class="text-muted fs-7 mb-3">Showing <span id="tenancies_count">{{ count($tenanciesWithStats) }}</span> tenancies</p>
                <div class="row g-6" id="tenancies_container">
                    @forelse($tenanciesWithStats as $tenancyData)
                        @php
                            $tenancy = $tenancyData['tenancy'];
                            $statusTone = ['Active' => 'success', 'On Notice' => 'warning', 'Terminated' => 'danger'][$tenancy->status] ?? 'dark';
                            $acc = $accounts->get($accountService->currentTenancy($tenancy)->id);
                            $isCurrent = $acc && $acc['tenancy']->id === $tenancy->id;
                        @endphp
                        <div class="col-md-6 col-xl-4 tenancy-item"
                             data-complex-id="{{ $tenancy->complex_id ?? '' }}"
                             data-status="{{ $tenancy->status }}"
                             data-listing-type="{{ $tenancy->listing_type === 'Commercial Unit' ? 'commercial' : 'residential' }}"
                             data-search-text="{{ strtolower($tenancy->tenancy_ref . ' ' . ($tenancy->complex->title ?? '') . ' ' . ($tenancy->unit_number ?? '') . ' ' . ($tenancy->specific_unit_number ?? '')) }}">
                            <section class="lead-profile-card h-100 tenant-tenancy-card">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                                    <h3 class="mb-0">{{ profile_link(tenancy_profile_url($tenancy), $tenancy->tenancy_ref, 'text-reset') }}</h3>
                                    <span class="badge badge-light-{{ $statusTone }}">{{ $tenancy->status }}</span>
                                </div>
                                <dl class="lead-profile-fields">
                                    <div class="is-wide"><dt>Property · unit</dt><dd>{{ $tenancy->complex->title ?? $tenancy->complex_name }}{{ ($tenancy->unit_number ?? $tenancy->specific_unit_number) ? ' · '.($tenancy->unit_number ?? $tenancy->specific_unit_number) : '' }}<span class="lead-profile-sub text-muted">{{ $tenancy->listing_type }}</span></dd></div>
                                    <div data-tenancy-agent><dt>Agent</dt><dd>@if($tenancy->agent){{ profile_link(agent_profile_url($tenancy->agent), $tenancy->agent->full_name) }}@else<span class="text-muted">Not assigned</span>@endif</dd></div>
                                    <div><dt>Rent</dt><dd>{{ ($tenancy->currency ?: default_currency()).' '.number_format((float) $tenancy->monthly_payment, 2) }}<span class="lead-profile-sub text-muted">{{ $tenancy->payment_frequency }}</span></dd></div>
                                    <div class="is-wide"><dt>Lease</dt><dd>{{ $tenancy->lease_start_date ? \Carbon\Carbon::parse($tenancy->lease_start_date)->format('j M Y') : 'Not set' }} – {{ $tenancy->lease_end_date ? \Carbon\Carbon::parse($tenancy->lease_end_date)->format('j M Y') : 'open-ended' }}@if($tenancyData['days_until_expiry'] !== null && $tenancyData['days_until_expiry'] >= 0 && $tenancyData['days_until_expiry'] <= 90)<span class="badge badge-light-warning ms-2">{{ $tenancyData['days_until_expiry'] }} days left</span>@endif</dd></div>
                                    @if($isCurrent)
                                        <div><dt>Balance</dt><dd class="{{ $acc['summary']['overdue'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $acc['summary']['balance'] < 0 ? 'Credit '.$money(abs($acc['summary']['balance'])) : $money($acc['summary']['balance']) }}</dd></div>
                                        <div><dt>Deposit held</dt><dd>{{ $money($acc['summary']['deposit_held']) }}</dd></div>
                                    @elseif($acc)
                                        <div class="is-wide"><dt>Account</dt><dd class="text-muted">Moved with the tenant to {{ profile_link(tenancy_profile_url($acc['tenancy']), $acc['tenancy']->tenancy_ref) }}</dd></div>
                                    @endif
                                </dl>
                                @if($url = tenancy_profile_url($tenancy))
                                    <div class="d-flex gap-2 mt-4">
                                        <a href="{{ $url }}" class="btn btn-sm btn-light-primary flex-grow-1">Open tenancy</a>
                                        @if($isCurrent)<a href="{{ $url }}#payments" class="btn btn-sm btn-light flex-grow-1">Billing</a>@endif
                                    </div>
                                @endif
                            </section>
                        </div>
                    @empty
                        <div class="col-12"><p class="lead-profile-empty">No tenancies yet.</p></div>
                    @endforelse
                </div>
            </div>

            {{-- Payments --}}
            <div class="tab-pane fade" id="profile_financial" role="tabpanel" aria-labelledby="tenant-tab-financial">
                <section class="lead-profile-card mb-6" aria-labelledby="tenant-accounts-title">
                    <h3 id="tenant-accounts-title">Accounts <small>one per tenancy; a transferred tenancy shares its account with the new one</small></h3>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-7 mb-0">
                            <thead><tr class="text-muted fw-semibold"><th>Tenancy</th><th>Property · unit</th><th class="text-end">Balance</th><th class="text-end">Overdue</th><th class="text-end">Credit</th><th class="text-end">Deposit held</th><th></th></tr></thead>
                            <tbody>
                            @forelse($accounts as $a)
                                @php $t = $a['tenancy']; $sm = $a['summary']; @endphp
                                <tr>
                                    <td class="fw-semibold">{{ profile_link(tenancy_profile_url($t), $t->tenancy_ref) }}<span class="lead-profile-sub text-muted fw-normal">{{ $t->status }}</span></td>
                                    <td>{{ $t->complex_name }}{{ $t->unit_number ? ' · '.$t->unit_number : '' }}</td>
                                    <td class="text-end fw-semibold">{{ $sm['balance'] < 0 ? '—' : $money($sm['balance']) }}</td>
                                    <td class="text-end {{ $sm['overdue'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $money($sm['overdue']) }}</td>
                                    <td class="text-end">{{ $money($sm['credit']) }}</td>
                                    <td class="text-end">{{ $money($sm['deposit_held']) }}</td>
                                    <td class="text-end">@if($url = tenancy_profile_url($t))<a class="btn btn-sm btn-light-primary" href="{{ $url }}#payments">Billing</a>@endif</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="lead-profile-empty">No accounts yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="lead-profile-card mb-6" aria-labelledby="tenant-payments-title">
                    <h3 id="tenant-payments-title">Recent payments <small>latest 10</small></h3>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-7 mb-0" data-tenant-payments>
                            <thead><tr class="text-muted fw-semibold"><th>Date</th><th>Entry</th><th>Tenancy</th><th>Method · reference</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                            <tbody>
                            @forelse($allPayments->take(10) as $payment)
                                @php
                                    $st = ['pending' => ['Awaiting clearance', 'warning'], 'confirmed' => ['Confirmed', 'success'], 'reversed' => ['Reversed', 'danger']][$payment->payment_status] ?? [ucfirst((string) $payment->payment_status), 'secondary'];
                                    $isRefund = $payment->entry_type === 'refund';
                                @endphp
                                <tr>
                                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($payment->payment_date)->format('j M Y') }}</td>
                                    <td>{{ \App\Models\TenantPayment::ENTRY_LABELS[$payment->entry_type] ?? 'Payment' }}</td>
                                    <td>{{ profile_link(tenancy_profile_url($payment->tenant), $payment->tenant->tenancy_ref ?? '—') }}</td>
                                    <td>{{ in_array($payment->entry_type, ['payment', 'refund'], true) ? ($methods[$payment->payment_method] ?? ucfirst(str_replace('_', ' ', (string) $payment->payment_method))) : '—' }}{{ $payment->transaction_reference ? ' · '.$payment->transaction_reference : '' }}</td>
                                    <td class="text-end fw-semibold text-nowrap {{ $isRefund ? 'text-danger' : '' }}">{{ $isRefund ? '−' : '' }}{{ ($payment->tenant->currency ?? default_currency()).' '.number_format((float) $payment->amount_paid, 2) }}</td>
                                    <td><span class="badge badge-light-{{ $st[1] }}">{{ $st[0] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="lead-profile-empty">No payments recorded yet.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="lead-profile-card" aria-labelledby="tenant-open-title">
                    @php $openInvoices = $allInvoices->filter(fn ($i) => in_array($i->status, [\App\Models\TenantInvoice::UNPAID, \App\Models\TenantInvoice::PARTIAL], true))->sortBy('due_date'); @endphp
                    <h3 id="tenant-open-title">Unpaid invoices <small>{{ $openInvoices->count() }}</small></h3>
                    @if($openInvoices->isEmpty())
                        <p class="lead-profile-empty"><i class="bi bi-check-circle text-success me-1" aria-hidden="true"></i>Nothing is owed right now.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-7 mb-0">
                            <thead><tr class="text-muted fw-semibold"><th>Invoice</th><th>For</th><th>Tenancy</th><th>Due</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach($openInvoices as $invoice)
                                <tr>
                                    <td class="fw-semibold">{{ $invoice->invoice_number }}<span class="lead-profile-sub text-muted fw-normal">{{ $invoice->kind_label }}</span></td>
                                    <td>{{ $invoice->line_items[0]['description'] ?? $invoice->kind_label }}</td>
                                    <td>{{ profile_link(tenancy_profile_url($invoice->tenancy), $invoice->tenancy->tenancy_ref ?? '—') }}</td>
                                    <td class="text-nowrap">{{ $invoice->due_date?->format('j M Y') }}</td>
                                    <td class="text-end text-nowrap">{{ ($invoice->currency ?? default_currency()).' '.number_format((float) $invoice->amount, 2) }}</td>
                                    <td class="text-end text-nowrap fw-semibold">{{ ($invoice->currency ?? default_currency()).' '.number_format($invoice->balance, 2) }}</td>
                                    <td><span class="badge badge-light-{{ $invoice->isOverdue() ? 'danger' : ($invoice->status === \App\Models\TenantInvoice::PARTIAL ? 'warning' : 'primary') }}">{{ $invoice->display_status }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </section>
            </div>

            {{-- Activity --}}
            <div class="tab-pane fade" id="profile_activity" role="tabpanel" aria-labelledby="tenant-tab-activity">
                <section class="lead-profile-card" aria-labelledby="tenant-activity-title">
                    <h3 id="tenant-activity-title">Activity <small>{{ count($activityTimeline) }} {{ \Illuminate\Support\Str::plural('entry', count($activityTimeline)) }}, newest first</small></h3>
                    @if(empty($activityTimeline))
                        <p class="lead-profile-empty">No activity yet.</p>
                    @else
                    <ol class="lead-profile-timeline">
                        @foreach($activityTimeline as $activity)
                            @php $when = \Carbon\Carbon::parse($activity['date']); @endphp
                            <li class="lead-profile-event">
                                <span class="lead-profile-event-icon is-{{ $activity['color'] === 'primary' ? 'info' : $activity['color'] }}" aria-hidden="true"><i class="{{ str_starts_with($activity['icon'], 'fa') ? $activity['icon'] : 'fa '.$activity['icon'] }}"></i></span>
                                <div class="lead-profile-event-body">
                                    <div class="lead-profile-event-head">
                                        <strong>{{ \Illuminate\Support\Str::ucfirst(mb_strtolower($activity['title'])) }}</strong>
                                        <time datetime="{{ $when->toIso8601String() }}">{{ $when->format('j M Y') }} · {{ $when->diffForHumans() }}</time>
                                    </div>
                                    @if(!empty($activity['description']))<p>{{ $activity['description'] }}</p>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @endif
                </section>
            </div>

            <!--begin::Documents Tab-->
            <div class="tab-pane fade" id="profile_documents" role="tabpanel">
                <div class="row g-6 g-xl-9">

                    {{-- Profile-level documents (ID docs & references) --}}
                    @if($profileData['documents']['identification_documents'] || $profileData['documents']['references'])
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header border-0 pt-6">
                                <div class="card-title">
                                    <h3 class="card-label fw-bolder text-dark">Profile Documents</h3>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                <div class="row g-5">
                                    <div class="col-md-6">
                                        <div class="fw-bolder text-gray-800 mb-2">Identification Documents</div>
                                        @if($profileData['documents']['identification_documents'])
                                            <div class="text-gray-600">{{ $profileData['documents']['identification_documents'] }}</div>
                                        @else
                                            <div class="text-muted fs-7">None uploaded</div>
                                        @endif
                                    </div>
                                    <div class="col-md-6">
                                        <div class="fw-bolder text-gray-800 mb-2">References</div>
                                        @if($profileData['documents']['references'])
                                            <div class="text-gray-600">{{ $profileData['documents']['references'] }}</div>
                                        @else
                                            <div class="text-muted fs-7">None provided</div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    {{-- Per-tenancy documents accordion --}}
                    <div class="col-12">
                        @forelse($tenanciesWithStats as $loopIndex => $tenancyData)
                        @php
                            $tenancy     = $tenancyData['tenancy'];
                            $docs        = $tenancyData['documents'] ?? [];
                            $legals      = $tenancyData['assigned_legal_documents'] ?? collect();
                            $statusClass = match($tenancy->status) {
                                'Active'     => 'success',
                                'Terminated' => 'danger',
                                default      => 'warning',
                            };
                            $accordionId = 'tenancy_docs_' . $tenancy->id;
                            $isFirst     = $loopIndex === 0;
                        @endphp
                        <div class="card mb-3">
                            {{-- Accordion Header --}}
                            <div class="card-header border-0 cursor-pointer py-4"
                                 role="button"
                                 data-bs-toggle="collapse"
                                 data-bs-target="#{{ $accordionId }}"
                                 aria-expanded="{{ $isFirst ? 'true' : 'false' }}"
                                 aria-controls="{{ $accordionId }}">
                                <div class="card-title d-flex align-items-center justify-content-between w-100 me-3">
                                    <div class="d-flex flex-column">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="fw-bolder text-dark fs-5">{{ $tenancy->tenancy_ref }}</span>
                                            <span class="badge badge-light-{{ $statusClass }} fs-8">{{ $tenancy->status }}</span>
                                            <span class="badge badge-light-primary fs-8">{{ count($docs) }} docs</span>
                                            <span class="badge badge-light-warning fs-8">{{ $legals->count() }} legal</span>
                                        </div>
                                        <span class="text-muted fw-semibold fs-7">
                                            {{ $tenancy->complex->title ?? 'N/A' }}
                                            @if($tenancy->unit_number ?? $tenancy->specific_unit_number ?? null)
                                                &bull; Unit {{ $tenancy->unit_number ?? $tenancy->specific_unit_number }}
                                            @endif
                                            @if($tenancy->lease_start_date)
                                                &bull; Since {{ \Carbon\Carbon::parse($tenancy->lease_start_date)->format('M Y') }}
                                            @endif
                                        </span>
                                    </div>
                                    <i class="ki-duotone ki-down fs-4 text-gray-500 accordion-chevron"></i>
                                </div>
                            </div>

                            {{-- Accordion Body --}}
                            <div id="{{ $accordionId }}" class="collapse {{ $isFirst ? 'show' : '' }}">
                                <div class="card-body pt-0">

                                    {{-- Uploaded Documents --}}
                                    <div class="mb-5">
                                        <div class="d-flex align-items-center mb-3">
                                            <h6 class="fw-bolder text-gray-700 mb-0 me-2">Uploaded Documents</h6>
                                            <span class="badge badge-light-primary">{{ count($docs) }}</span>
                                        </div>
                                        @if(count($docs) > 0)
                                        <div class="table-responsive">
                                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                                                <thead>
                                                    <tr class="fw-bolder text-muted fs-7">
                                                        <th class="min-w-200px">File Name</th>
                                                        <th class="min-w-80px">Type</th>
                                                        <th class="min-w-100px">Size</th>
                                                        <th class="min-w-120px">Uploaded</th>
                                                        <th class="min-w-100px text-end">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($docs as $doc)
                                                    @php
                                                        $ext = strtolower(pathinfo($doc['name'] ?? '', PATHINFO_EXTENSION));
                                                        $iconColor = match($ext) {
                                                            'pdf'               => 'text-danger',
                                                            'doc', 'docx'       => 'text-primary',
                                                            'xls', 'xlsx'       => 'text-success',
                                                            'jpg','jpeg',
                                                            'png','gif','webp'  => 'text-warning',
                                                            default             => 'text-gray-500',
                                                        };
                                                    @endphp
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="symbol symbol-35px me-3">
                                                                    <span class="symbol-label bg-light {{ $iconColor }}">
                                                                        <i class="ki-duotone ki-document fs-3 {{ $iconColor }}">
                                                                            <span class="path1"></span><span class="path2"></span>
                                                                        </i>
                                                                    </span>
                                                                </div>
                                                                <span class="text-dark fw-semibold fs-6">{{ $doc['name'] }}</span>
                                                            </div>
                                                        </td>
                                                        <td><span class="badge badge-light-secondary fs-8">{{ strtoupper($ext ?: '—') }}</span></td>
                                                        <td><span class="text-muted fw-semibold fs-7">{{ $doc['size'] ?? '—' }}</span></td>
                                                        <td><span class="text-muted fw-semibold fs-7">{{ $doc['uploaded_at'] ?? '—' }}</span></td>
                                                        <td class="text-end">
                                                            @if(!empty($doc['url']) && !empty($doc['exists']))
                                                                <a href="{{ $doc['url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-primary me-1">
                                                                    <i class="ki-duotone ki-eye fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                                    View
                                                                </a>
                                                                <a href="{{ $doc['url'] }}" download class="btn btn-sm btn-light">
                                                                    <i class="ki-duotone ki-cloud-download fs-5"><span class="path1"></span><span class="path2"></span></i>
                                                                </a>
                                                            @else
                                                                <span class="badge badge-light-danger">Unavailable</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @else
                                        <div class="text-muted fs-7 ps-1">No documents uploaded for this tenancy.</div>
                                        @endif
                                    </div>

                                    <div class="separator separator-dashed my-5"></div>

                                    {{-- Legal Documents --}}
                                    <div>
                                        <div class="d-flex align-items-center mb-3">
                                            <h6 class="fw-bolder text-gray-700 mb-0 me-2">Legal Documents</h6>
                                            <span class="badge badge-light-warning">{{ $legals->count() }}</span>
                                        </div>
                                        @if($legals->count() > 0)
                                        <div class="table-responsive">
                                            <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                                                <thead>
                                                    <tr class="fw-bolder text-muted fs-7">
                                                        <th class="min-w-200px">Document</th>
                                                        <th class="min-w-130px">Type</th>
                                                        <th class="min-w-130px">Folder</th>
                                                        <th class="min-w-180px">Description</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($legals as $legal)
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <div class="symbol symbol-35px me-3">
                                                                    <span class="symbol-label bg-light-warning text-warning">
                                                                        <i class="ki-duotone ki-security-user fs-3">
                                                                            <span class="path1"></span><span class="path2"></span>
                                                                        </i>
                                                                    </span>
                                                                </div>
                                                                <span class="text-dark fw-semibold fs-6">{{ $legal->file_name }}</span>
                                                            </div>
                                                        </td>
                                                        <td><span class="badge badge-light-warning fs-8">{{ $legal->legal_type }}</span></td>
                                                        <td><span class="text-muted fw-semibold fs-7">{{ $legal->folder_name }}</span></td>
                                                        <td><span class="text-muted fw-semibold fs-7">{{ $legal->description ?? '—' }}</span></td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        @else
                                        <div class="text-muted fs-7 ps-1">No legal documents assigned to this tenancy.</div>
                                        @endif
                                    </div>

                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="card">
                            <div class="card-body text-center py-15">
                                <i class="ki-duotone ki-folder fs-3x text-gray-300 mb-3 d-block">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                                <div class="text-gray-500 fw-semibold fs-6">No tenancies found for this profile.</div>
                            </div>
                        </div>
                        @endforelse
                    </div>

                </div>
            </div>
            <!--end::Documents Tab-->
            
        </div>
        <!--end::Tab Content-->

    </div>
</div>

@endsection
