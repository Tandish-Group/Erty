@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Agent Management</h1>
            <!--end::Heading-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    @businessCan('crm_management', 'view')<a href="/customer_relationship" class="text-muted text-hover-primary">CRM</a>@else<span class="text-muted">CRM</span>@endbusinessCan
                </li>
                <li class="breadcrumb-item text-dark">Agent Management</li>
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
<div class="content d-flex flex-column flex-column-fluid crm-agent-index" id="kt_content">
    <!--begin::Container-->
    <div class="container-xxl crm-agent-index-content" id="kt_content_container">
        @include('pages.business.agents.partials.nav')
        
        <div class="crm-agent-page-intro">
            <div>
                <span class="crm-agent-eyebrow">Team workspace</span>
                <p>Manage your agents, performance, and commission activity.</p>
            </div>
            @businessCan('crm_management', 'view')<a href="/customer_relationship" class="crm-agent-back-link"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to CRM dashboard</span></a>@endbusinessCan
        </div>

        <div class="row g-3 mb-5 crm-agent-stats">
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-agent-stat-card"><span class="crm-agent-stat-icon"><i class="bi bi-people" aria-hidden="true"></i></span><div><strong>{{ $agentStats['active'] }}</strong><span>Active agents</span></div></section>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-agent-stat-card"><span class="crm-agent-stat-icon"><i class="bi bi-award" aria-hidden="true"></i></span><div><strong>{{ $agentStats['top_performers'] }}</strong><span>Top performers</span></div></section>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-agent-stat-card"><span class="crm-agent-stat-icon"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></span><div><strong>Ksh {{ number_format($agentStats['total_sales']) }}</strong><span>Sales this month</span></div></section>
            </div>
            <div class="col-12 col-sm-6 col-xl-3">
                <section class="crm-agent-stat-card"><span class="crm-agent-stat-icon"><i class="bi bi-percent" aria-hidden="true"></i></span><div><strong>{{ $agentStats['avg_commission'] }}%</strong><span>Average commission</span></div></section>
            </div>
        </div>

        <!--begin::Agents Table-->
        <div class="row g-5 g-xl-10">
            <div class="col-12">
                <div class="card rounded">
                    <!--begin::Card header-->
                    <div class="card-header d-flex flex-row justify-content-between align-items-center border-0 crm-agent-toolbar">
                        <div class="crm-agent-toolbar-title">
                            <h2>Agents</h2>
                            <p>Directory and team details</p>
                        </div>
                        <!--begin::Card title-->
                        <div class="card-title">
                            <!--begin::Search-->
                            <div class="d-flex align-items-center position-relative my-1">
                                <i class="ki-duotone ki-magnifier fs-3 position-absolute ms-5">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <input type="text" data-kt-agents-table-filter="search" class="form-control form-control-solid w-350px ps-13" placeholder="Search agents" />
                            </div>
                            <!--end::Search-->
                        </div>
                        <!--begin::Card title-->
                        <!--begin::Card toolbar-->
                        <div class="card-toolbar">
                            <!--begin::Toolbar-->
                            <div class="d-flex justify-content-end" data-kt-agents-table-toolbar="base">
                                {{-- <a href="/customer_relationship" class="btn btn-light-secondary me-4">
                                    <span class="svg-icon svg-icon-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <path d="M9.60001 11H21C21.6 11 22 11.4 22 12C22 12.6 21.6 13 21 13H9.60001V11Z" fill="black"/>
                                            <path opacity="0.3" d="M9.6 20V4L2.3 11.3C1.9 11.7 1.9 12.3 2.3 12.7L9.6 20Z" fill="black"/>
                                        </svg>
                                    </span>Back
                                </a> --}}

                                @businessCan('agent_management', 'create')
                                <a href="/agents/onboarding" class="btn btn-primary me-3">
                                    <!--begin::Svg Icon | path: icons/duotune/arrows/arr075.svg-->
                                    <span class="svg-icon svg-icon-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <rect opacity="0.5" x="11.364" y="20.364" width="16" height="2" rx="1" transform="rotate(-90 11.364 20.364)" fill="black"></rect>
                                            <rect x="4.36396" y="11.364" width="16" height="2" rx="1" fill="black"></rect>
                                        </svg>
                                    </span>
                                    <!--end::Svg Icon-->Add Agent
                                </a>
                                @endbusinessCan

                                <a href="{{ route('tenant_management.agent_commissions') }}" class="btn btn-sm btn-light-primary d-flex align-items-center me-3">
                                    <i class="fas fa-user-tie me-1"></i> Agent Commissions
                                </a>

                                <!--begin::Trigger-->
                                <button type="button" class="btn btn-light-info" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-start">
                                    Actions
                                    <span class="svg-icon svg-icon-5 rotate-180 ms-3 me-0">...</span>
                                </button>
                                <!--end::Trigger-->

                                <!--begin::Menu-->
                                <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold fs-7 w-200px py-4"
                                    data-kt-menu="true">

                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="/agents/settings" class="menu-link px-3">
                                            Preconfigurations
                                        </a>
                                    </div>
                                    <!--end::Menu item-->

                                    @if($canInviteLogin)
                                    <!--begin::Menu item-->
                                    <div class="menu-item px-3">
                                        <a href="#" class="menu-link px-3" data-bs-toggle="modal" data-bs-target="#kt_modal_invite_agent">
                                            Invite agent who signs in
                                        </a>
                                    </div>
                                    <!--end::Menu item-->
                                    @endif

                                </div>
                                <!--end::Menu-->

                            </div>
                            <!--end::Toolbar-->
                        </div>
                        <!--end::Card toolbar-->
                    </div>
                    <!--end::Card header-->
                </div>

                <div class="row g-4" id="agents-results">
                    <!-- Agent cards will be injected here by JS -->
                </div>
            </div>
        </div>
        <!--end::Agents Table-->

    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

@include('pages.business.crm.agent_management.include.modal')

@endsection