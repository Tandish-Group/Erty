@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Dashboard</h1>
            <!--end::Heading-->
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
<div class="content d-flex flex-column flex-column-fluid business-dashboard" id="kt_content">
    <div class="container-xxl py-6 business-dashboard-content" id="kt_content_container">

        <section class="dashboard-overview">
          <div class="dashboard-intro">
            <div>
                <span class="dashboard-intro-eyebrow">Portfolio overview</span>
                <h2>Good to see you,
                    @if(auth()->user()->business->account_type == 'individual')
                        {{ ucfirst(auth()->user()->first_name) }} {{ ucfirst(auth()->user()->last_name) }}
                    @else
                        {{ ucfirst(auth()->user()->business->business_name) }}
                    @endif
                </h2>
                <p>Your properties, tenants, and daily activity in one place.</p>
            </div>
          </div>
          @include('pages.business.workflow.overview')
        </section>

        {{-- Portfolio summary --}}
        @php
            $listingsModule = app(\App\Services\Access\ModuleService::class)->enabled(\App\Services\Access\ModuleService::LISTINGS);
            $me = auth()->user();
            // Cards follow the viewer's role: a section they cannot open is not summarised here.
            $show = [
                'complexes' => $me->hasBusinessPermission('complex_management', 'view'),
                'listings' => $listingsModule && $me->hasBusinessPermission('listings_management', 'view'),
                'tenants' => $me->hasBusinessPermission('tenant_management', 'view'),
                'leads' => $me->hasBusinessPermission('lead_management', 'view'),
                'pipeline' => $me->hasBusinessPermission('lead_management', 'view'),
                'financials' => $me->hasBusinessPermission('tenant_management', 'billing') || $me->hasBusinessPermission('tenant_management', 'payments') || $me->hasBusinessPermission('reports', 'financial'),
            ];
            $kpiCount = max(1, count(array_filter(array_intersect_key($show, array_flip(['complexes', 'listings', 'tenants', 'leads'])))));
            $detailCount = max(1, count(array_filter(array_intersect_key($show, array_flip(['pipeline', 'financials'])))));
            $kpiCol = 'col-xl-' . intdiv(12, $kpiCount);
            $detailCol = 'col-lg-' . intdiv(12, $detailCount);
        @endphp
        <div class="row g-4 g-xl-5 mb-0 dashboard-kpis">
            @if($show['complexes'])
            {{-- Complexes --}}
            <div class="col-sm-6 {{ $kpiCol }}">
                <section class="dashboard-stat-card dashboard-stat-complexes" data-walkthrough="complexes">
                    <div class="dashboard-stat-heading"><span class="dashboard-stat-icon"><i class="fas fa-building" aria-hidden="true"></i></span><span>Complexes</span></div>
                    <div class="dashboard-stat-main"><strong>{{ $complexStats['total'] }}</strong><small>Complexes you manage</small></div>
                    <div class="dashboard-stat-breakdown">
                        <span><b>{{ $complexStats['residential_units'] + $complexStats['commercial_units'] }}</b><small>Total units</small></span>
                        <span><b>{{ $complexStats['residential_vacant'] + $complexStats['commercial_vacant'] }}</b><small>Available</small></span>
                    </div>
                    <a href="/complex" class="dashboard-stat-link">View complexes <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </section>
            </div>
            @endif

            @if($show['listings'])
            {{-- Listings --}}
            <div class="col-sm-6 {{ $kpiCol }}">
                <section class="dashboard-stat-card dashboard-stat-listings" data-walkthrough="listings">
                    <div class="dashboard-stat-heading"><span class="dashboard-stat-icon"><i class="fas fa-house" aria-hidden="true"></i></span><span>Listings</span></div>
                    <div class="dashboard-stat-main"><strong>{{ $listingStats['published'] }}</strong><small>Unit types published for renters</small></div>
                    <div class="dashboard-stat-breakdown">
                        <span><b>{{ $listingStats['available'] }}</b><small>Units on offer</small></span>
                        <span><b>{{ $listingStats['draft'] }}</b><small>Drafts</small></span>
                    </div>
                    <a href="{{ route('listings.index') }}" class="dashboard-stat-link">View listings <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </section>
            </div>
            @endif

            @if($show['tenants'])
            {{-- Tenants --}}
            <div class="col-sm-6 {{ $kpiCol }}">
                <section class="dashboard-stat-card dashboard-stat-tenants" data-walkthrough="tenants">
                    <div class="dashboard-stat-heading"><span class="dashboard-stat-icon"><i class="fas fa-users" aria-hidden="true"></i></span><span>Tenants</span></div>
                    <div class="dashboard-stat-main"><strong>{{ $tenantStats['total'] }}</strong><small>People in active tenancy agreements</small></div>
                    <div class="dashboard-stat-breakdown">
                        <span><b>{{ $tenantStats['active'] }}</b><small>Active tenancies</small></span>
                        <span><b>{{ $tenantStats['expiring_soon'] }}</b><small>Expiring soon</small></span>
                    </div>
                    <a href="/tenant_management/tenants" class="dashboard-stat-link">View tenants <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </section>
            </div>
            @endif

            @if($show['leads'])
            {{-- Leads --}}
            <div class="col-sm-6 {{ $kpiCol }}">
                <section class="dashboard-stat-card dashboard-stat-leads" data-walkthrough="leads">
                    <div class="dashboard-stat-heading"><span class="dashboard-stat-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span><span>Leads</span></div>
                    <div class="dashboard-stat-main"><strong>{{ $leadStats['total'] }}</strong><small>Prospective tenants in your pipeline</small></div>
                    <div class="dashboard-stat-breakdown">
                        <span><b>{{ $leadStats['open'] }}</b><small>Open</small></span>
                        <span><b>{{ $leadStats['hot'] }}</b><small>Hot</small></span>
                    </div>
                    <a href="/customer_relationship/lead_management" class="dashboard-stat-link">View CRM <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </section>
            </div>
            @endif
        </div>

        {{-- ================================================================ --}}
        {{-- ROW 2: Detail Cards --}}
        {{-- ================================================================ --}}
        <div class="row g-5 g-xl-8 mb-7 dashboard-details">

            @if($show['pipeline'])
            {{-- Lead Pipeline --}}
            <div class="col-12 {{ $detailCol }}">
                <div class="card h-100 border-0 shadow-sm dashboard-detail-card dashboard-lead-pipeline">
                    <div class="card-header border-0 pt-6 d-flex flex-row justify-content-between align-items-center">
                        <h5 class="card-title fw-bold"><span class="dashboard-detail-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span>Lead Pipeline</h5>
                        <div class="card-toolbar">
                            @businessCan('crm_management', 'view')<a href="/customer_relationship" class="btn btn-sm btn-light-warning">Go to CRM</a>@else<a href="/customer_relationship/lead_management" class="btn btn-sm btn-light-warning">Open leads</a>@endbusinessCan
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <div class="d-flex align-items-center py-3 border-bottom">
                            <span class="bullet w-8px h-8px rounded-2 bg-secondary me-3 flex-shrink-0"></span>
                            <div class="flex-grow-1 fw-semibold text-gray-700">Open Leads</div>
                            <span class="badge badge-light-secondary fw-bold">{{ $leadStats['open'] }}</span>
                        </div>
                        <div class="d-flex align-items-center py-3 border-bottom">
                            <span class="bullet w-8px h-8px rounded-2 bg-danger me-3 flex-shrink-0"></span>
                            <div class="flex-grow-1 fw-semibold text-gray-700">Hot Leads</div>
                            <span class="badge badge-light-danger fw-bold">{{ $leadStats['hot'] }}</span>
                        </div>
                        <div class="d-flex align-items-center py-3 border-bottom">
                            <span class="bullet w-8px h-8px rounded-2 bg-primary me-3 flex-shrink-0"></span>
                            <div class="flex-grow-1 fw-semibold text-gray-700">New This Week</div>
                            <span class="badge badge-light-primary fw-bold">{{ $leadStats['new_this_week'] }}</span>
                        </div>
                        <div class="d-flex align-items-center py-3">
                            <span class="bullet w-8px h-8px rounded-2 bg-warning me-3 flex-shrink-0"></span>
                            <div class="flex-grow-1 fw-semibold text-gray-700">Follow-up Due Today</div>
                            <span class="badge {{ $leadStats['follow_up_today'] > 0 ? 'badge-light-warning' : 'badge-light-secondary' }} fw-bold">{{ $leadStats['follow_up_today'] }}</span>
                        </div>
                        @if($leadStats['follow_up_today'] > 0)
                        <div class="mt-5 p-4 rounded bg-light-warning d-flex align-items-center gap-3">
                            <i class="ki-duotone ki-notification-bing fs-2x text-warning"><span class="path1"></span><span class="path2"></span><span class="path3"></span></i>
                            <span class="text-gray-700 fs-7 fw-semibold">
                                You have <strong>{{ $leadStats['follow_up_today'] }}</strong>
                                follow-up{{ $leadStats['follow_up_today'] > 1 ? 's' : '' }} due today.
                            </span>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endif

            @if($show['financials'])
            {{-- Rent Financials --}}
            <div class="col-12 {{ $detailCol }}">
                <div class="card h-100 border-0 shadow-sm dashboard-detail-card dashboard-rent-financials">
                    <div class="card-header border-0 pt-6 d-flex flex-row justify-content-between align-items-center">
                        <h5 class="card-title fw-bold"><span class="dashboard-detail-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>Rent Financials</h5>
                        <div class="card-toolbar">
                            <a href="{{ auth()->user()->hasBusinessPermission('tenant_management', 'payments') ? '/tenant_management/payment-reconciliation' : '/tenant_management' }}" class="btn btn-sm btn-light-success">View All</a>
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <div class="mb-5 p-5 rounded bg-light-success text-center">
                            <div class="text-gray-500 fs-7 fw-semibold mb-1">Collected This Month</div>
                            <div class="text-success fw-bolder" style="font-size:2rem; line-height:1.2;">
                                KES {{ number_format($financialStats['collected_this_month']) }}
                            </div>
                        </div>
                        <div class="d-flex align-items-center py-3 border-bottom">
                            <span class="bullet w-8px h-8px rounded-2 bg-warning me-3 flex-shrink-0"></span>
                            <div class="flex-grow-1 fw-semibold text-gray-700">Pending Invoices</div>
                            <span class="badge badge-light-warning fw-bold">{{ $financialStats['pending_invoices'] }}</span>
                        </div>
                        <div class="d-flex align-items-center py-3 {{ $financialStats['overdue_amount'] > 0 ? 'border-bottom' : '' }}">
                            <span class="bullet w-8px h-8px rounded-2 bg-danger me-3 flex-shrink-0"></span>
                            <div class="flex-grow-1 fw-semibold text-gray-700">Overdue Invoices</div>
                            <span class="badge {{ $financialStats['overdue_count'] > 0 ? 'badge-light-danger' : 'badge-light-secondary' }} fw-bold">{{ $financialStats['overdue_count'] }}</span>
                        </div>
                        @if($financialStats['overdue_amount'] > 0)
                        <div class="d-flex align-items-center py-3">
                            <span class="bullet w-8px h-8px rounded-2 bg-danger me-3 flex-shrink-0"></span>
                            <div class="flex-grow-1 fw-semibold text-danger">Overdue Amount</div>
                            <span class="fw-bold text-danger">KES {{ number_format($financialStats['overdue_amount']) }}</span>
                        </div>
                        @endif
                        <div class="mt-5 p-4 rounded bg-light">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted fs-7">Active Tenancies</span>
                                <span class="fw-bold text-dark">{{ $tenantStats['active'] }}</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted fs-7">Expiring in 30 days</span>
                                <span class="fw-bold {{ $tenantStats['expiring_soon'] > 0 ? 'text-danger' : 'text-dark' }}">{{ $tenantStats['expiring_soon'] }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
        {{-- end detail row --}}

    </div>
</div>
<!--end::Content-->

@endsection
