<!--begin::Modal - Upload Tenants Data-->
<div class="modal fade" tabindex="-1" id="kt_modal_bulk_upload" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulk_upload_modal_title">Tenant Bulk Upload</h5>
                <!--begin::Close-->
                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-2x">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"></rect>
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"></rect>
                        </svg>
                    </span>
                </div>
                <!--end::Close-->
            </div>

            <form id="kt_modal_bulk_upload_form" class="form" enctype="multipart/form-data">
                <div class="modal-body p-6">
                    <!--begin::Stepper-->
                    <div class="stepper stepper-pills stepper-row" id="kt_stepper_bulk_upload">
                        <!--begin::Aside-->
                        <div class="d-flex justify-content-center flex-row-auto w-100">
                            <!--begin::Nav-->
                            <div class="stepper-nav w-100 d-flex flex-row justify-content-between ps-lg-10 pe-lg-20">
                                <!--begin::Step 1-->
                                <div class="stepper-item current" data-kt-stepper-element="nav">
                                    <div class="stepper-line w-40px"></div>
                                    <div class="stepper-icon w-40px h-40px">
                                        <i class="stepper-check fas fa-check"></i>
                                        <span class="stepper-number">1</span>
                                    </div>
                                    <div class="stepper-label">
                                        <h3 class="stepper-title">File Selection</h3>
                                        <div class="stepper-desc">Choose file and type</div>
                                    </div>
                                </div>
                                <!--end::Step 1-->

                                <!--begin::Step 2-->
                                <div class="stepper-item me-9" data-kt-stepper-element="nav">
                                    <div class="stepper-line w-40px"></div>
                                    <div class="stepper-icon w-40px h-40px">
                                        <i class="stepper-check fas fa-check"></i>
                                        <span class="stepper-number">2</span>
                                    </div>
                                    <div class="stepper-label">
                                        <h3 class="stepper-title">Column Mapping</h3>
                                        <div class="stepper-desc">Map CSV headers</div>
                                    </div>
                                </div>
                                <!--end::Step 2-->

                            </div>
                            <!--end::Nav-->
                        </div>
                        <!--end::Aside-->

                        <!--begin::Content-->
                        <div class="flex-row-fluid py-lg-5 px-lg-15">
                            <!--begin::Step 1 - File Selection-->
                            <div class="current" data-kt-stepper-element="content">
                                <div class="w-100">

                                    <!--begin::Upload Type-->
                                    <div class="fv-row mb-4">
                                        <label class="required fs-6 fw-bold form-label mb-2">Upload Type</label>
                                        <select name="upload_type" id="upload_type" class="form-select form-select-solid" data-control="select2" data-placeholder="Select upload type" data-allow-clear="true" required>
                                            <option></option>
                                            <option value="tenants">Tenant Information</option>
                                            <option value="payments">Tenant Payments</option>
                                            <option value="notices">Tenant Notices</option>
                                        </select>
                                    </div>
                                    <!--end::Upload Type-->

                                    <!--begin::Upload Type Instructions-->
                                    <div id="upload_type_instructions"></div>
                                    <!--end::Upload Type Instructions-->

                                    <!--begin::File Upload + Template row-->
                                    <div class="d-flex gap-4 align-items-start">

                                        <!--begin::Dropzone-->
                                        <div class="flex-grow-1">
                                            <label class="required fs-6 fw-bold form-label mb-2">Data File</label>
                                            <div id="bulk-upload-dropzone"
                                                 class="border border-dashed border-primary rounded p-4 text-center cursor-pointer"
                                                 style="transition: background .15s;">
                                                <i class="fas fa-cloud-upload-alt fs-2x text-primary mb-1"></i>
                                                <p class="text-muted mb-0 fs-7">Drag &amp; drop, or <span class="text-primary fw-bold">browse</span></p>
                                                <p class="text-muted fs-8 mb-0">CSV or XLSX — max 10 MB</p>
                                                <input type="file" name="data_file" id="data_file" accept=".csv,.xlsx" style="display:none" required />
                                            </div>
                                            <div id="bulk-upload-file-info" class="mt-2"></div>
                                        </div>
                                        <!--end::Dropzone-->

                                        <!--begin::Template Download-->
                                        <div class="flex-shrink-0" style="min-width: 150px;">
                                            <label class="fs-6 fw-bold form-label mb-2">Sample Template</label>
                                            <div id="template_download_section">
                                                <div class="border border-dashed rounded p-3 text-center text-muted fs-8">
                                                    <i class="fas fa-file-csv fs-2x text-muted d-block mb-1"></i>
                                                    Select a type first
                                                </div>
                                            </div>
                                        </div>
                                        <!--end::Template Download-->

                                    </div>
                                    <!--end::File Upload + Template row-->

                                </div>
                            </div>
                            <!--end::Step 1-->

                            <!--begin::Step 2 - Column Mapping-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100 scroll h-400px">
                                    <div class="text-left mb-5 mt-5">
                                        <h3 class="fw-bolder text-dark mb-3">Map CSV Columns</h3>
                                        <p class="text-muted fs-6">Map your CSV columns to the required fields</p>
                                    </div>

                                    <!--begin::File Preview-->
                                    <div id="file_preview_section" class="mb-5">
                                        <!-- File preview will be inserted here -->
                                    </div>
                                    <!--end::File Preview-->

                                    <!--begin::Column Mapping-->
                                    <div id="column_mapping_section">
                                        <!-- Column mapping interface will be inserted here -->
                                    </div>
                                    <!--end::Column Mapping-->

                                    <!--begin::Validation Summary-->
                                    <div id="mapping_validation_summary" class="mt-10">
                                        <!-- Validation summary will be inserted here -->
                                    </div>
                                    <!--end::Validation Summary-->
                                </div>
                            </div>
                            <!--end::Step 2-->
                        </div>
                        <!--end::Content-->
                    </div>
                    <!--end::Stepper-->
                </div>

                <!--begin::Modal footer-->
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-3" data-kt-stepper-action="previous">
                        <span class="svg-icon svg-icon-4 me-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="11" width="13" height="2" rx="1" fill="black" />
                                <path d="M8.56569 11.4343L12.75 7.25C13.1642 6.83579 13.1642 6.16421 12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75L5.70711 11.2929C5.31658 11.6834 5.31658 12.3166 5.70711 12.7071L11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25C13.1642 17.8358 13.1642 17.1642 12.75 16.75L8.56569 12.5657C8.25327 12.2533 8.25327 11.7467 8.56569 11.4343Z" fill="black" />
                            </svg>
                        </span>Back
                    </button>

                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>

                    <button type="button" class="btn btn-primary" data-kt-stepper-action="next">
                        Continue
                        <span class="svg-icon svg-icon-4 ms-1 me-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="18" y="13" width="13" height="2" rx="1" transform="rotate(-180 18 13)" fill="black" />
                                <path d="M15.4343 12.5657L11.25 16.75C10.8358 17.1642 10.8358 17.8358 11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25L18.2929 12.7071C18.6834 12.3166 18.6834 11.6834 18.2929 11.2929L12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75C10.8358 6.16421 10.8358 6.83579 11.25 7.25L15.4343 11.4343C15.7467 11.7467 15.7467 12.2533 15.4343 12.5657Z" fill="black" />
                            </svg>
                        </span>
                    </button>

                    <button type="submit" class="btn btn-success" id="tenant_bulk_upload_button" data-kt-stepper-action="submit" style="display: none;">
                        <span class="indicator-label">Upload Data</span>
                        <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
                <!--end::Modal footer-->
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Upload Tenants Data-->

<!--begin::Modal - Export Data-->
<div class="modal fade" id="kt_modal_export_data" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-1000px">
        <div class="modal-content">
            <!--begin::Modal header-->
            <div class="modal-header">
                <h2 class="fw-bolder">Export Data</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                        </svg>
                    </span>
                </div>
            </div>
            <!--end::Modal header-->

            <form id="kt_modal_export_form" class="form">
                <!--begin::Modal body-->
                <div class="modal-body scroll h-400px mx-5 mx-xl-15 my-7">
                    <!--begin::Export Options-->
                    <div class="row mb-10">
                        <div class="col-12">
                            <label class="fs-6 fw-bold form-label mb-4 required">What do you want to export?</label>
                            <div class="row g-5">
                                
                                <!--begin::Tenant Data-->
                                <div class="col-md-4">
                                    <div class="card card-bordered h-100">
                                        <div class="card-body d-flex flex-column">
                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                <input class="form-check-input" type="checkbox" id="export_tenants" name="export_types[]" value="tenants" checked />
                                                <label class="form-check-label fw-bold fs-5" for="export_tenants">
                                                    <i class="fas fa-users text-primary me-2"></i>
                                                    Tenant Data
                                                </label>
                                            </div>
                                            <div class="text-muted fs-7 mb-3">Tenant profiles and tenancy management information</div>
                                            
                                            <!--begin::Tenant Sub-options-->
                                            <div class="tenant-suboptions ms-4" style="display: block;">
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="tenant_profiles" name="tenant_options[]" value="tenant_profiles" checked />
                                                    <label class="form-check-label fs-7 fw-bold" for="tenant_profiles">Tenant Profiles</label>
                                                    <div class="text-muted fs-8">Personal/business information from tenant profiles</div>
                                                </div>
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="tenancy_details" name="tenant_options[]" value="tenancy_details" checked />
                                                    <label class="form-check-label fs-7 fw-bold" for="tenancy_details">Tenancy Details</label>
                                                    <div class="text-muted fs-8">Property, agreement, and management information</div>
                                                </div>
                                                
                                                <div class="separator separator-dashed my-3"></div>
                                                
                                                <!-- Sub-options for Tenant Profiles -->
                                                <div class="tenant-profile-suboptions ms-2" id="tenant_profile_suboptions">
                                                    <div class="text-muted fs-8 fw-bold mb-2">Profile Options:</div>
                                                    <div class="form-check form-check-sm mb-3">
                                                        <input class="form-check-input" type="checkbox" id="include_personal_details" name="tenant_profile_options[]" value="personal_details" checked />
                                                        <label class="form-check-label fs-8" for="include_personal_details">Personal/Business Details</label>
                                                    </div>
                                                    <div class="form-check form-check-sm mb-3">
                                                        <input class="form-check-input" type="checkbox" id="include_contact_details" name="tenant_profile_options[]" value="contact_details" checked />
                                                        <label class="form-check-label fs-8" for="include_contact_details">Contact Information</label>
                                                    </div>
                                                    <div class="form-check form-check-sm mb-3">
                                                        <input class="form-check-input" type="checkbox" id="include_emergency_contacts" name="tenant_profile_options[]" value="emergency_contacts" />
                                                        <label class="form-check-label fs-8" for="include_emergency_contacts">Emergency Contacts</label>
                                                    </div>
                                                </div>
                                                
                                                <!-- Sub-options for Tenancy Details -->
                                                <div class="tenancy-details-suboptions ms-2" id="tenancy_details_suboptions">
                                                    <div class="text-muted fs-8 fw-bold mb-2">Tenancy Options:</div>
                                                    <div class="form-check form-check-sm mb-3">
                                                        <input class="form-check-input" type="checkbox" id="include_property_details" name="tenancy_detail_options[]" value="property_details" checked />
                                                        <label class="form-check-label fs-8" for="include_property_details">Property Information</label>
                                                    </div>
                                                    <div class="form-check form-check-sm mb-3">
                                                        <input class="form-check-input" type="checkbox" id="include_agreement_details" name="tenancy_detail_options[]" value="agreement_details" checked />
                                                        <label class="form-check-label fs-8" for="include_agreement_details">Agreement Terms</label>
                                                    </div>
                                                    <div class="form-check form-check-sm mb-3">
                                                        <input class="form-check-input" type="checkbox" id="include_financial_details" name="tenancy_detail_options[]" value="financial_details" checked />
                                                        <label class="form-check-label fs-8" for="include_financial_details">Financial Details</label>
                                                    </div>
                                                    <div class="form-check form-check-sm mb-3">
                                                        <input class="form-check-input" type="checkbox" id="include_occupancy_details" name="tenancy_detail_options[]" value="occupancy_details" />
                                                        <label class="form-check-label fs-8" for="include_occupancy_details">Occupancy Information</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <!--end::Tenant Sub-options-->
                                        </div>
                                    </div>
                                </div>
                                <!--end::Tenant Data-->

                                <!--begin::Payment Data-->
                                <div class="col-md-4">
                                    <div class="card card-bordered h-100">
                                        <div class="card-body d-flex flex-column">
                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                <input class="form-check-input" type="checkbox" id="export_payments" name="export_types[]" value="payments" />
                                                <label class="form-check-label fw-bold fs-5" for="export_payments">
                                                    <i class="fas fa-money-bill-wave text-success me-2"></i>
                                                    Financial Data
                                                </label>
                                            </div>
                                            <div class="text-muted fs-7 mb-3">Billing records, invoices, and payment history with comprehensive financial tracking</div>
                                            
                                            <!--begin::Payment Sub-options-->
                                            <div class="payment-suboptions ms-4" style="display: none;">
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="include_billing_records" name="payment_options[]" value="billing_records" checked />
                                                    <label class="form-check-label fs-7" for="include_billing_records">Billing Records</label>
                                                </div>
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="include_invoice_details" name="payment_options[]" value="invoice_details" checked />
                                                    <label class="form-check-label fs-7" for="include_invoice_details">Invoice Details</label>
                                                </div>
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="include_payment_records" name="payment_options[]" value="payment_records" checked />
                                                    <label class="form-check-label fs-7" for="include_payment_records">Payment Records</label>
                                                </div>
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="include_service_charges" name="payment_options[]" value="service_charges" />
                                                    <label class="form-check-label fs-7" for="include_service_charges">Service Charge Breakdown</label>
                                                </div>
                                                <div class="form-check form-check-sm">
                                                    <input class="form-check-input" type="checkbox" id="include_verification_details" name="payment_options[]" value="verification_details" />
                                                    <label class="form-check-label fs-7" for="include_verification_details">Verification Details</label>
                                                </div>
                                            </div>
                                            <!--end::Payment Sub-options-->
                                        </div>
                                    </div>
                                </div>
                                <!--end::Payment Data-->

                                <!--begin::Notice Data-->
                                <div class="col-md-4">
                                    <div class="card card-bordered h-100">
                                        <div class="card-body d-flex flex-column">
                                            <div class="form-check form-check-custom form-check-solid mb-3">
                                                <input class="form-check-input" type="checkbox" id="export_notices" name="export_types[]" value="notices" />
                                                <label class="form-check-label fw-bold fs-5" for="export_notices">
                                                    <i class="fas fa-bell text-warning me-2"></i>
                                                    Notice Data
                                                </label>
                                            </div>
                                            <div class="text-muted fs-7 mb-3">Notice history, delivery status, and communication records</div>
                                            
                                            <!--begin::Notice Sub-options-->
                                            <div class="notice-suboptions ms-4" style="display: none;">
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="include_notice_content" name="notice_options[]" value="notice_content" checked />
                                                    <label class="form-check-label fs-7" for="include_notice_content">Notice Content</label>
                                                </div>
                                                <div class="form-check form-check-sm mb-2">
                                                    <input class="form-check-input" type="checkbox" id="include_delivery_status" name="notice_options[]" value="delivery_status" />
                                                    <label class="form-check-label fs-7" for="include_delivery_status">Delivery Status</label>
                                                </div>
                                                <div class="form-check form-check-sm">
                                                    <input class="form-check-input" type="checkbox" id="include_follow_up_details" name="notice_options[]" value="follow_up_details" />
                                                    <label class="form-check-label fs-7" for="include_follow_up_details">Follow-up Details</label>
                                                </div>
                                            </div>
                                            <!--end::Notice Sub-options-->
                                        </div>
                                    </div>
                                </div>
                                <!--end::Notice Data-->
                            </div>
                        </div>
                    </div>
                    <!--end::Export Options-->

                    <!--begin::Filters-->
                    <div class="separator separator-dashed mb-8"></div>
                    <div class="row mb-8">
                        <div class="col-12">
                            <h4 class="fw-bold mb-5">Filter Options</h4>
                            
                            <div class="row">
                                <!--begin::Date Range-->
                                <div class="col-md-6 mb-7">
                                    <label class="fs-6 fw-bold form-label mb-2">Date Range</label>
                                    <select name="date_range" id="date_range" class="form-select form-select-solid" data-control="select2" data-placeholder="Select date range">
                                        <option value="">All Time</option>
                                        <option value="today">Today</option>
                                        <option value="yesterday">Yesterday</option>
                                        <option value="last_7_days">Last 7 Days</option>
                                        <option value="last_30_days">Last 30 Days</option>
                                        <option value="this_month">This Month</option>
                                        <option value="last_month">Last Month</option>
                                        <option value="this_quarter">This Quarter</option>
                                        <option value="this_year">This Year</option>
                                        <option value="last_year">Last Year</option>
                                        <option value="custom">Custom Range</option>
                                    </select>
                                </div>
                                <!--end::Date Range-->

                                <!--begin::Property Type-->
                                <div class="col-md-6 mb-7">
                                    <label class="fs-6 fw-bold form-label mb-2">Property Type</label>
                                    <select name="property_type" class="form-select form-select-solid" data-control="select2" data-placeholder="All Property Types">
                                        <option value="">All Property Types</option>
                                        @if(isset($property_types))
                                            @foreach($property_types as $property_type)
                                                <option value="{{$property_type->property_type}}">{{$property_type->property_type}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                <!--end::Property Type-->
                            </div>

                            <!--begin::Custom Date Range-->
                            <div class="row custom-date-range" style="display: none;">
                                <div class="col-md-6 mb-7">
                                    <label class="fs-6 fw-bold form-label mb-2">From Date</label>
                                    <input type="date" name="from_date" class="form-control form-control-solid" />
                                </div>
                                <div class="col-md-6 mb-7">
                                    <label class="fs-6 fw-bold form-label mb-2">To Date</label>
                                    <input type="date" name="to_date" class="form-control form-control-solid" />
                                </div>
                            </div>
                            <!--end::Custom Date Range-->

                            <!--begin::Tenant Specific Filters (shown when tenant export is selected)-->
                            <div class="tenant_specific_options" style="display: none;">
                                <div class="row">
                                    <!--begin::Tenant Status-->
                                    <div class="col-md-6 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Tenant Status</label>
                                        <select name="tenant_status" class="form-select form-select-solid" data-control="select2" data-placeholder="All Statuses">
                                            <option value="">All Statuses</option>
                                            <option value="Active">Active</option>
                                            <option value="Inactive">Inactive</option>
                                            <option value="Pending">Pending</option>
                                            <option value="Terminated">Terminated</option>
                                            <option value="Overdue">Overdue</option>
                                        </select>
                                    </div>
                                    <!--end::Tenant Status-->

                                    <!--begin::Tenant Type-->
                                    <div class="col-md-6 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Tenant Type</label>
                                        <select name="tenant_type" class="form-select form-select-solid" data-control="select2" data-placeholder="All Types">
                                            <option value="">All Types</option>
                                            <option value="Individual">Individual</option>
                                            <option value="Business">Business</option>
                                        </select>
                                    </div>
                                    <!--end::Tenant Type-->
                                </div>
                            </div>
                            <!--end::Tenant Specific Filters-->

                            <!--begin::Specific Tenant Selection (shown when tenant export is selected)-->
                            <div class="specific_tenant_selection" style="display: none;">
                                <div class="row">
                                    <div class="col-md-12 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Select Specific Tenant</label>
                                        <select name="specific_tenant[]" id="specific_tenant" class="form-select form-select-solid" data-control="select2" data-placeholder="Search and select tenant" multiple data-allow-clear="true">
                                            <!-- Options will be dynamically loaded via AJAX -->
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!--end::Specific Tenant Selection-->

                            <!--begin::Payment Filters (shown when payment export is selected)-->
                            <div class="payment-filters" style="display: none;">
                                <div class="row">
                                    <div class="col-md-4 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Payment Amount Range</label>
                                        <div class="row">
                                            <div class="col-6">
                                                <input type="number" name="min_amount" class="form-control form-control-solid" placeholder="Min Amount" min="0" step="0.01" />
                                            </div>
                                            <div class="col-6">
                                                <input type="number" name="max_amount" class="form-control form-control-solid" placeholder="Max Amount" min="0" step="0.01" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Payment Method</label>
                                        <select name="payment_method" class="form-select form-select-solid" data-control="select2" data-placeholder="All Methods" multiple data-allow-clear="true">
                                            <option value="">All Methods</option>
                                            <option value="cash">Cash</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="mobile_money">Mobile Money</option>
                                            <option value="check">Check</option>
                                            <option value="card">Card</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <div class="col-md-4 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Payment Status</label>
                                        <select name="payment_status" class="form-select form-select-solid" data-control="select2" data-placeholder="All Statuses" multiple data-allow-clear="true">
                                            <option value="">All Statuses</option>
                                            <option value="paid">Paid</option>
                                            <option value="partial">Partial</option>
                                            <option value="pending">Pending</option>
                                            <option value="failed">Failed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!--end::Payment Filters-->

                            <!--begin::Notice Filters (shown when notice export is selected)-->
                            <div class="notice-filters" style="display: none;">
                                <div class="row">
                                    <div class="col-md-6 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Notice Type</label>
                                        <select name="notice_type" class="form-select form-select-solid" data-control="select2" data-placeholder="All Types" multiple data-allow-clear="true">
                                            <option value="">All Types</option>
                                            <option value="lease_termination">Lease Termination</option>
                                            <option value="rent_increase">Rent Increase</option>
                                            <option value="lease_violation">Lease Violation</option>
                                            <option value="property_inspection">Property Inspection</option>
                                            <option value="maintenance_notice">Maintenance Notice</option>
                                            <option value="eviction_notice">Eviction Notice</option>
                                            <option value="lease_renewal">Lease Renewal</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-7">
                                        <label class="fs-6 fw-bold form-label mb-2">Delivery Status</label>
                                        <select name="notice_delivery_status" class="form-select form-select-solid" data-control="select2" data-placeholder="All Statuses" multiple data-allow-clear="true">
                                            <option value="">All Statuses</option>
                                            <option value="pending">Pending</option>
                                            <option value="sent">Sent</option>
                                            <option value="delivered">Delivered</option>
                                            <option value="failed">Failed</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <!--end::Notice Filters-->

                            <!--begin::Complex Filter-->
                            <div class="row">
                                <div class="col-md-6 mb-7">
                                    <label class="fs-6 fw-bold form-label mb-2">Complex</label>
                                    <select name="complex" class="form-select form-select-solid" data-control="select2" data-placeholder="All Complexes" multiple data-allow-clear="true">
                                        @if(isset($complexes))
                                            @foreach($complexes as $complex)
                                                <option value="{{$complex->id}}">{{$complex->title}}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="col-md-6 mb-7">
                                    <label class="fs-6 fw-bold form-label mb-2">Monthly Payment Range</label>
                                    <div class="row">
                                        <div class="col-6">
                                            <input type="number" name="min_monthly_payment" class="form-control form-control-solid" placeholder="Min Payment" min="0" step="0.01" />
                                        </div>
                                        <div class="col-6">
                                            <input type="number" name="max_monthly_payment" class="form-control form-control-solid" placeholder="Max Payment" min="0" step="0.01" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Complex Filter-->
                        </div>
                    </div>
                    <!--end::Filters-->

                    <!--begin::Export Format-->
                    <div class="separator separator-dashed mb-8"></div>
                    <div class="row mb-8">
                        <div class="col-12">
                            <h4 class="fw-bold mb-5">Export Format</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-check-custom form-check-solid mb-3">
                                        <input class="form-check-input" type="radio" name="export_format" id="format_excel" value="excel" checked />
                                        <label class="form-check-label fw-bold" for="format_excel">
                                            <i class="fas fa-file-excel text-success me-2"></i>
                                            Excel (.xlsx)
                                        </label>
                                        <div class="text-muted fs-7">Best for data analysis and manipulation</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-check-custom form-check-solid mb-3">
                                        <input class="form-check-input" type="radio" name="export_format" id="format_csv" value="csv" />
                                        <label class="form-check-label fw-bold" for="format_csv">
                                            <i class="fas fa-file-csv text-primary me-2"></i>
                                            CSV (.csv)
                                        </label>
                                        <div class="text-muted fs-7">Universal format for data import/export</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Export Format-->

                    <!--begin::Preview-->
                    <div class="row">
                        <div class="col-12">
                            <div class="alert alert-info d-flex align-items-center p-5">
                                <span class="svg-icon svg-icon-2hx svg-icon-info me-4">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                        <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="black"/>
                                        <rect x="11" y="17" width="2" height="2" rx="1" fill="black"/>
                                        <rect x="13" y="13" width="2" height="4" rx="1" fill="black"/>
                                    </svg>
                                </span>
                                <div class="d-flex flex-column">
                                    <h4 class="mb-1 text-dark">Export Preview</h4>
                                    <span id="export_preview_text">Tenant data will be exported with basic information</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Preview-->
                </div>
                <!--end::Modal body-->

                <!--begin::Modal footer-->
                <div class="modal-footer">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="export_submit_btn">
                        <span class="indicator-label">
                            <i class="fas fa-download me-2"></i>
                            Export Data
                        </span>
                        <span class="indicator-progress">
                            Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
                <!--end::Modal footer-->
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Export Data-->