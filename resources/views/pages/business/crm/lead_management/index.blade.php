@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Lead Management</h1>
            <!--end::Heading-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    @businessCan('crm_management', 'view')<a href="/customer_relationship" class="text-muted text-hover-primary">CRM</a>@else<span class="text-muted">CRM</span>@endbusinessCan
                </li>
                <li class="breadcrumb-item text-dark">Lead Management</li>
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
<div class="content d-flex flex-column flex-column-fluid crm-lead-index" id="kt_content">
    <!--begin::Container-->
    <div class="container-xxl crm-lead-index-content" id="kt_content_container">

        @businessCan('crm_management', 'view')
        <a href="/customer_relationship" class="btn btn-light crm-back-dashboard-link">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <span>Back to CRM dashboard</span>
        </a>
        @endbusinessCan
        
        <!--begin::Lead Stats Row-->
        <div class="row g-3 mb-5 crm-lead-stats">
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-lead-stat-card">
                    <span class="crm-lead-stat-icon"><i class="fas fa-users" aria-hidden="true"></i></span>
                    <div><strong>{{ $stats['total_active'] ?? 0 }}</strong><span>Active leads</span></div>
                </section>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-lead-stat-card">
                    <span class="crm-lead-stat-icon"><i class="fas fa-user-plus" aria-hidden="true"></i></span>
                    <div><strong>{{ $stats['new_this_week'] ?? 0 }}</strong><span>New this week</span></div>
                </section>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-lead-stat-card">
                    <span class="crm-lead-stat-icon"><i class="fas fa-user-check" aria-hidden="true"></i></span>
                    <div><strong>{{ $stats['qualified'] ?? 0 }}</strong><span>Qualified leads</span></div>
                </section>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-lead-stat-card">
                    <span class="crm-lead-stat-icon"><i class="fas fa-chart-line" aria-hidden="true"></i></span>
                    <div><strong>{{ $stats['conversion_rate'] ?? 0 }}<small>%</small></strong><span>Conversion rate</span></div>
                </section>
            </div>
        </div>
        <!--end::Lead Stats Row-->

        <!--begin::Leads Cards-->
        <div class="card rounded crm-lead-list">

            <!-- Header -->
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title">Leads</h3>

                <div class="d-flex gap-3">
                    <button class="btn btn-light-primary" data-bs-toggle="modal" data-bs-target="#kt_modal_upload_leads">
                        Upload Leads
                    </button>
                    @businessCan('lead_management', 'create')<a href="/customer_relationship/lead_management/onboarding/lead" class="btn btn-primary">
                        Add New Lead
                    </a>@endbusinessCan
                </div>
            </div>

            <!-- Filters -->
            <div class="card-body border-bottom crm-lead-filters">
                <form id="kt_lead_management_search_form">

                    <!-- Top Row -->
                    <div class="row g-3 align-items-end">

                        <!-- Search -->
                        <div class="col-12 col-xl-3">
                            <label class="form-label fw-bold">Search leads</label>
                            <input type="text"
                                name="search"
                                class="form-control form-control-solid"
                                placeholder="Search by name, phone, or email">
                        </div>

                        <!-- Lead Type -->
                        <div class="col-12 col-sm-6 col-xl-3">
                            <label class="form-label fw-bold">Lead type</label>
                            <select name="lead_type"
                                    class="form-select form-select-solid"
                                    data-control="select2"
                                    data-placeholder="Select lead type"
                                    data-allow-clear="true">
                                <option></option>
                                <option value="Individual">Individual</option>
                                <option value="Business">Business</option>
                            </select>
                        </div>

                        

                        <!-- Actions -->
                        <div class="col-12 col-xl-3 d-flex gap-2 crm-lead-filter-actions">
                            <button type="button" class="btn btn-primary" id="kt_button_search_leads">
                                <span class="indicator-label"><i class="fas fa-search me-2" aria-hidden="true"></i>Search</span>
                                <span class="indicator-progress">
                                    Please wait...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span> 
                                </span> 
                            </button>

                            <button type="button"
                                    class="btn btn-light crm-more-filters-button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#advancedFilters" aria-expanded="false" aria-controls="advancedFilters">
                                <i class="fas fa-sliders-h me-2" aria-hidden="true"></i><span>More filters</span>
                            </button>
                        </div>

                    </div>

                    <!-- Advanced Filters -->
                    <div class="collapse mt-4 crm-lead-advanced-filters" id="advancedFilters">
                        <div class="row g-3">

                            <!-- Status -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Lead Status</label>
                                <select name="lead_status"
                                        class="form-select form-select-solid"
                                        multiple="multiple"
                                        data-control="select2"
                                        data-placeholder="Select status"
                                        data-allow-clear="true">
                                    <option></option>
                                    <option value="new">New</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="qualified">Qualified</option>
                                    <option value="proposal">Proposal</option>
                                    <option value="closed_won">Closed Won</option>
                                    <option value="closed_lost">Closed Lost</option>
                                </select>
                            </div>

                            <!-- Priority -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Priority Level</label>
                                <select name="priority_level"
                                        class="form-select form-select-solid"
                                        data-control="select2"
                                        data-placeholder="Select priority"
                                        data-allow-clear="true">
                                    <option></option>
                                    <option value="hot">Hot - Ready to move</option>
                                    <option value="warm">Warm - Actively looking</option>
                                    <option value="cold">Cold - Just browsing</option>
                                </select>
                            </div>

                            <!-- Property Type -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Property Type</label>
                                <select name="property_type"
                                        class="form-select form-select-solid"
                                        data-control="select2"
                                        data-placeholder="Select property type"
                                        data-allow-clear="true">
                                    <option></option>
                                    @foreach($property_types as $property_type)
                                        <option value="{{$property_type->property_type}}">
                                            {{$property_type->property_type}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Assigned Agent -->
                            <div class="col-md-3">
                                <label class="form-label fw-bold">Assigned Agent</label>
                                <select name="assigned_agent_id"
                                        class="form-select form-select-solid"
                                        data-control="select2"
                                        data-placeholder="Select an agent"
                                        data-ajax-url="{{ route('agents.search') }}"
                                        data-allow-clear="true">
                                </select>
                            </div>

                        </div>
                    </div>

                </form>
            </div>

            <!-- Results -->
            <div class="card-body crm-lead-results">
                <div id="leads-results">
                    <div class="crm-lead-search-prompt d-flex flex-column align-items-center justify-content-center text-center py-10">
                        <span class="crm-lead-search-prompt-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
                        <h3>Search to view leads</h3>
                        <p>Enter a name, phone number, or email, or choose a filter above.</p>
                    </div>
                </div>
            </div>

        </div>
        <!--end::Leads Cards-->

    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

@include('pages.business.crm.lead_management.include.modals')

@endsection
