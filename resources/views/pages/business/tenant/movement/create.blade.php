@extends('pages.business.layouts.app')

@section('content')

<!--begin::Header-->
<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Initiate Unit Transfer</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">Home</li>
                <li class="breadcrumb-item text-muted"><a href="/tenant_management" class="text-muted">Tenant Management</a></li>
                <li class="breadcrumb-item text-muted"><a href="/tenant_management/movement" class="text-muted">Unit Transfers</a></li>
                <li class="breadcrumb-item text-dark">Initiate Transfer</li>
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
<!--end::Header-->

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">

        <!--begin::Stepper-->
        <div class="card">
            <div class="card-header d-flex flex-row justify-content-end">
                <div class="card-toolbar">
                    <a href="/tenant_management/movement" class="btn btn-light-primary"><i class="fas fa-arrow-left"></i> Go Back</a>
                </div>
            </div>
            <div class="card-body">

                {{-- Inject server-side page data for JS --}}
                <script>
                    window.currentPageData = @json($pageData);
                </script>

                <!--begin::Stepper Header-->
                <div class="stepper stepper-pills stepper-column d-flex flex-column flex-xl-row flex-row-fluid" id="movement_stepper">
                    <div class="d-flex justify-content-center justify-content-xl-start flex-row-auto w-100 w-xl-300px">
                        <div class="stepper-nav ps-lg-10">

                            <div class="stepper-item current" data-kt-stepper-element="nav" id="step_nav_1">
                                <div class="stepper-wrapper d-flex flex-row">
                                    <div class="stepper-icon w-40px h-40px">
                                        <i class="stepper-check fas fa-check"></i>
                                        <span class="stepper-number">1</span>
                                    </div>
                                    <div class="stepper-label">
                                        <h3 class="stepper-title">Transfer Details</h3>
                                        <div class="stepper-desc fw-bold">Select tenant and target unit</div>
                                    </div>
                                </div>
                                <div class="stepper-line h-40px"></div>
                            </div>

                            <div class="stepper-item" data-kt-stepper-element="nav" id="step_nav_2">
                                <div class="stepper-wrapper d-flex flex-row">
                                    <div class="stepper-icon w-40px h-40px">
                                        <i class="stepper-check fas fa-check"></i>
                                        <span class="stepper-number">2</span>
                                    </div>
                                    <div class="stepper-label">
                                        <h3 class="stepper-title">New Lease Terms</h3>
                                        <div class="stepper-desc fw-bold">Set terms for the new unit</div>
                                    </div>
                                </div>
                                <div class="stepper-line h-40px"></div>
                            </div>

                            <div class="stepper-item" data-kt-stepper-element="nav" id="step_nav_3">
                                <div class="stepper-wrapper d-flex flex-row">
                                    <div class="stepper-icon w-40px h-40px">
                                        <i class="stepper-check fas fa-check"></i>
                                        <span class="stepper-number">3</span>
                                    </div>
                                    <div class="stepper-label">
                                        <h3 class="stepper-title">Deposit & Parking</h3>
                                        <div class="stepper-desc fw-bold">Adjust deposit and parking</div>
                                    </div>
                                </div>
                                <div class="stepper-line h-40px"></div>
                            </div>

                            <div class="stepper-item" data-kt-stepper-element="nav" id="step_nav_4">
                                <div class="stepper-wrapper d-flex flex-row">
                                    <div class="stepper-icon w-40px h-40px">
                                        <i class="stepper-check fas fa-check"></i>
                                        <span class="stepper-number">4</span>
                                    </div>
                                    <div class="stepper-label">
                                        <h3 class="stepper-title">Preview & Confirm</h3>
                                        <div class="stepper-desc fw-bold">Review and submit</div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!--begin::Stepper Content-->
                    <div class="flex-row-fluid py-lg-5 px-lg-15">

                        {{-- Hidden state inputs --}}
                        <input type="hidden" id="movement_tenancy_id"   value="{{ $pageData['tenancy_id'] ?? '' }}">
                        <input type="hidden" id="movement_complex_id"   value="{{ $pageData['complex_id'] ?? '' }}">
                        <input type="hidden" id="movement_unit_type"    value="{{ $pageData['unit_type'] ?? '' }}">
                        <input type="hidden" id="movement_currency"     value="{{ $pageData['currency'] ?? 'USD' }}">

                        <!--===== STEP 1: Transfer Details =====-->
                        <div id="movement_step_1">

                            {{-- Tenant select (shown only when no tenancy pre-selected) --}}
                            <div id="tenant_select_wrapper" class="mb-8" style="{{ !empty($pageData['tenancy_id']) ? 'display:none' : '' }}">
                                <label class="form-label required fw-bold fs-6">Select Tenant</label>
                                <select id="movement_tenant_select" class="form-select form-select-solid" data-placeholder="Search tenants…"></select>
                                <div class="text-danger mt-1 d-none" id="tenant_select_error">Please select a tenant to continue.</div>
                            </div>

                            {{-- Current tenancy info (shown when pre-selected) --}}
                            <div id="current_tenancy_info" class="mb-8 {{ empty($pageData['tenancy_id']) ? 'd-none' : '' }}">
                                <div class="alert alert-light-primary d-flex align-items-center p-5 mb-0">
                                    <i class="fas fa-user-circle fs-2tx text-primary me-4"></i>
                                    <div>
                                        <h4 class="mb-1 fw-bolder" id="tenancy_info_name">{{ $pageData['tenant_name'] ?? '' }}</h4>
                                        <div class="text-muted fs-7">Current unit: <strong id="tenancy_info_unit">{{ $pageData['unit_number'] ?? '' }}</strong></div>
                                        <div class="text-muted fs-7">Monthly rent: <strong id="tenancy_info_rent">{{ $pageData['currency'] ?? '' }} {{ number_format($pageData['monthly_payment'] ?? 0, 2) }}</strong></div>
                                    </div>
                                </div>
                            </div>

                            {{-- Arrears banner (injected by JS) --}}
                            <div id="arrears_banner"></div>

                            <div class="row g-6 mb-6">
                                <div class="col-md-6">
                                    <label class="form-label required fw-bold fs-6">Target Unit</label>
                                    <select id="movement_new_unit" class="form-select form-select-solid" data-placeholder="Select vacant unit…">
                                        <option value="">— select after choosing tenant —</option>
                                    </select>
                                    <div class="text-danger mt-1 d-none" id="unit_select_error">Please select a target unit.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required fw-bold fs-6">Move Type</label>
                                    <select id="movement_move_type" class="form-select form-select-solid">
                                        <option value="end_of_month">End of Month (standard)</option>
                                        <option value="mid_month">Mid Month (exceptional)</option>
                                    </select>
                                </div>
                            </div>

                            {{-- ===== RESIDENTIAL UNIT DETAILS (shown when unit_type = residential) ===== --}}
                            <div id="residential_unit_extra" class="d-none mt-4 mb-6">
                                <div class="separator separator-dashed mb-6"></div>
                                <h5 class="fw-bolder mb-5 text-primary"><i class="fas fa-home me-2"></i>Residential Unit Details</h5>

                                {{-- Read-only auto-filled stats --}}
                                <div class="row g-4 mb-6" id="residential_unit_info_row">
                                    <div class="col-6 col-md-3">
                                        <label class="form-label fw-bold fs-7 text-muted">Bedrooms</label>
                                        <div class="fw-bolder fs-5" id="res_info_bedrooms">—</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label fw-bold fs-7 text-muted">Bathrooms</label>
                                        <div class="fw-bolder fs-5" id="res_info_bathrooms">—</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label fw-bold fs-7 text-muted">Floor Area</label>
                                        <div class="fw-bolder fs-5" id="res_info_sqarea">—</div>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <label class="form-label fw-bold fs-7 text-muted">Base Monthly Rate</label>
                                        <div class="fw-bolder fs-5" id="res_info_rent">—</div>
                                    </div>
                                </div>

                                <div class="row g-6 mb-6">
                                    <div class="col-md-6">
                                        <label class="form-label required fw-bold fs-6">Furnishing</label>
                                        <select id="movement_furnishing" class="form-select form-select-solid">
                                            <option value="">— select —</option>
                                            <option value="Unfurnished">Unfurnished</option>
                                            <option value="Furnished">Furnished</option>
                                            <option value="Partially Furnished">Partially Furnished</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required fw-bold fs-6">Number of Occupants</label>
                                        <input type="number" id="movement_occupants" class="form-control form-control-solid" min="1" value="1">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold fs-6">Pets</label>
                                    <div class="d-flex gap-6 mb-3">
                                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                                            <input class="form-check-input" type="radio" name="movement_has_pets" value="0" checked> No pets
                                        </label>
                                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                                            <input class="form-check-input" type="radio" name="movement_has_pets" value="1"> Has pets
                                        </label>
                                    </div>
                                    <div id="movement_pet_details_wrapper" class="d-none">
                                        <textarea id="movement_pet_details" class="form-control form-control-solid" rows="2"
                                            placeholder="Describe the pets (type, breed, number…)"></textarea>
                                    </div>
                                </div>
                            </div>
                            {{-- ===== /RESIDENTIAL UNIT DETAILS ===== --}}

                            {{-- ===== COMMERCIAL UNIT DETAILS (shown when unit_type = commercial) ===== --}}
                            <div id="commercial_unit_extra" class="d-none mt-4 mb-6">
                                <div class="separator separator-dashed mb-6"></div>
                                <h5 class="fw-bolder mb-5 text-success"><i class="fas fa-building me-2"></i>Commercial Unit Details</h5>

                                {{-- Availability info panel (populated by JS) --}}
                                <div id="commercial_availability_info" class="alert alert-light-info d-flex align-items-start p-5 mb-6 d-none">
                                    <i class="fas fa-info-circle fs-2x text-info me-4 mt-1"></i>
                                    <div class="row w-100 g-3">
                                        <div class="col-6 col-md-3">
                                            <div class="text-muted fs-7">Total Unit Size</div>
                                            <div class="fw-bolder" id="comm_total_sqft">—</div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="text-muted fs-7">Min Required Area</div>
                                            <div class="fw-bolder" id="comm_min_sqft">—</div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="text-muted fs-7">Max Subdivisions</div>
                                            <div class="fw-bolder" id="comm_max_subdivisions">—</div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="text-muted fs-7">Base Monthly Rate</div>
                                            <div class="fw-bolder" id="comm_base_rent">—</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-6 mb-6">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold fs-6">Specific Unit / Sub-unit Number</label>
                                        <input type="text" id="movement_specific_unit_number" class="form-control form-control-solid" placeholder="e.g. A-101">
                                        <div class="form-text">Subdivision identifier within the commercial unit</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required fw-bold fs-6">Occupied Area (sq ft)</label>
                                        <input type="number" id="movement_occupied_sqft" class="form-control form-control-solid" min="1" step="0.01" placeholder="Enter sq ft needed">
                                        <div class="text-muted fs-7 mt-1">Min: <span id="comm_min_label" class="fw-bold text-warning">—</span> sq ft</div>
                                    </div>
                                </div>

                                <div class="row g-6 mb-6">
                                    <div class="col-md-6">
                                        <label class="form-label required fw-bold fs-6">Rate per sq ft</label>
                                        <input type="number" id="movement_rate_per_sqft" class="form-control form-control-solid" min="0" step="0.01" placeholder="Enter rate">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label required fw-bold fs-6">Service Charge per sq ft</label>
                                        <input type="number" id="movement_service_charge_per_sqft" class="form-control form-control-solid" min="0" step="0.01" placeholder="Enter service charge rate">
                                    </div>
                                </div>

                                {{-- Live rent calculation banner --}}
                                <div id="commercial_rent_calc_banner" class="alert alert-primary d-none mb-2">
                                    <i class="fas fa-calculator me-2"></i>
                                    <strong>Calculated Monthly Rent:</strong>
                                    <span id="calc_sqft">0</span> sq ft
                                    × (<span id="calc_rate">0</span> + <span id="calc_sc">0</span>) =
                                    <strong class="text-primary fs-5 ms-1" id="calc_total_rent">0.00</strong>
                                    <span id="rent_currency_calc_label" class="ms-1 text-muted"></span>
                                </div>
                                <div class="text-danger mt-1 d-none" id="commercial_fields_error">Please fill in all required commercial fields.</div>
                            </div>
                            {{-- ===== /COMMERCIAL UNIT DETAILS ===== --}}

                            <div class="row g-6 mb-6">
                                <div class="col-md-6">
                                    <label class="form-label required fw-bold fs-6">Transfer Date</label>
                                    <input type="date" id="movement_transfer_date" class="form-control form-control-solid">
                                    <div class="text-danger mt-1 d-none" id="date_error">Please select a transfer date.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Notes</label>
                                    <input type="text" id="movement_notes" class="form-control form-control-solid" placeholder="Optional notes…">
                                </div>
                            </div>

                            {{-- Arrears acknowledgement checkbox (shown by JS when warning) --}}
                            <div id="arrears_ack_wrapper" class="d-none mb-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="arrears_acknowledged">
                                    <label class="form-check-label fw-semibold" for="arrears_acknowledged">
                                        I acknowledge the outstanding arrears and confirm the transfer may proceed.
                                    </label>
                                </div>
                                <div class="text-danger mt-1 d-none" id="arrears_ack_error">You must acknowledge the arrears to continue.</div>
                            </div>

                        </div>
                        <!--===== /STEP 1 =====-->

                        <!--===== STEP 2: New Lease Terms =====-->
                        <div id="movement_step_2" class="d-none">

                            <div class="row g-6 mb-6">
                                <div class="col-md-6">
                                    <label class="form-label required fw-bold fs-6">New Monthly Rent</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="rent_currency_label">USD</span>
                                        <input type="number" id="movement_new_rent" class="form-control form-control-solid" step="0.01" min="0">
                                    </div>
                                    <div class="form-text text-info d-none" id="rent_auto_note">
                                        <i class="fas fa-calculator me-1"></i>Auto-calculated from sq ft × rate
                                    </div>
                                    <div class="text-danger mt-1 d-none" id="rent_error">Please enter the new rent amount.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required fw-bold fs-6">Lease Start Date</label>
                                    <input type="date" id="lease_start_date" class="form-control form-control-solid">
                                </div>
                            </div>

                            <div class="row g-6 mb-6">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Lease End Date</label>
                                    <input type="date" id="lease_end_date" class="form-control form-control-solid">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Lease Duration (months)</label>
                                    <input type="number" id="lease_duration_months" class="form-control form-control-solid" min="1">
                                </div>
                            </div>

                            <div class="row g-6 mb-6">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Payment Frequency</label>
                                    <select id="lease_payment_frequency" class="form-select form-select-solid">
                                        <option value="monthly">Monthly</option>
                                        <option value="quarterly">Quarterly</option>
                                        <option value="biannual">Bi-Annual</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Payment Day of Month</label>
                                    <input type="number" id="lease_payment_day" class="form-control form-control-solid" min="1" max="31" value="1">
                                </div>
                            </div>

                            <div class="row g-6 mb-6">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Lease Type</label>
                                    <select id="lease_type" class="form-select form-select-solid">
                                        <option value="fixed">Fixed Term</option>
                                        <option value="month_to_month">Month-to-Month</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Late Fee</label>
                                    <input type="number" id="lease_late_fee" class="form-control form-control-solid" step="0.01" min="0" value="0">
                                </div>
                            </div>

                            <div class="mb-6">
                                <label class="form-label fw-bold fs-6">Special Terms</label>
                                <textarea id="lease_special_terms" class="form-control form-control-solid" rows="3" placeholder="Any special terms or conditions…"></textarea>
                            </div>

                        </div>
                        <!--===== /STEP 2 =====-->

                        <!--===== STEP 3: Deposit & Parking =====-->
                        <div id="movement_step_3" class="d-none">

                            <div class="row g-6 mb-8">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold fs-6">Previous Deposit</label>
                                    <div class="input-group">
                                        <span class="input-group-text" id="deposit_currency_label">USD</span>
                                        <input type="number" id="movement_old_deposit" class="form-control form-control-solid" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required fw-bold fs-6">New Deposit</label>
                                    <div class="input-group">
                                        <span class="input-group-text">USD</span>
                                        <input type="number" id="movement_new_deposit" class="form-control form-control-solid" step="0.01" min="0">
                                    </div>
                                </div>
                            </div>

                            <!--begin::Deposit Adjustment Banner-->
                            <div id="deposit_adjustment_banner" class="alert mb-8 d-none">
                                <span id="deposit_adjustment_text"></span>
                            </div>
                            <!--end::Deposit Adjustment Banner-->

                            <!--begin::Parking Section (shown by JS only if tenant has parking)-->
                            <div id="parking_section" class="d-none">
                                <div class="separator separator-dashed mb-6"></div>
                                <h4 class="fw-bolder mb-5">Parking Slot</h4>
                                <div class="alert alert-light-info mb-6" id="current_parking_info">
                                    <i class="fas fa-car me-2 text-info"></i>
                                    Current slot: <strong id="current_slot_label">—</strong>
                                </div>
                                <div class="mb-6">
                                    <label class="form-label required fw-bold fs-6">Parking Action</label>
                                    <div class="d-flex gap-4 flex-wrap">
                                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                                            <input class="form-check-input" type="radio" name="parking_action" id="parking_keep" value="keep" checked>
                                            <span>Keep same slot</span>
                                        </label>
                                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                                            <input class="form-check-input" type="radio" name="parking_action" id="parking_release" value="release">
                                            <span>Release slot</span>
                                        </label>
                                        <label class="d-flex align-items-center gap-2 cursor-pointer">
                                            <input class="form-check-input" type="radio" name="parking_action" id="parking_replace" value="replace">
                                            <span>Assign new slot</span>
                                        </label>
                                    </div>
                                </div>
                                <div id="new_parking_slot_wrapper" class="d-none mb-6">
                                    <label class="form-label required fw-bold fs-6">Select New Parking Slot</label>
                                    <select id="movement_new_parking_slot" class="form-select form-select-solid">
                                        <option value="">Loading slots…</option>
                                    </select>
                                    <div class="text-danger mt-1 d-none" id="parking_slot_error">Please select a parking slot.</div>
                                </div>
                            </div>
                            <!--end::Parking Section-->

                        </div>
                        <!--===== /STEP 3 =====-->

                        <!--===== STEP 4: Preview & Confirm =====-->
                        <div id="movement_step_4" class="d-none">

                            <div id="preview_loading" class="text-center py-10">
                                <div class="spinner-border text-primary"></div>
                                <p class="mt-3 text-muted">Building preview…</p>
                            </div>

                            <div id="preview_content" class="d-none">

                                <!--Transfer Summary-->
                                <div class="card bg-light-primary border-0 mb-6">
                                    <div class="card-body py-4 px-6">
                                        <h4 class="fw-bolder text-primary mb-4">Transfer Summary</h4>
                                        <div class="row g-4">
                                            <div class="col-sm-6">
                                                <div class="text-muted fs-7 mb-1">Tenant</div>
                                                <div class="fw-bolder" id="preview_tenant_name">—</div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="text-muted fs-7 mb-1">Move Type</div>
                                                <div class="fw-bolder" id="preview_move_type">—</div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="text-muted fs-7 mb-1">From Unit</div>
                                                <div class="fw-bolder text-danger" id="preview_from_unit">—</div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="text-muted fs-7 mb-1">To Unit</div>
                                                <div class="fw-bolder text-success" id="preview_to_unit">—</div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="text-muted fs-7 mb-1">Transfer Date</div>
                                                <div class="fw-bolder" id="preview_transfer_date">—</div>
                                            </div>
                                            <div class="col-sm-6">
                                                <div class="text-muted fs-7 mb-1">Rent Change</div>
                                                <div class="fw-bolder" id="preview_rent_change">—</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!--Financial Summary-->
                                <div class="card border mb-6" id="preview_financial_card">
                                    <div class="card-body py-4 px-6">
                                        <h4 class="fw-bolder mb-4">Financial Summary</h4>
                                        <div id="preview_financial_rows"></div>
                                    </div>
                                </div>

                                <!--Parking Summary-->
                                <div class="card border mb-6 d-none" id="preview_parking_card">
                                    <div class="card-body py-4 px-6">
                                        <h4 class="fw-bolder mb-3">Parking</h4>
                                        <div id="preview_parking_text" class="text-muted fs-6"></div>
                                    </div>
                                </div>

                            </div>

                            <div id="preview_error" class="alert alert-danger d-none">Failed to load preview. Please go back and check your inputs.</div>

                        </div>
                        <!--===== /STEP 4 =====-->

                        <!--begin::Nav Buttons-->
                        <div class="d-flex flex-stack pt-10 mt-8 border-top">
                            <button type="button" class="btn btn-light btn-lg" id="movement_prev_btn" style="display:none!important;">
                                <i class="fas fa-chevron-left me-2"></i>Previous
                            </button>
                            <div class="ms-auto d-flex gap-3">
                                <a href="/tenant_management/movement" class="btn btn-light btn-lg">Cancel</a>
                                <button type="button" class="btn btn-primary btn-lg" id="movement_next_btn">
                                    Continue<i class="fas fa-chevron-right ms-2"></i>
                                </button>
                                <button type="button" class="btn btn-success btn-lg d-none" id="movement_submit_btn">
                                    <i class="fas fa-check me-2"></i>Submit Transfer Request
                                </button>
                            </div>
                        </div>
                        <!--end::Nav Buttons-->

                    </div>
                    <!--end::Stepper Content-->

                </div>
                <!--end::Stepper-->

            </div>
        </div>
        <!--end::Card-->

    </div>
</div>
<!--end::Content-->

@endsection
