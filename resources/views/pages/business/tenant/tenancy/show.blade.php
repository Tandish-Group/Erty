@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Tenancy details</h1>
            <!--end::Heading-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted"><a href="/tenant_management" class="text-muted text-hover-primary">Tenants</a></li>
                <li class="breadcrumb-item text-dark">{{ $tenantData['tenant_profile']['display_name'] ?? 'Tenancy' }}</li>
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title=-->
        <!--begin::Wrapper-->
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <!--begin::Aside mobile toggle-->
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <!--begin::Svg Icon | path: icons/duotune/abstract/abs015.svg-->
                <span class="svg-icon svg-icon-1 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                    </svg>
                </span>
                <!--end::Svg Icon-->
            </div>
            <!--end::Aside mobile toggle-->
        </div>
        <!--end::Wrapper-->
        <!--begin::Topbar-->
            @include('pages.business.include.topbar.index')
        <!--end::Topbar-->
    </div>
    <!--end::Container-->
</div>
<!--end::Header-->

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Container-->
    <div class="container-xxl" id="kt_content_container">

        @php
            $tm = \App\Models\TenantManagement::with(['agent', 'complex', 'tenantProfile', 'previousTenancy'])->findOrFail($tenantData['basic_info']['id']);
            $tp = $tenantData['tenant_profile'];
            $pd = $tenantData['personal_details'] ?? [];
            $prop = $tenantData['property_details'] ?? [];
            $ag = $tenantData['agreement_details'] ?? [];
            $isBusiness = ($tp['tenant_type'] ?? null) === 'Business';
            $displayName = $tp['display_name'] ?? 'Unknown tenant';
            $initials = collect(preg_split('/\s+/', trim(preg_replace('/\(.*\)/', '', $displayName))))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
            $acct = app(\App\Services\Rent\TenantAccountService::class);
            $sum = $acct->summary($tm);
            $cur = $sum['currency'];
            $money = fn ($v) => money($v, $cur);
            $unit = $prop['specific_unit_number'] ?? null ?: ($prop['unit_number'] ?? $tm->unit_number);
            $statusTone = ['Active' => 'success', 'On Notice' => 'warning', 'Terminated' => 'danger', 'Transferred' => 'dark'][$tm->status] ?? 'dark';
            $sourceLead = \App\Models\Lead::with('profile')->where('converted_tenant_id', $tm->id)->first();
            $nextTenancy = \App\Models\TenantManagement::where('previous_tenancy_id', $tm->id)->first();
            $lastPayment = \App\Models\TenantPayment::whereIn('tenant_id', $acct->chainIds($tm))->where('entry_type', 'payment')->where('payment_status', 'confirmed')->orderByDesc('payment_date')->first();
            $daysLeft = $tm->lease_end_date ? (int) now()->startOfDay()->diffInDays($tm->lease_end_date, false) : null;
            $termination = $tm->status === 'Terminated' ? \App\Models\TenantTermination::where('tenant_management_id', $tm->id)->first() : null;
            $field = fn ($label, $value, $wide = false) => ['label' => $label, 'value' => $value, 'wide' => $wide];
            $filledRows = fn ($rows) => collect($rows)->filter(fn ($r) => filled(strip_tags((string) $r['value'])) && !in_array(trim(strip_tags((string) $r['value'])), ['N/A', 'KES 0', '0'], true) || str_contains((string) $r['value'], 'text-muted'));
            $freq = strtolower($ag['payment_frequency'] ?? $tm->payment_frequency ?? 'monthly');
            $canPay = auth()->user()->hasBusinessPermission('tenant_management', 'payments');
        @endphp

        {{-- Tenancy header --}}
        <section class="lead-profile-hero mb-6" aria-labelledby="tenancy-name" data-tenancy-hero>
            <div class="lead-profile-hero-top">
                <div class="lead-profile-identity">
                    <span class="lead-profile-avatar is-{{ $isBusiness ? 'info' : 'warning' }}" aria-hidden="true">{{ $initials ?: 'T' }}</span>
                    <div class="min-w-0">
                        <div class="lead-profile-name-row">
                            <h2 id="tenancy-name" class="lead-profile-name">{{ profile_link(!empty($tp['tenant_ref']) ? tenant_profile_url((object) ['tenant_ref' => $tp['tenant_ref']]) : null, $displayName, 'text-reset') }}</h2>
                            <span class="badge badge-light-{{ $statusTone }}">{{ $tm->status }}</span>
                            <span class="badge badge-light-{{ $isBusiness ? 'success' : 'primary' }}"><i class="bi bi-{{ $isBusiness ? 'building' : 'person' }} me-1" aria-hidden="true"></i>{{ $tp['tenant_type'] ?? 'Tenant' }}</span>
                            @if(!empty($tp['is_blacklisted']))<span class="badge badge-danger">Blacklisted</span>@endif
                        </div>
                        <ul class="lead-profile-facts">
                            <li><i class="bi bi-hash" aria-hidden="true"></i>Tenancy {{ $tm->tenancy_ref }}</li>
                            <li><i class="bi bi-geo-alt" aria-hidden="true"></i>@if($tm->complex){{ profile_link(auth()->user()->hasBusinessPermission('complex_management', 'view') ? route('complex.show', $tm->complex->slug) : null, $tm->complex->title) }}@else{{ $tm->complex_name }}@endif{{ $unit ? ' · Unit '.$unit : '' }}</li>
                            @if(!empty($tp['primary_email']))<li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:{{ $tp['primary_email'] }}">{{ $tp['primary_email'] }}</a></li>@endif
                            @if(!empty($tp['primary_phone']))<li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:{{ $tp['primary_phone'] }}">{{ $tp['primary_phone'] }}</a></li>@endif
                            @if($sourceLead)<li data-tenancy-lead><i class="bi bi-funnel" aria-hidden="true"></i>From lead {{ profile_link(lead_profile_url($sourceLead), $sourceLead->full_name) }}</li>@endif
                            @if($tm->previousTenancy)<li><i class="bi bi-arrow-left-right" aria-hidden="true"></i>Moved from {{ profile_link(tenancy_profile_url($tm->previousTenancy), ($tm->previousTenancy->unit_number ?: $tm->previousTenancy->tenancy_ref)) }}</li>@endif
                        </ul>
                    </div>
                </div>
                <div class="lead-profile-actions">
                    @if($canPay)
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#account_payment_modal" data-tenant-id="{{ $tm->id }}"><i class="bi bi-cash-coin" aria-hidden="true"></i>Record payment</button>
                    @endif
                    @if(!empty($tenantData['basic_info']['encrypted_lease_id']))
                        <a href="/tenant_management/lease/{{ $tenantData['basic_info']['encrypted_lease_id'] }}" class="btn btn-sm btn-light-primary"><i class="bi bi-file-earmark-text" aria-hidden="true"></i>View lease</a>
                    @endif
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" data-tenancy-more>More<i class="bi bi-chevron-down ms-2 me-0" aria-hidden="true"></i></button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                            @businessCan('tenant_management', 'edit')
                            @if($tm->status === 'Active')
                                <li><a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#kt_modal_edit_tenancy" data-edit-tenancy><i class="bi bi-pencil me-2" aria-hidden="true"></i>Edit tenancy</a></li>
                            @endif
                            @endbusinessCan
                            @if(in_array($tm->status, ['Active', 'On Notice'], true))
                                @businessCan('tenant_management', 'transfers')<li><a class="dropdown-item" href="/tenant_management/movement/create?tenancy_id={{ $tm->id }}"><i class="bi bi-arrow-left-right me-2" aria-hidden="true"></i>Move / transfer</a></li>@endbusinessCan
                                @businessCan('tenant_management', 'notices')
                                <li><a href="#" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#kt_modal_give_notice" data-tenant-id="{{ $tm->id }}" data-action="notice"><i class="bi bi-bell me-2" aria-hidden="true"></i>Give notice</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a href="#" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#kt_modal_end_tenancy" data-tenant-id="{{ $tm->id }}" data-action="tenancy"><i class="bi bi-stop-circle me-2" aria-hidden="true"></i>End tenancy</a></li>
                                @endbusinessCan
                            @endif
                            @if(!empty($tp['tenant_ref']))<li><a class="dropdown-item" href="/tenant_management/tenant/{{ $tp['tenant_ref'] }}/view"><i class="bi bi-person me-2" aria-hidden="true"></i>Tenant profile</a></li>@endif
                        </ul>
                    </div>
                </div>
            </div>

            @php
                $callout = null;
                if ($tm->status === 'Transferred') {
                    $callout = ['info', 'arrow-left-right', 'Moved to another unit', 'This tenancy ended when the tenant transferred. Their account, agent and deposit moved with them.', $nextTenancy ? ['Open new tenancy', tenancy_profile_url($nextTenancy)] : null];
                } elseif ($tm->status === 'Terminated') {
                    $callout = ['info', 'flag', 'Tenancy ended' . ($termination ? ' on ' . \Carbon\Carbon::parse($termination->termination_date)->format('j M Y') : ''), $sum['balance'] > 0 ? 'The tenant still owes ' . $money($sum['balance']) . '.' : ($sum['credit'] > 0 ? $money($sum['credit']) . ' is still owed back to the tenant.' : 'The account is settled.'), null];
                } elseif ($sum['overdue'] > 0) {
                    $callout = ['warning', 'exclamation-circle', $money($sum['overdue']) . ' overdue', 'Rent or charges are past their due date.', null];
                } elseif ($tm->status === 'On Notice') {
                    $callout = ['warning', 'bell', 'On notice', 'Moving out' . ($tm->expected_move_out_date ? ' on ' . $tm->expected_move_out_date->format('j M Y') : '') . '. Rent is billed until they leave.', null];
                } elseif ($daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 60) {
                    $callout = ['primary', 'calendar-event', 'Lease ends in ' . $daysLeft . ' ' . \Illuminate\Support\Str::plural('day', $daysLeft), 'Renew the lease, give notice or plan the move-out.', null];
                }
            @endphp
            @if($callout)
                <div class="lead-profile-next is-{{ $callout[0] === 'info' ? 'primary' : $callout[0] }}" role="status" data-tenancy-next-step>
                    <i class="bi bi-{{ $callout[1] }}" aria-hidden="true"></i>
                    <div class="flex-grow-1"><strong>{{ $callout[2] }}</strong><span>{{ $callout[3] }}</span></div>
                    @if($callout[4] && $callout[4][1])<a class="btn btn-sm btn-primary" href="{{ $callout[4][1] }}">{{ $callout[4][0] }}</a>
                    @elseif($sum['overdue'] > 0 && $canPay)<button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#account_payment_modal">Record payment</button>@endif
                </div>
            @endif

            <div class="lead-profile-metrics" aria-label="Tenancy summary">
                <div><span>Rent</span><strong>{{ $cur }} {{ number_format((float) $tm->monthly_payment, 2) }}</strong><small>{{ ucfirst($freq) }} · {{ $tm->agreement_type }}</small></div>
                <div><span>{{ $sum['balance'] < 0 ? 'In credit' : 'Balance due' }}</span><strong class="{{ $sum['overdue'] > 0 ? 'text-danger' : '' }}">{{ $money(abs($sum['balance'])) }}</strong><small>{{ $lastPayment ? 'Last paid '.$lastPayment->payment_date->format('j M Y').' · '.$money($lastPayment->amount_paid) : 'No payments yet' }}</small></div>
                <div><span>Deposit held</span><strong>{{ $money($sum['deposit_held']) }}</strong><small>{{ $tm->status === 'Terminated' ? 'Settled at move-out' : ($tm->status === 'Transferred' ? 'Moved with the tenant' : ($sum['deposit_required'] > $sum['deposit_held'] + 0.01 ? $money($sum['deposit_required'] - $sum['deposit_held']).' still to pay' : 'of '.$money($sum['deposit_required']).' billed')) }}</small></div>
                <div><span>Agent</span><strong class="fs-6" data-tenancy-agent>@if($tm->agent){{ profile_link(agent_profile_url($tm->agent), $tm->agent->full_name, 'lead-profile-link') }}@else Not assigned @endif</strong><small>{{ $tm->lease_end_date ? 'Lease to '.$tm->lease_end_date->format('j M Y') : 'Open-ended lease' }}</small></div>
            </div>

            <nav class="nav lead-profile-tabs" role="tablist" aria-label="Tenancy sections">
                <a class="nav-link active" id="tenancy-tab-overview" data-bs-toggle="tab" href="#overview" role="tab" aria-controls="overview" aria-selected="true">Overview</a>
                <a class="nav-link" id="tenancy-tab-payments" data-bs-toggle="tab" href="#payments" role="tab" aria-controls="payments" aria-selected="false">Billing &amp; payments @if($sum['open_invoices'])<span class="lead-profile-count">{{ $sum['open_invoices'] }}</span>@endif</a>
                <a class="nav-link" id="tenancy-tab-notices" data-bs-toggle="tab" href="#notices" role="tab" aria-controls="notices" aria-selected="false">Notices <span class="lead-profile-count">{{ $tenantData['notice_stats']['total_notices'] ?? 0 }}</span></a>
                <a class="nav-link" id="tenancy-tab-documents" data-bs-toggle="tab" href="#documents" role="tab" aria-controls="documents" aria-selected="false">Documents <span class="lead-profile-count">{{ count($tenantData['documents'] ?? []) }}</span></a>
                @php
                    $tenancyRepairs = \App\Models\MaintenanceRequest::with('assignee:id,first_name,last_name')->where('managed_by', business_owner_id())->where('tenancy_id', $tm->id)->latest('reported_at')->get();
                    $openRepairs = $tenancyRepairs->filter->isOpen()->count();
                @endphp
                <a class="nav-link" id="tenancy-tab-maintenance" data-bs-toggle="tab" href="#maintenance" role="tab" aria-controls="maintenance" aria-selected="false">Maintenance @if($openRepairs)<span class="lead-profile-count">{{ $openRepairs }}</span>@endif</a>
            </nav>
        </section>

        @php
            $tenantRows = $isBusiness ? [
                $field('Business name', e($pd['business_name'] ?? '')),
                $field('Registration', e($pd['business_registration'] ?? '')),
                $field('Contact person', e($pd['business_contact_person'] ?? '')),
                $field('Business type', e($pd['business_type'] ?? '')),
                $field('Tax ID', e($pd['tax_id_number'] ?? '')),
                $field('Annual revenue', !empty($pd['annual_revenue']) ? e($cur.' '.number_format((float) $pd['annual_revenue'])) : null),
                $field('Years in business', e($pd['years_in_business'] ?? '')),
            ] : [
                $field('Full name', e($pd['tenant_name'] ?? '')),
                $field('ID number', e($pd['tenant_id_number'] ?? '')),
                $field('Date of birth', !empty($pd['date_of_birth']) ? \Carbon\Carbon::parse($pd['date_of_birth'])->format('j M Y') : null),
                $field('Gender', e($pd['gender'] ?? '')),
                $field('Nationality', e($pd['nationality'] ?? '')),
                $field('Occupation', e($pd['occupation'] ?? '')),
                $field('Monthly income', !empty($pd['monthly_income']) ? e($cur.' '.number_format((float) $pd['monthly_income'])) : null),
            ];
            $tenantRows[] = $field('Email', !empty($tp['primary_email']) ? '<a href="mailto:'.e($tp['primary_email']).'">'.e($tp['primary_email']).'</a>' : null);
            $tenantRows[] = $field('Phone', !empty($tp['primary_phone']) ? '<a href="tel:'.e($tp['primary_phone']).'">'.e($tp['primary_phone']).'</a>' : null);
            $tenantRows[] = $field('Emergency contact', !empty($pd['emergency_contact_name']) ? e($pd['emergency_contact_name']).(!empty($pd['emergency_contact_relationship']) ? ' · '.e($pd['emergency_contact_relationship']) : '').(!empty($pd['emergency_contact_phone']) ? '<span class="lead-profile-sub"><a href="tel:'.e($pd['emergency_contact_phone']).'">'.e($pd['emergency_contact_phone']).'</a></span>' : '') : '<span class="text-muted">None provided</span>', true);

            $badges = fn ($items, $label) => collect($items)->map(fn ($i) => '<span class="badge badge-light me-1 mb-1">'.e($label($i)).'</span>')->implode('');
            $legalGroups = collect($prop['assigned_legal_documents'] ?? [])->groupBy('legal_type')->map->count();
            $propertyRows = [
                $field('Property', $tm->complex ? profile_link(auth()->user()->hasBusinessPermission('complex_management', 'view') ? route('complex.show', $tm->complex->slug) : null, $tm->complex->title)->toHtml() : e($tm->complex_name)),
                $field('Unit', e($unit ?? '')),
                $field('Type', e($prop['listing_type'] ?? $tm->listing_type)),
                $field('Furnishing', e($prop['furnishing'] ?? '')),
                $field('Bedrooms · bathrooms', !empty($prop['bedrooms']) ? e(($prop['bedrooms'] ?? '–').' bed · '.($prop['bathrooms'] ?? '–').' bath') : null),
                $field('Area', !empty($prop['occupied_sq_area']) ? e(number_format((float) $prop['occupied_sq_area']).' sq ft occupied') : (!empty($prop['sq_area']) ? e(number_format((float) $prop['sq_area']).' sq ft') : null)),
                $field('Occupants', !empty($prop['number_of_occupants']) ? e($prop['number_of_occupants']) : null),
                $field('Pets', !empty($prop['has_pets']) ? e('Yes'.(!empty($prop['pet_details']) ? ' · '.$prop['pet_details'] : '')) : 'No'),
                $field('Parking', collect($prop['assigned_parking'] ?? [])->isNotEmpty() ? $badges($prop['assigned_parking'], fn ($p) => $p->parking_id) : '<span class="text-muted">None</span>', true),
                $field('Amenities', collect($prop['assigned_amenities'] ?? [])->isNotEmpty() ? $badges($prop['assigned_amenities'], fn ($a) => $a->amenity_type) : null, true),
                $field('Legal documents', $legalGroups->isNotEmpty() ? $legalGroups->map(fn ($c, $t) => '<span class="badge badge-light me-1 mb-1">'.e(ucfirst(str_replace('_', ' ', $t))).' · '.$c.'</span>')->implode('') : null, true),
            ];

            $agreementRows = [
                $field('Agent', $tm->agent ? profile_link(agent_profile_url($tm->agent), $tm->agent->full_name)->toHtml() : '<span class="text-muted">Not assigned</span>'),
                $field('From lead', $sourceLead ? profile_link(lead_profile_url($sourceLead), $sourceLead->full_name)->toHtml() : null),
                $field('Agreement', e(ucfirst($ag['agreement_type'] ?? $tm->agreement_type))),
                $field('Rent', e($cur.' '.number_format((float) $tm->monthly_payment, 2).' '.$freq)),
                $field('Security deposit', e($cur.' '.number_format((float) ($ag['security_deposit'] ?? $tm->security_deposit ?? 0), 2))),
                $field('Service charge', ($ag['total_service_charge'] ?? 0) > 0 ? e($cur.' '.number_format((float) $ag['total_service_charge']).' '.strtolower($ag['service_charge_payment_interval'] ?? '')) : null),
                $field('Lease', e(($tm->lease_start_date?->format('j M Y') ?? 'Not set').' – '.($tm->lease_end_date?->format('j M Y') ?? 'open-ended'))),
                $field('Rent due', e('Billed in advance, due '.((int) $tm->grace_period_days === 0 ? 'on the first day of each period' : $tm->grace_period_days.' '.\Illuminate\Support\Str::plural('day', (int) $tm->grace_period_days).' after each period starts'))),
                $field('Late fee', (float) $tm->late_fee_amount > 0 ? e($cur.' '.number_format((float) $tm->late_fee_amount, 2).' once rent is overdue') : '<span class="text-muted">None</span>'),
                $field('Notes', !empty($ag['general_notes']) ? nl2br(e($ag['general_notes'])) : null, true),
            ];
            $renderRows = fn ($rows) => collect($rows)->filter(fn ($r) => filled(trim(strip_tags((string) $r['value']))) || str_contains((string) $r['value'], 'text-muted'));
            $activities = array_slice($tenantData['recent_activity'] ?? [], 0, 6);
            $actIcon = ['payment' => ['bi-cash-coin', 'success'], 'notice' => ['bi-bell', 'warning']];
        @endphp

        <div class="tab-content" id="myTabContent">
            {{-- Overview --}}
            <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="tenancy-tab-overview">
                <div class="row g-6">
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="tenancy-tenant-title">
                            <h3 id="tenancy-tenant-title">{{ $isBusiness ? 'Tenant (business)' : 'Tenant' }}</h3>
                            <dl class="lead-profile-fields">
                                @foreach($renderRows($tenantRows) as $row)<div class="{{ $row['wide'] ? 'is-wide' : '' }}"><dt>{{ $row['label'] }}</dt><dd>{!! $row['value'] !!}</dd></div>@endforeach
                            </dl>
                        </section>
                    </div>
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="tenancy-agreement-title">
                            <h3 id="tenancy-agreement-title">Agreement</h3>
                            <dl class="lead-profile-fields">
                                @foreach($renderRows($agreementRows) as $row)<div class="{{ $row['wide'] ? 'is-wide' : '' }}" @if($row['label'] === 'Agent') data-tenancy-agent-row @endif><dt>{{ $row['label'] }}</dt><dd>{!! $row['value'] !!}</dd></div>@endforeach
                            </dl>
                        </section>
                    </div>
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="tenancy-property-title">
                            <h3 id="tenancy-property-title">Property &amp; unit</h3>
                            <dl class="lead-profile-fields">
                                @foreach($renderRows($propertyRows) as $row)<div class="{{ $row['wide'] ? 'is-wide' : '' }}"><dt>{{ $row['label'] }}</dt><dd>{!! $row['value'] !!}</dd></div>@endforeach
                            </dl>
                        </section>
                    </div>
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="tenancy-account-title">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <h3 id="tenancy-account-title" class="mb-0">Account</h3>
                                <a href="#payments" class="fs-7 fw-semibold" data-open-billing>Open billing <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                            </div>
                            <dl class="lead-profile-fields mb-5">
                                <div><dt>{{ $sum['balance'] < 0 ? 'In credit' : 'Balance due' }}</dt><dd class="{{ $sum['overdue'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $money(abs($sum['balance'])) }}</dd></div>
                                <div><dt>Overdue</dt><dd class="{{ $sum['overdue'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $money($sum['overdue']) }}</dd></div>
                                <div><dt>Credit</dt><dd>{{ $money($sum['credit']) }}</dd></div>
                                <div><dt>Deposit held</dt><dd>{{ $money($sum['deposit_held']) }}</dd></div>
                            </dl>
                            <h4 class="fs-6 fw-semibold text-gray-700 mb-3">Recent activity</h4>
                            @if(empty($activities))
                                <p class="text-muted fs-7 mb-0">No activity yet.</p>
                            @else
                            <ol class="lead-profile-timeline">
                                @foreach($activities as $activity)
                                    @php [$icon, $tone] = $actIcon[$activity['type']] ?? ['bi-info-circle', 'info']; $when = \Carbon\Carbon::parse($activity['date']); @endphp
                                    <li class="lead-profile-event">
                                        <span class="lead-profile-event-icon is-{{ $tone }}" aria-hidden="true"><i class="bi {{ $icon }}"></i></span>
                                        <div class="lead-profile-event-body">
                                            <div class="lead-profile-event-head"><strong>{{ \Illuminate\Support\Str::ucfirst(mb_strtolower($activity['title'])) }}</strong><time datetime="{{ $when->toIso8601String() }}">{{ $when->format('j M Y') }}</time></div>
                                            @if(!empty($activity['description']))<p>{{ $activity['description'] }}</p>@endif
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                            @endif
                        </section>
                    </div>
                </div>
            </div>
            <!--end::Tab Pane - Overview-->
            <script>
                document.addEventListener('click', function (e) {
                    const link = e.target.closest('[data-open-billing]');
                    if (!link) return;
                    e.preventDefault();
                    const tab = document.querySelector('a[data-bs-toggle="tab"][href="#payments"]');
                    if (tab && window.bootstrap) { bootstrap.Tab.getOrCreateInstance(tab).show(); tab.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
                });
            </script>

            <!--begin::Tab Pane - Payments-->
            <div class="tab-pane fade" id="payments" role="tabpanel">
                @include('pages.business.tenant.tenancy.partials.account')
            </div>
            <!--end::Tab Pane - Payments-->

            <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="tenancy-tab-maintenance" data-tenancy-maintenance>
                <section class="lead-profile-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
                        <div><h2 class="fs-5 fw-bolder mb-1">Repairs for this tenancy</h2><p class="text-muted fs-7 mb-0">Requests logged against {{ $tm->unit_number ?: 'this unit' }} while this tenant is in it.</p></div>
                        @if(auth()->user()->hasBusinessPermission('complex_management', 'edit') || auth()->user()->hasBusinessPermission('tenant_management', 'create'))
                            <a class="btn btn-sm btn-primary" href="{{ route('maintenance.create', ['tenancy_id' => $tm->id]) }}"><i class="fas fa-plus"></i> Log a request</a>
                        @endif
                    </div>
                    @if($tenancyRepairs->isEmpty())
                        <p class="text-muted fs-7 mb-0">No repairs logged.</p>
                    @else
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle fs-7 mb-0">
                            <thead><tr class="text-muted fw-semibold text-uppercase fs-8"><th>Request</th><th>Status</th><th>Assigned to</th><th>Reported</th><th class="text-end">Cost</th><th></th></tr></thead>
                            <tbody>
                            @foreach($tenancyRepairs as $r)
                                <tr>
                                    <td class="fw-bold">{{ $r->title }}<div class="text-muted fs-8 fw-normal">{{ $r->reference }}</div></td>
                                    <td><span class="badge badge-light{{ $r->isOpen() ? '-warning' : '' }}">{{ \App\Models\MaintenanceRequest::STATUSES[$r->status] ?? $r->status }}</span></td>
                                    <td>{{ $r->assignee ? trim($r->assignee->first_name.' '.$r->assignee->last_name) : '—' }}</td>
                                    <td>{{ $r->reported_at?->format('j M Y') }}</td>
                                    <td class="text-end">{{ $r->cost !== null ? $money($r->cost) : '—' }}@if($r->cost_borne_by === 'tenant')<div class="text-muted fs-8">charged to tenant</div>@endif</td>
                                    <td class="text-end"><a class="btn btn-sm btn-light-primary" href="{{ route('maintenance.show', $r->reference) }}">Open</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </section>
            </div>
            
			
			<!--begin::Tab Pane - Notices-->
			<div class="tab-pane fade" id="notices" role="tabpanel">
				<!--begin::Card-->
				<div class="card">
					<!--begin::Card header-->
					<div class="card-header d-flex flex-row justify-content-between border-0 pt-6">
						<!--begin::Card title-->
						<div class="card-title">
							<!--begin::Search-->
							<div class="d-flex align-items-center position-relative my-1">
								<span class="svg-icon svg-icon-1 position-absolute ms-6">
									<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
										<rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"></rect>
										<path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black"></path>
									</svg>
								</span>
								<input type="text" id="notice_search" class="form-control form-control-solid w-250px ps-15" placeholder="Search notices..." />
							</div>
							<!--end::Search-->
						</div>
						<!--begin::Card title-->
						
						<!--begin::Card toolbar-->
						<div class="card-toolbar">
							<!--begin::Toolbar-->
							<div class="d-flex justify-content-end" data-kt-notice-table-toolbar="base">
								@businessCan('tenant_management', 'notices')
								<!--begin::Add notice-->
								<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_give_notice" data-tenant-id="{{ $tenantData['basic_info']['id'] }}" data-action="notice">
									<span class="svg-icon svg-icon-2">
										<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
											<rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="black"></rect>
											<rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="black"></rect>
										</svg>
									</span>
									Give Notice
								</button>
								<!--end::Add notice-->
								@endbusinessCan
							</div>
							<!--end::Toolbar-->
						</div>
						<!--end::Card toolbar-->
					</div>
					<!--end::Card header-->
					
					<!--begin::Card body-->
					<div class="card-body pt-0">
						<!--begin::Table-->
						<table class="table align-middle table-row-dashed fs-6 gy-5" id="kt_notices_table">
							<thead>
								<tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
									<th class="min-w-125px">Notice Date</th>
									<th class="min-w-150px">Type</th>
									<th class="min-w-200px">Subject</th>
									<th class="min-w-100px">Effective Date</th>
									<th class="min-w-100px">Priority</th>
									<th class="min-w-100px">Status</th>
									<th class="min-w-100px">Response</th>
									<th class="text-end min-w-100px">Actions</th>
								</tr>
							</thead>
							<tbody class="text-gray-600 fw-bold">
                                @forelse($tenantData['notices'] ?? [] as $notice)
                                    <tr>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-bolder text-hover-primary mb-1">{{ \Carbon\Carbon::parse($notice['notice_date'])->format('M j, Y') }}</span>
                                                <span class="text-muted fs-7">{{ \Carbon\Carbon::parse($notice['notice_date'])->diffForHumans() }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-primary fw-bolder fs-7">{{ $notice['notice_type_label'] }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-bolder text-hover-primary mb-1">{{ Str::limit($notice['notice_subject'], 30) }}</span>
                                                @if($notice['amount_involved'])
                                                    <span class="text-muted fs-7">Amount: KES {{ number_format($notice['amount_involved'], 2) }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-800 fw-bolder">{{ \Carbon\Carbon::parse($notice['effective_date'])->format('M j, Y') }}</span>
                                                @if($notice['notice_period'])
                                                    <span class="text-muted fs-7">{{ $notice['notice_period'] }} days notice</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $notice['priority_badge_class'] }}">{{ ucfirst($notice['priority_level'] ?? 'medium') }}</span>
                                        </td>
                                        <td>
                                            @switch($notice['delivery_status'])
                                                @case('delivered')
                                                    <span class="badge badge-light-success">Delivered</span>
                                                    @break
                                                @case('sent')
                                                    <span class="badge badge-light-primary">Sent</span>
                                                    @break
                                                @case('pending')
                                                    <span class="badge badge-light-warning">Pending</span>
                                                    @break
                                                @case('failed')
                                                    <span class="badge badge-light-danger">Failed</span>
                                                    @break
                                                @default
                                                    <span class="badge badge-light-secondary">{{ $notice['delivery_status_label'] }}</span>
                                            @endswitch
                                        </td>
                                        <td>
                                            @if($notice['response_required'])
                                                @if($notice['response_deadline'] && \Carbon\Carbon::parse($notice['response_deadline'])->isPast())
                                                    <span class="badge badge-light-danger">Overdue</span>
                                                @elseif($notice['response_deadline'])
                                                    <span class="badge badge-light-warning">Due {{ \Carbon\Carbon::parse($notice['response_deadline'])->format('M j') }}</span>
                                                @else
                                                    <span class="badge badge-light-info">Required</span>
                                                @endif
                                            @else
                                                <span class="text-muted fs-7">Not required</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <!--begin::Menu-->
                                            <a href="#" class="btn btn-light btn-active-light-primary btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                                                Actions
                                                <span class="svg-icon svg-icon-5 m-0">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                                    </svg>
                                                </span>
                                            </a>
                                            <!--begin::Menu-->
                                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-125px py-4" data-kt-menu="true">
                                                <!--begin::Menu item-->
                                                <div class="menu-item px-3">
                                                    <a href="#" class="menu-link px-3" data-notice-id="{{ $notice['id'] }}" onclick="viewNoticeDetails(this)">View Details</a>
                                                </div>
                                                <!--end::Menu item-->
                                                
                                                <!--begin::Menu item-->
                                                @if($notice['delivery_status'] === 'pending')
                                                    <div class="menu-item px-3">
                                                        <a href="#" class="menu-link px-3" data-notice-id="{{ $notice['id'] }}" onclick="sendNotice(this)">Send Now</a>
                                                    </div>
                                                @endif
                                                <!--end::Menu item-->
                                                
                                                <!--begin::Menu item-->
                                                @if($notice['follow_up_required'] && $notice['follow_up_date'] && \Carbon\Carbon::parse($notice['follow_up_date'])->isPast())
                                                    <div class="menu-item px-3">
                                                        <a href="#" class="menu-link px-3 text-warning" data-notice-id="{{ $notice['id'] }}" onclick="followUpNotice(this)">Follow Up</a>
                                                    </div>
                                                @endif
                                                <!--end::Menu item-->
                                                
                                                <!--begin::Menu item-->
                                                <div class="menu-item px-3">
                                                    <a href="#" class="menu-link px-3" data-notice-id="{{ $notice['id'] }}" onclick="editNotice(this)">Edit</a>
                                                </div>
                                                <!--end::Menu item-->
                                                
                                                <!--begin::Menu item-->
                                                <div class="menu-item px-3">
                                                    <a href="#" class="menu-link px-3 text-danger" data-notice-id="{{ $notice['id'] }}" onclick="deleteNotice(this)">Delete</a>
                                                </div>
                                                <!--end::Menu item-->
                                            </div>
                                            <!--end::Menu-->
                                        </td>
                                    </tr>
                                @empty
									<tr>
										<td colspan="8" class="text-center py-10">
											<div class="d-flex flex-column align-items-center">
												<span class="svg-icon svg-icon-muted svg-icon-2hx mb-4">
													<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
														<path opacity="0.3" d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z" fill="black"/>
														<path d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C6.1 15 7 14.1 7 13V10C7 7.6 8.7 5.4 11.1 5.1C11.3 5.1 11.6 5 12 5C12.4 5 12.7 5.1 12.9 5.1C15.3 5.4 17 7.6 17 10V13C17 14.1 17.9 15 19 15Z" fill="black"/>
													</svg>
												</span>
												<span class="text-gray-500 fs-6 fw-bold">No notices given</span>
												<span class="text-gray-400 fs-7">Start by giving the first notice to this tenant</span>
											</div>
										</td>
									</tr>
								@endforelse
							</tbody>
						</table>
						<!--end::Table-->
						
						<!--begin::Notice Summary-->
                        @if(count($tenantData['notices'] ?? []) > 0)
                            <div class="row mt-8">
                                <div class="col-12">
                                    <div class="card bg-light-warning">
                                        <div class="card-body p-6">
                                            <div class="d-flex align-items-center mb-4">
                                                <span class="svg-icon svg-icon-2 svg-icon-warning me-3">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <path opacity="0.3" d="M12 22C13.6569 22 15 20.6569 15 19C15 17.3431 13.6569 16 12 16C10.3431 16 9 17.3431 9 19C9 20.6569 10.3431 22 12 22Z" fill="black"/>
                                                        <path d="M19 15V18C19 18.6 18.6 19 18 19H6C5.4 19 5 18.6 5 18V15C6.1 15 7 14.1 7 13V10C7 7.6 8.7 5.4 11.1 5.1C11.3 5.1 11.6 5 12 5C12.4 5 12.7 5.1 12.9 5.1C15.3 5.4 17 7.6 17 10V13C17 14.1 17.9 15 19 15Z" fill="black"/>
                                                    </svg>
                                                </span>
                                                <h3 class="mb-0 text-gray-900">Notice Summary</h3>
                                            </div>
                                            <div class="row text-center">
                                                <div class="col-md-2 mb-4">
                                                    <div class="fs-2 fw-bolder text-warning">{{ count($tenantData['notices'] ?? []) }}</div>
                                                    <div class="fs-6 text-muted">Total Notices</div>
                                                </div>
                                                <div class="col-md-2 mb-4">
                                                    @php
                                                        $deliveredCount = 0;
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if(($notice['delivery_status'] ?? '') === 'delivered') {
                                                                $deliveredCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-2 fw-bolder text-success">{{ $deliveredCount }}</div>
                                                    <div class="fs-6 text-muted">Delivered</div>
                                                </div>
                                                <div class="col-md-2 mb-4">
                                                    @php
                                                        $pendingCount = 0;
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if(in_array($notice['delivery_status'] ?? '', ['pending', 'sent'])) {
                                                                $pendingCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-2 fw-bolder text-primary">{{ $pendingCount }}</div>
                                                    <div class="fs-6 text-muted">Pending</div>
                                                </div>
                                                <div class="col-md-2 mb-4">
                                                    @php
                                                        $overdueCount = 0;
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if(($notice['response_required'] ?? false) && 
                                                               ($notice['response_deadline'] ?? false) && 
                                                               \Carbon\Carbon::parse($notice['response_deadline'])->isPast()) {
                                                                $overdueCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-2 fw-bolder text-danger">{{ $overdueCount }}</div>
                                                    <div class="fs-6 text-muted">Overdue</div>
                                                </div>
                                                <div class="col-md-2 mb-4">
                                                    @php
                                                        $followUpCount = 0;
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if(($notice['follow_up_required'] ?? false) && 
                                                               ($notice['follow_up_date'] ?? false) && 
                                                               \Carbon\Carbon::parse($notice['follow_up_date'])->isPast()) {
                                                                $followUpCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-2 fw-bolder text-info">{{ $followUpCount }}</div>
                                                    <div class="fs-6 text-muted">Follow-ups</div>
                                                </div>
                                                <div class="col-md-2 mb-4">
                                                    @php
                                                        $highPriorityCount = 0;
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if(in_array($notice['priority_level'] ?? '', ['high', 'urgent'])) {
                                                                $highPriorityCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-2 fw-bolder text-secondary">{{ $highPriorityCount }}</div>
                                                    <div class="fs-6 text-muted">High Priority</div>
                                                </div>
                                            </div>
                                            
                                            <!--begin::Additional Stats Row-->
                                            <div class="separator my-4"></div>
                                            <div class="row text-center">
                                                <div class="col-md-3 mb-4">
                                                    @php
                                                        $failedCount = 0;
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if(($notice['delivery_status'] ?? '') === 'failed') {
                                                                $failedCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-4 fw-bolder text-danger">{{ $failedCount }}</div>
                                                    <div class="fs-7 text-muted">Failed Delivery</div>
                                                </div>
                                                <div class="col-md-3 mb-4">
                                                    @php
                                                        $thisMonthCount = 0;
                                                        $thisMonth = now()->format('Y-m');
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if($notice['notice_date'] && 
                                                               \Carbon\Carbon::parse($notice['notice_date'])->format('Y-m') === $thisMonth) {
                                                                $thisMonthCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-4 fw-bolder text-primary">{{ $thisMonthCount }}</div>
                                                    <div class="fs-7 text-muted">This Month</div>
                                                </div>
                                                <div class="col-md-3 mb-4">
                                                    @php
                                                        $responseRequiredCount = 0;
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if(($notice['response_required'] ?? false)) {
                                                                $responseRequiredCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-4 fw-bolder text-warning">{{ $responseRequiredCount }}</div>
                                                    <div class="fs-7 text-muted">Response Required</div>
                                                </div>
                                                <div class="col-md-3 mb-4">
                                                    @php
                                                        $recentCount = 0;
                                                        $lastWeek = now()->subWeek();
                                                        foreach($tenantData['notices'] ?? [] as $notice) {
                                                            if($notice['notice_date'] && 
                                                               \Carbon\Carbon::parse($notice['notice_date'])->greaterThan($lastWeek)) {
                                                                $recentCount++;
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="fs-4 fw-bolder text-info">{{ $recentCount }}</div>
                                                    <div class="fs-7 text-muted">Last 7 Days</div>
                                                </div>
                                            </div>
                                            <!--end::Additional Stats Row-->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
						<!--end::Notice Summary-->
					</div>
					<!--end::Card body-->
				</div>
				<!--end::Card-->
			</div>
			<!--end::Tab Pane - Notices-->
            
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <div class="row g-6 g-xl-9">

                    {{-- Uploaded Tenant Documents --}}
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header border-0 pt-6">
                                <div class="card-title">
                                    <h3 class="card-label fw-bolder text-dark">Uploaded Documents
                                        <span class="badge badge-light-primary ms-2">{{ count($tenantData['documents'] ?? []) }}</span>
                                    </h3>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                @if(!empty($tenantData['documents']))
                                <div class="table-responsive">
                                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                        <thead>
                                            <tr class="fw-bolder text-muted">
                                                <th class="min-w-200px">File Name</th>
                                                <th class="min-w-100px">Type</th>
                                                <th class="min-w-100px">Size</th>
                                                <th class="min-w-150px">Uploaded</th>
                                                <th class="min-w-100px text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tenantData['documents'] as $doc)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        @php
                                                            $ext = strtolower(pathinfo($doc['name'] ?? '', PATHINFO_EXTENSION));
                                                            $iconColor = match($ext) {
                                                                'pdf'               => 'text-danger',
                                                                'doc', 'docx'       => 'text-primary',
                                                                'xls', 'xlsx'       => 'text-success',
                                                                'jpg', 'jpeg',
                                                                'png', 'gif',
                                                                'webp'              => 'text-warning',
                                                                default             => 'text-gray-500',
                                                            };
                                                        @endphp
                                                        <div class="symbol symbol-40px me-3">
                                                            <span class="symbol-label {{ $iconColor }} bg-light">
                                                                <i class="ki-duotone ki-document fs-2x {{ $iconColor }}">
                                                                    <span class="path1"></span><span class="path2"></span>
                                                                </i>
                                                            </span>
                                                        </div>
                                                        <span class="text-dark fw-bold fs-6">{{ $doc['name'] }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-secondary fs-7">{{ strtoupper($ext ?: '—') }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted fw-semibold fs-7">{{ $doc['size'] ?? '—' }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted fw-semibold fs-7">{{ $doc['uploaded_at'] ?? '—' }}</span>
                                                </td>
                                                <td class="text-end">
                                                    @if(!empty($doc['url']) && !empty($doc['exists']))
                                                        <a href="{{ $doc['url'] }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light-primary me-1">
                                                            <i class="ki-duotone ki-eye fs-5"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                                                            View
                                                        </a>
                                                        <a href="{{ $doc['url'] }}" download class="btn btn-sm btn-light">
                                                            <i class="ki-duotone ki-cloud-download fs-5"><span class="path1"></span><span class="path2"></span></i>
                                                            Download
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
                                <div class="text-center py-10">
                                    <i class="ki-duotone ki-folder fs-3x text-gray-300 mb-3 d-block">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                    <div class="text-gray-500 fw-semibold fs-6">No documents uploaded for this tenancy.</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Assigned Legal Documents --}}
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header border-0 pt-6">
                                <div class="card-title">
                                    <h3 class="card-label fw-bolder text-dark">Legal Documents
                                        @php $legalDocs = $tenantData['property_details']['assigned_legal_documents'] ?? collect(); @endphp
                                        <span class="badge badge-light-warning ms-2">{{ $legalDocs->count() }}</span>
                                    </h3>
                                </div>
                            </div>
                            <div class="card-body pt-0">
                                @if($legalDocs->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                                        <thead>
                                            <tr class="fw-bolder text-muted">
                                                <th class="min-w-200px">Document</th>
                                                <th class="min-w-150px">Type</th>
                                                <th class="min-w-150px">Folder</th>
                                                <th class="min-w-200px">Description</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($legalDocs as $legal)
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="symbol symbol-40px me-3">
                                                            <span class="symbol-label bg-light-warning text-warning">
                                                                <i class="ki-duotone ki-security-user fs-2x">
                                                                    <span class="path1"></span><span class="path2"></span>
                                                                </i>
                                                            </span>
                                                        </div>
                                                        <span class="text-dark fw-bold fs-6">{{ $legal->file_name }}</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-light-warning fs-7">{{ $legal->legal_type }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted fw-semibold fs-7">{{ $legal->name }}</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted fw-semibold fs-7">{{ $legal->description ?? '—' }}</span>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @else
                                <div class="text-center py-10">
                                    <i class="ki-duotone ki-security-user fs-3x text-gray-300 mb-3 d-block">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                    <div class="text-gray-500 fw-semibold fs-6">No legal documents assigned to this tenancy.</div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!--end::Tab Content-->

		<!--begin::Modal - Record Payment-->
		{{-- Payments on this page use the account tab's own form (partials/account). --}}
		<!--end::Modal - Record Payment-->

		<!--begin::Modal - Give Notice-->
		@include('pages.business.tenant.include.notice_modal')
		<!--end::Modal - Give Notice-->

		<!--begin::Modal - End Tenancy-->
		@include('pages.business.tenant.include.tenancy_modal')
		<!--end::Modal - End Tenancy-->
    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

@businessCan('tenant_management', 'edit')
@if($tenant->status === 'Active')
@include('pages.business.tenant.partials.edit-tenancy')
@endif
@endbusinessCan
@endsection
