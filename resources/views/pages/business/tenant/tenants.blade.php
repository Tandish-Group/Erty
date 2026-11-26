@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
		<!--begin::Page title-->
		<div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
			<!--begin::Heading-->
			<h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">All Tenants</h1>
			<!--end::Heading-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
				<li class="breadcrumb-item text-muted">Home</li>
				<li class="breadcrumb-item text-muted">{{auth()->user()->business['business_name']}}</li>
				<li class="breadcrumb-item text-muted"><a href="/tenant_management" class="text-muted text-hover-primary">Tenant Management</a></li>
				<li class="breadcrumb-item text-dark">All Tenants</li>
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

        <!--begin::Tenants Card-->
        <div class="card">
            <!--begin::Card header-->
            <div class="card-header d-flex flex-row justify-content-between border-0 pt-6">
                <!--begin::Card title-->
                <div class="card-title">
                    <!--begin::Search-->
                    <div class="d-flex align-items-center position-relative my-1">
                        <!--begin::Svg Icon-->
                        <span class="svg-icon svg-icon-1 position-absolute ms-6">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black" />
                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black" />
                            </svg>
                        </span>
                        <!--end::Svg Icon-->
                        <input type="text" data-kt-user-table-filter="search" class="form-control form-control-solid w-250px ps-14" placeholder="Search Tenants" />
                    </div>
                    <!--end::Search-->
                </div>
                <!--end::Card title-->
                <!--begin::Card toolbar-->
                <div class="card-toolbar">
                    <!--begin::Toolbar-->
                    <div class="d-flex flex-wrap justify-content-end align-items-center gap-2" data-kt-user-table-toolbar="base">

                        <!--begin::Actions dropdown-->
                        <div class="dropdown">
                            <button type="button" class="btn btn-light-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bolt me-1"></i>Actions
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end py-2" style="min-width: 190px;">
                                <li>
                                    <a href="/tenant_management" class="dropdown-item px-4 py-2">
                                        <i class="fas fa-arrow-left me-2 text-secondary"></i>Overview
                                    </a>
                                </li>
                                @businessCan('tenant_management', 'create')
                                <li>
                                    <a href="/tenant_management/onboarding/tenant" class="dropdown-item px-4 py-2">
                                        <i class="fas fa-user-plus me-2 text-success"></i>Add Tenant
                                    </a>
                                </li>
                                @endbusinessCan
                                @businessCan('tenant_management', 'export')
                                <li><hr class="dropdown-divider mx-3 my-1"></li>
                                <li>
                                    <a href="#" class="dropdown-item px-4 py-2" data-bs-toggle="modal" data-bs-target="#kt_modal_export_data">
                                        <i class="fas fa-file-export me-2 text-info"></i>Export Data
                                    </a>
                                </li>
                                @endbusinessCan
                            </ul>
                        </div>
                        <!--end::Actions dropdown-->

                        @businessCan('tenant_management', 'import')
                        <!--begin::Bulk Uploads dropdown-->
                        <button type="button" class="btn btn-light-info btn-sm" data-kt-menu-trigger="click" data-kt-menu-placement="bottom-start">
                            Bulk Uploads
                            <span class="svg-icon svg-icon-5 ms-2 me-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                    <path d="M11.4343 12.7344L7.25 8.55005C6.83579 8.13583 6.16421 8.13584 5.75 8.55005C5.33579 8.96426 5.33579 9.63583 5.75 10.05L11.2929 15.5929C11.6834 15.9835 12.3166 15.9835 12.7071 15.5929L18.25 10.05C18.6642 9.63584 18.6642 8.96426 18.25 8.55005C17.8358 8.13584 17.1642 8.13584 16.75 8.55005L12.5657 12.7344C12.2533 13.0468 11.7467 13.0468 11.4343 12.7344Z" fill="black"></path>
                                </svg>
                            </span>
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-bold w-250px fs-7 py-4" data-kt-menu="true">
                            <div class="menu-item px-3">
                                <div class="menu-content text-muted pb-2 px-3 fs-7 text-uppercase">Bulk Upload Options</div>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-bs-toggle="modal" data-bs-target="#kt_modal_bulk_upload" data-upload-type="tenants">
                                    <span class="menu-icon"><i class="fas fa-users text-primary"></i></span>
                                    <span class="menu-title">Bulk Upload Data</span>
                                </a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="/tenant_management/bulk-upload/mapping" class="menu-link px-3">
                                    <span class="menu-icon"><i class="fas fa-check text-primary"></i></span>
                                    <span class="menu-title">Data Confirmation</span>
                                </a>
                            </div>
                            <div class="separator my-2"></div>
                            <div class="menu-item px-3">
                                <div class="menu-content text-muted pb-2 px-3 fs-7 text-uppercase">Download Templates</div>
                            </div>
                            <div class="menu-item px-3">
                                <a href="/tenant_management/bulk-upload/template/tenants" class="menu-link px-3">
                                    <span class="menu-icon"><i class="fas fa-download"></i></span>
                                    <span class="menu-title">Tenants Template</span>
                                </a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="/tenant_management/bulk-upload/template/payments" class="menu-link px-3">
                                    <span class="menu-icon"><i class="fas fa-download"></i></span>
                                    <span class="menu-title">Payments Template</span>
                                </a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="/tenant_management/bulk-upload/template/notices" class="menu-link px-3">
                                    <span class="menu-icon"><i class="fas fa-download"></i></span>
                                    <span class="menu-title">Notices Template</span>
                                </a>
                            </div>
                        </div>
                        <!--end::Bulk Uploads dropdown-->
                        @endbusinessCan

                    </div>
                    <!--end::Toolbar-->

                    <!--begin::Modals-->
                    @include('pages.business.tenant.include.modals')
                    <!--end::Modals-->
                </div>
                <!--end::Card toolbar-->
            </div>
            <!--end::Card header-->

            <!--begin::Card body-->
            <div class="card-body pt-0">
                <div class="table-responsive" style="overflow-x: hidden;">
                    <!--begin::Table-->
                    <table class="table align-middle fs-6 gy-5" id="kt_table_tenants">
                        <!--begin::Table head-->
                        <thead>
                            <tr class="text-start text-muted fw-bolder fs-7 text-uppercase gs-0">
                                <th>Tenant Profile &amp; Tenancies</th>
                            </tr>
                        </thead>
                        <!--end::Table head-->

                        <!--begin::Table body-->
                        <tbody class="text-gray-600 fw-bold" id="tenants-table-body">
                            <!-- Table rows will be dynamically populated by DataTables -->
                        </tbody>
                        <!--end::Table body-->
                    </table>
                    <!--end::Table-->
                </div>
            </div>
            <!--end::Card body-->

            <!--begin::Modal - Record Payment-->
            @include('pages.business.tenant.include.payment_modal')
            <!--end::Modal - Record Payment-->

            <!--begin::Modal - Give Notice-->
            @include('pages.business.tenant.include.notice_modal')
            <!--end::Modal - Give Notice-->

            <!--begin::Modal - End Tenancy-->
            @include('pages.business.tenant.include.tenancy_modal')
            <!--end::Modal - End Tenancy-->
        </div>
        <!--end::Tenants Card-->

    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

@endsection
