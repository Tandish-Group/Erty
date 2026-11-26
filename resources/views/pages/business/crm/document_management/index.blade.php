@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <!--begin::Container-->
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <!--begin::Page title-->
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <!--begin::Heading-->
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Document Center</h1>
            <!--end::Heading-->
            <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    @businessCan('crm_management', 'view')<a href="/customer_relationship" class="text-muted text-hover-primary">CRM</a>@else<span class="text-muted">CRM</span>@endbusinessCan
                </li>
                <li class="breadcrumb-item text-dark">Document Center</li>
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
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <!--begin::Container-->
    <div class="container-xxl" id="kt_content_container">

        <!--begin::Stats Row-->
        <div class="row g-5 g-xl-8 mb-8">
            <!--begin::Total Documents-->
            <div class="col-xl-3 col-md-6">
                <a href="#" class="card bg-body hoverable card-xl-stretch mb-xl-8 rounded" onclick="filterBySource('all')">
                    <div class="card-body">
                        <i class="ki-duotone ki-folder fs-3x text-primary mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-3" id="stat-total">--</div>
                        <div class="fw-bold text-gray-400">Total Documents</div>
                    </div>
                </a>
            </div>
            <!--end::Total Documents-->

            <!--begin::Tenant Onboarding-->
            <div class="col-xl-3 col-md-6">
                <a href="#" class="card bg-body hoverable card-xl-stretch mb-xl-8 rounded" onclick="filterBySource('tenant_onboarding')">
                    <div class="card-body">
                        <i class="ki-duotone ki-home-2 fs-3x text-success mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-3" id="stat-tenant">--</div>
                        <div class="fw-bold text-gray-400">Tenant Documents</div>
                    </div>
                </a>
            </div>
            <!--end::Tenant Onboarding-->

            <!--begin::Lease & Legal Documents-->
            <div class="col-xl-3 col-md-6">
                <a href="#" class="card bg-body hoverable card-xl-stretch mb-xl-8 rounded" onclick="filterBySource('tenant_legal')">
                    <div class="card-body">
                        <i class="ki-duotone ki-document fs-3x text-warning mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-3" id="stat-legal">--</div>
                        <div class="fw-bold text-gray-400">Lease &amp; Legal Documents</div>
                    </div>
                </a>
            </div>
            <!--end::Lease & Legal Documents-->

            <!--begin::Agent Documents-->
            <div class="col-xl-3 col-md-6">
                <a href="#" class="card bg-body hoverable card-xl-stretch mb-xl-8 rounded" onclick="filterBySource('agent')">
                    <div class="card-body">
                        <i class="ki-duotone ki-people fs-3x text-info mb-3">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                            <span class="path4"></span>
                            <span class="path5"></span>
                        </i>
                        <div class="text-gray-900 fw-bolder fs-2 mb-2 mt-3" id="stat-agent">--</div>
                        <div class="fw-bold text-gray-400">Agent Documents</div>
                    </div>
                </a>
            </div>
            <!--end::Agent Documents-->
        </div>
        <!--end::Stats Row-->

        <!--begin::Documents Table Card-->
        <div class="card">
            <!--begin::Card Header-->
            <div class="card-header d-flex flex-row justify-content-between border-0 pt-6">
                <div class="card-title">
                    <h3 class="card-title align-items-start flex-column">
                        <span class="card-label fw-bold fs-3 mb-1">All Documents</span>
                        <span class="text-muted fw-semibold fs-7">Centralized view of all documents across your system</span>
                    </h3>
                </div>
                <div class="card-toolbar">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <!--begin::Source Filter-->
                        <select class="form-select form-select-sm w-175px" id="sourceFilter">
                            <option value="all">All Sources</option>
                            <option value="tenant_onboarding">Tenant Onboarding</option>
                            <option value="tenant_legal">Tenant Legal</option>
                            <option value="agent">Agents</option>
                        </select>
                        <!--end::Source Filter-->

                        <!--begin::Category Filter-->
                        <select class="form-select form-select-sm w-175px" id="categoryFilter">
                            <option value="all">All Categories</option>
                            <option value="PDF Document">PDF Document</option>
                            <option value="Image">Image</option>
                            <option value="Word Document">Word Document</option>
                            <option value="Proof of Income">Proof of Income</option>
                            <option value="ID Document">ID Document</option>
                            <option value="Rental Reference">Rental Reference</option>
                            <option value="Employer Letter">Employer Letter</option>
                            <option value="Business Certificate">Business Certificate</option>
                            <option value="Business License">Business License</option>
                            <option value="Resume / CV">Resume / CV</option>
                            <option value="Certification">Certification</option>
                            <option value="Profile Photo">Profile Photo</option>
                            <option value="Legal">Legal</option>
                        </select>
                        <!--end::Category Filter-->

                        <!--begin::Date Range-->
                        <input type="date" class="form-control form-control-sm w-150px" id="dateFrom" placeholder="From Date">
                        <input type="date" class="form-control form-control-sm w-150px" id="dateTo" placeholder="To Date">
                        <!--end::Date Range-->

                        <!--begin::Reset-->
                        <button type="button" class="btn btn-sm btn-light-danger" id="resetFilters" title="Reset Filters">
                            <i class="ki-duotone ki-cross fs-2">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            Reset
                        </button>
                        <!--end::Reset-->
                    </div>
                </div>
            </div>
            <!--end::Card Header-->

            <!--begin::Card Body-->
            <div class="card-body py-4">
                <div class="table-responsive">
                    <table class="table align-middle table-row-dashed fs-6 gy-5" id="documentsTable">
                        <thead>
                            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                <th class="min-w-200px">Document</th>
                                <th class="min-w-125px">Source</th>
                                <th class="min-w-125px">Category</th>
                                <th class="min-w-150px">Related To</th>
                                <th class="min-w-100px">Size</th>
                                <th class="min-w-125px">Date</th>
                                <th class="text-end min-w-100px">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600 fw-semibold">
                            <!-- DataTable will populate this -->
                        </tbody>
                    </table>
                </div>
            </div>
            <!--end::Card Body-->
        </div>
        <!--end::Documents Table Card-->

    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

<!--begin::Document Preview Modal-->
<div class="modal fade" id="documentPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fw-bold" id="previewModalTitle">Document Preview</h3>
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-duotone ki-cross fs-1">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                </div>
            </div>
            <div class="modal-body">
                <div id="previewContent" class="text-center">
                    <!-- Preview content will be injected here -->
                </div>
                <div class="separator my-5"></div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="d-flex flex-column mb-3">
                            <span class="text-muted fs-7">Source</span>
                            <span class="fw-bold fs-6" id="previewSource">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-column mb-3">
                            <span class="text-muted fs-7">Category</span>
                            <span class="fw-bold fs-6" id="previewCategory">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-column mb-3">
                            <span class="text-muted fs-7">Related To</span>
                            <span class="fw-bold fs-6" id="previewRelatedTo">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-column mb-3">
                            <span class="text-muted fs-7">File Size</span>
                            <span class="fw-bold fs-6" id="previewSize">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-column mb-3">
                            <span class="text-muted fs-7">Upload Date</span>
                            <span class="fw-bold fs-6" id="previewDate">-</span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-column mb-3">
                            <span class="text-muted fs-7">File Type</span>
                            <span class="fw-bold fs-6" id="previewType">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <a href="#" class="btn btn-primary" id="previewDownloadBtn" target="_blank">
                    <i class="ki-duotone ki-cloud-download fs-2">
                        <span class="path1"></span>
                        <span class="path2"></span>
                    </i>
                    Download
                </a>
            </div>
        </div>
    </div>
</div>
<!--end::Document Preview Modal-->


@endsection
