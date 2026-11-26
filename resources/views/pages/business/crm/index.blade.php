@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Customer Relationship Management</h1>
            <!--end::Heading-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">Home</li>
                <li class="breadcrumb-item text-muted">Property Listing</li>
                <li class="breadcrumb-item text-dark">CRM Dashboard</li>
            </ul>
            <!--end::Breadcrumb-->
        </div>
        <!--end::Page title=-->
       
        <!--begin::Wrapper-->
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <!--begin::Aside mobile toggle-->
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <span class="svg-icon svg-icon-1 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                    </svg>
                </span>
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
<div class="content d-flex flex-column flex-column-fluid crm-dashboard" id="kt_content">
    <!--begin::Container-->
    <div class="container-xxl crm-dashboard-content" id="kt_content_container">

        <div class="card border-0 shadow-sm mb-7 crm-lifecycle-card">
            <div class="card-body p-7">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4 mb-6">
                    <div>
                        <div class="text-muted fw-semibold fs-7 mb-2">LEAD-TO-TENANT WORKSPACE</div>
                        <h2 class="text-dark fw-bolder mb-2">Move every prospect through one clear lifecycle</h2>
                        <div class="text-muted">Leads move through qualification, engagement and agreement before they are onboarded as tenants.</div>
                    </div>
                    @businessCan('lead_management', 'create')<a href="/customer_relationship/lead_management/onboarding/lead" class="btn btn-primary">Capture a lead</a>@endbusinessCan
                </div>

                <div class="row g-4">
                    <div class="col-12 col-md-6 col-xl-3">
                        <a href="/customer_relationship/lead_management" class="d-block h-100 text-decoration-none">
                            <div class="border rounded p-5 h-100 crm-lifecycle-step">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="fw-bolder text-dark">1. Capture & qualify</span>
                                    <span class="badge badge-primary">{{ $pipelineStats['qualification']['count'] ?? 0 }}</span>
                                </div>
                                <div class="text-muted fs-7">Record the source, contact details, property need and assigned employee.</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <a href="/customer_relationship/lead_management" class="d-block h-100 text-decoration-none">
                            <div class="border rounded p-5 h-100 crm-lifecycle-step">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="fw-bolder text-dark">2. Engage</span>
                                    <span class="badge badge-warning">{{ $pipelineStats['engagement']['count'] ?? 0 }}</span>
                                </div>
                                <div class="text-muted fs-7">Record calls and meetings. The lead's agent works its follow-up tasks under Agents → Tasks.</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <a href="/customer_relationship/lead_management" class="d-block h-100 text-decoration-none">
                            <div class="border rounded p-5 h-100 crm-lifecycle-step">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="fw-bolder text-dark">3. Agree terms</span>
                                    <span class="badge badge-info">{{ $pipelineStats['conversion']['count'] ?? 0 }}</span>
                                </div>
                                <div class="text-muted fs-7">Confirm the unit, rent and move-in date with the prospect before conversion.</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        @businessCan('tenant_management', 'create')<a href="/tenant_management/onboarding/tenant" class="d-block h-100 text-decoration-none">
                            <div class="border rounded p-5 h-100 crm-lifecycle-step">
                                <div class="d-flex align-items-center justify-content-between mb-3">
                                    <span class="fw-bolder text-dark">4. Convert & onboard</span>
                                    <span class="badge badge-success">{{ $pipelineStats['finalization']['count'] ?? 0 }}</span>
                                </div>
                                <div class="text-muted fs-7">Assign only to an available unit, complete tenant details and hand off to tenancy.</div>
                            </div>
                        </a>@endbusinessCan
                    </div>
                </div>
            </div>
        </div>
        
        <!--begin::CRM Modules Row-->
        <div class="row g-5 g-xl-10 mb-5 mb-xl-10">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">CRM Modules</span>
                            <span class="text-muted fw-semibold fs-7">Access all your customer management tools</span>
                        </h3>
                    </div>
                    <div class="card-body py-3">

                        <div class="row g-5">

                            <!-- Leads -->
                            <div class="col-12">
                                <a href="/customer_relationship/lead_management" class="card h-100 text-decoration-none card-hover">
                                    <div class="card-body d-flex flex-column justify-content-between p-6">

                                        <!-- Top -->
                                        <div class="d-flex align-items-center mb-4">
                                            <div class="symbol symbol-50px me-4">
                                                <span class="symbol-label bg-light-primary">
                                                    <i class="ki-duotone ki-user-tick fs-2x text-primary"></i>
                                                </span>
                                            </div>

                                            <div>
                                                <div class="fw-bold fs-5 text-dark">Leads Management</div>
                                                <div class="text-muted fs-7">
                                                    Capture, qualify and assign leads efficiently
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Stats -->
                                        <div class="d-flex justify-content-between mt-4">
                                            <div class="text-start">
                                                <div class="fw-bold fs-4 text-primary">{{ $leadStats['total_active'] }}</div>
                                                <div class="text-muted fs-7">Active</div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold fs-4 text-success">{{ $leadStats['new_this_week'] }}</div>
                                                <div class="text-muted fs-7">New</div>
                                            </div>
                                        </div>

                                    </div>
                                </a>
                            </div>

                            

                            

                        </div>
                        
                        <div class="separator separator-dashed my-6"></div>
                        
                        <div class="row g-5">

                            <!-- Reports -->
                            <div class="col-md-4">
                                <a href="/customer_relationship/reports" class="card h-100 text-decoration-none card-hover">
                                    <div class="card-body d-flex align-items-center p-5">

                                        <!-- Icon -->
                                        <div class="symbol symbol-45px me-4">
                                            <span class="symbol-label bg-light-danger">
                                                <i class="ki-duotone ki-chart-line fs-2x text-danger"></i>
                                            </span>
                                        </div>

                                        <!-- Text -->
                                        <div>
                                            <div class="fw-bold fs-6 text-dark">Reports & Analytics</div>
                                            <div class="text-muted fs-7">Performance insights</div>
                                        </div>

                                    </div>
                                </a>
                            </div>

                            <!-- Documents -->
                            <div class="col-md-4">
                                <a href="/customer_relationship/document-center" class="card h-100 text-decoration-none card-hover">
                                    <div class="card-body d-flex align-items-center p-5">

                                        <div class="symbol symbol-45px me-4">
                                            <span class="symbol-label bg-light-dark">
                                                <i class="ki-duotone ki-folder fs-2x text-dark"></i>
                                            </span>
                                        </div>

                                        <div>
                                            <div class="fw-bold fs-6 text-dark">Document Center</div>
                                            <div class="text-muted fs-7">File management</div>
                                        </div>

                                    </div>
                                </a>
                            </div>

                            

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--end::CRM Modules Row-->

        <!--begin::Content Row-->
        <div class="row g-5 g-xl-10">
            

            <!--begin::Col-->
            <div class="col-xl-6">
                <!--begin::Recent Leads-->
                <div class="card h-md-100">
                    <div class="card-header d-flex flex-row justify-content-between border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Recent Leads</span>
                            <span class="text-muted fw-semibold fs-7">Latest prospects</span>
                        </h3>
                        <div class="card-toolbar">
                            @businessCan('lead_management', 'create')<a href="/customer_relationship/lead_management/onboarding/lead" class="btn btn-sm btn-light-success">Add Lead</a>@endbusinessCan
                        </div>
                    </div>
                    <div class="card-body py-3">
                        @forelse($recentLeads as $lead)
                        <div class="d-flex align-items-center mb-6">
                            <div class="symbol symbol-45px me-5">
                                <div class="symbol-label fs-3 bg-light-{{ $lead['priority'] === 'high' ? 'success' : ($lead['priority'] === 'medium' ? 'warning' : 'info') }} text-{{ $lead['priority'] === 'high' ? 'success' : ($lead['priority'] === 'medium' ? 'warning' : 'info') }}">
                                    {{ $lead['initials'] }}
                                </div>
                            </div>
                            <div class="d-flex align-items-center flex-wrap w-100">
                                <div class="mb-1 pe-3 flex-grow-1">
                                    <a href="{{ $lead['url'] }}" class="fs-5 text-gray-800 text-hover-primary fw-bold">{{ $lead['name'] }}</a>
                                    <div class="text-muted fw-semibold fs-7">{{ $lead['description'] }}</div>
                                    <div class="text-muted fs-8">Added by {{ $lead['added_by'] ?? 'not recorded' }} · Agent: {{ profile_link($lead['agent_url'] ?? null, $lead['agent']) }}</div>
                                </div>
                                <div class="d-flex flex-column align-items-end">
                                    <span class="badge badge-light-{{ $lead['priority'] === 'high' ? 'success' : ($lead['priority'] === 'medium' ? 'warning' : 'info') }} mb-1">{{ $lead['priority_label'] }}</span>
                                    <span class="text-muted fs-7">{{ $lead['created_at'] }}</span>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-muted py-5">
                            <p class="fw-semibold">No recent leads</p>
                        </div>
                        @endforelse
                    </div>
                </div>
                <!--end::Recent Leads-->
            </div>
            <!--end::Col-->

            <!--begin::Col-->
            <div class="col-xl-6">
                <!--begin::Pipeline Status-->
                <div class="card h-md-100">
                    <div class="card-header border-0 pt-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold fs-3 mb-1">Pipeline Overview</span>
                            <span class="text-muted fw-semibold fs-7">Deal stages breakdown</span>
                        </h3>
                    </div>
                    <div class="card-body d-flex flex-column">
                        @foreach($pipelineStats as $phase => $stats)
                        <div class="d-flex align-items-center mb-5">
                            <div class="me-5">
                                <div class="symbol symbol-30px symbol-circle">
                                    <span class="symbol-label bg-light-{{ $stats['color'] }}">
                                        <i class="ki-duotone ki-{{ $stats['icon'] }} fs-3 text-{{ $stats['color'] }}">
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </span>
                                </div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="text-muted fw-semibold fs-7">{{ $stats['label'] }}</span>
                                    <span class="fw-bold fs-6">{{ $stats['count'] }} deals</span>
                                </div>
                                <div class="h-5px w-100 bg-light-{{ $stats['color'] }} rounded mb-2">
                                    <div class="bg-{{ $stats['color'] }} rounded h-5px" style="width: {{ $stats['progress'] }}%;"></div>
                                </div>
                                <span class="text-muted fw-semibold fs-7">R{{ number_format($stats['value'], 0) }}</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                <!--end::Pipeline Status-->
            </div>
            <!--end::Col-->
        </div>
        <!--end::Content Row-->

    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

@endsection
