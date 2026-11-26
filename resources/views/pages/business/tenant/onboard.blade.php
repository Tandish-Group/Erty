@extends('pages.business.layouts.app')

@section('content')

@include('pages.business.tenant.partials.header', ['title' => 'Onboard tenant'])

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid tenant-workspace tenant-onboarding" id="kt_content">
	<!--begin::Container-->
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div><a href="/tenant_management" class="tenant-back-link">← Tenant dashboard</a><span class="tenant-eyebrow">New tenancy</span><h1>Set up a tenancy</h1><p>Choose the tenant and property, agree the terms, then review before creating the tenancy.</p></div>
        </header>
        @if($conversionLead)
            @php
                $lp = $conversionLead->profile;
                $leadIsBusiness = ($lp->lead_type ?? 'Individual') === 'Business';
                $leadName = $leadIsBusiness ? ($lp->company_name ?? 'Business lead') : trim(($lp->first_name ?? '').' '.($lp->last_name ?? ''));
            @endphp
            <div class="alert alert-primary d-flex flex-column gap-1" role="status" data-conversion-lead
                data-lead='{{ json_encode([
                    'id' => $conversionLead->id, 'type' => $leadIsBusiness ? 'Business' : 'Individual', 'label' => $leadName.($lp?->email ? ' ('.$lp->email.')' : ''),
                    'tenant_name' => trim(($lp->first_name ?? '').' '.($lp->last_name ?? '')), 'tenant_email' => $lp->email ?? null, 'tenant_phone' => $lp->phone ?? null, 'occupation' => $lp->occupation ?? null,
                    'business_name' => $lp->company_name ?? null, 'business_registration' => $lp->registration_number ?? null, 'business_contact_person' => $lp->contact_person ?? null,
                    'business_email' => $lp->business_email ?? null, 'business_phone' => $lp->business_phone ?? null,
                    'agent' => $conversionLead->assignedAgent ? ['id' => $conversionLead->assignedAgent->id, 'name' => $conversionLead->assignedAgent->full_name] : null,
                ], JSON_HEX_APOS | JSON_HEX_QUOT) }}'>
                <strong>Converting lead: {{ $leadName }}</strong>
                <span class="fs-7">Their details are filled in from the lead.@if($conversionLead->complex) They were interested in <strong>{{ $conversionLead->complex->title }}</strong>.@endif @if($conversionLead->assignedAgent) The tenancy is credited to their agent, {{ $conversionLead->assignedAgent->full_name }}, unless you choose another.@endif When you save, the lead is marked converted and its agent gets the finalization tasks.</span>
            </div>
        @endif
        <section class="tenant-onboard-success tenant-panel" data-onboard-success hidden tabindex="-1" aria-live="polite"></section>
        <div class="tenant-onboard-shell" data-onboard-shell>
            <div class="tenant-onboard-progress"><span data-onboard-progress-label role="status">Step 1 of 6 · Tenant</span><span>Created after your final confirmation</span><progress data-onboard-progress max="6" value="1" aria-label="Onboarding progress"></progress></div>
            <div class="card-body">
                <!--begin::Stepper-->
                <div class="stepper stepper-pills stepper-column d-flex flex-column flex-xl-row flex-row-fluid tenant-onboard-stepper" id="kt_modal_add_tenant_stepper">
                    <!--begin::Aside-->
                    <div class="tenant-step-sidebar">
                        <!--begin::Nav-->
                        <div class="stepper-nav">
                            <!--begin::Step 1-->
                            <div class="stepper-item current" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">1</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Tenant</h3><div class="stepper-desc">New or existing profile</div>
                                </div>
                            </div>
                            <!--end::Step 1-->
                            <!--begin::Step 1.2-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">2</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Property & unit</h3><div class="stepper-desc">Available space</div>
                                </div>
                            </div>
                            <!--end::Step 1.2-->
                            <!--begin::Step 2-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">3</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Agreement</h3><div class="stepper-desc">Dates and responsibility</div>
                                </div>
                            </div>
                            <!--end::Step 2-->
                            <!--begin::Step 3-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">4</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Billing</h3><div class="stepper-desc">Rent, deposit and timing</div>
                                </div>
                            </div>
                            <!--end::Step 3-->
                            <!--begin::Step 4-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">5</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Documents & parking</h3><div class="stepper-desc">Optional supporting details</div>
                                </div>
                            </div>
                            <!--end::Step 4-->
                            <!--begin::Step 5-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">6</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Review</h3><div class="stepper-desc">Confirm and create</div>
                                </div>
                            </div>
                            <!--end::Step 5-->
                        </div>
                        <!--end::Nav-->
                    </div>
                    <!--end::Aside-->

                    <!--begin::Content-->
                    <div class="tenant-step-content flex-row-fluid">
                        <!--begin::Form-->
                        <form class="form" novalidate="novalidate" id="kt_modal_add_tenant_form" action="/tenant_management" method="post" enctype="multipart/form-data">
                            @if($conversionLead)<input type="hidden" name="lead_id" value="{{ $conversionLead->id }}">@endif
                            @csrf
                            <div class="tenant-feedback is-error" data-onboard-error role="alert" tabindex="-1" hidden></div>
                            <div data-onboard-steps>
                            <!--begin::Step 1-->
                            <div class="current" data-kt-stepper-element="content">
                                <div class="w-100">
                                    <!--begin::Input group-->
                                    <div class="fv-row tenant-type-selection">
                                        <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                            <span class="required">Tenant Type</span>
                                            <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select whether this is an individual or business tenant"></i>
                                        </label>
                                        <p class="text-muted mb-6">Choose who will hold the tenancy, then create a profile or select an existing tenant.</p>
                                        
                                        <!--begin:Options-->
                                        <div class="fv-row row justify-content-center">
                                            <div class="col-6">
                                                <!--begin:Option-->
                                                <label class="d-flex flex-column align-items-center cursor-pointer btn btn-outline btn-outline-dashed btn-outline-default p-7 h-100">
                                                    <span class="d-flex flex-column align-items-center text-center">
                                                        <span class="symbol symbol-60px mb-4">
                                                            <span class="symbol-label bg-light-primary">
                                                                <i class="fas fa-user text-primary fs-2x"></i>
                                                            </span>
                                                        </span>
                                                        <span class="d-flex flex-column align-items-center">
                                                            <span class="fw-bolder fs-5 mb-1">Individual</span>
                                                            <span class="fs-7 text-muted">Personal tenant</span>
                                                        </span>
                                                    </span>
                                                    <span class="form-check form-check-custom form-check-solid mt-4">
                                                        <input class="form-check-input" type="radio" name="tenant_type" value="Individual" />
                                                    </span>
                                                </label>
                                                <!--end::Option-->
                                            </div>
                                    
                                            <div class="col-6">
                                                <!--begin:Option-->
                                                <label class="d-flex flex-column align-items-center cursor-pointer btn btn-outline btn-outline-dashed btn-outline-default p-7 h-100">
                                                    <span class="d-flex flex-column align-items-center text-center">
                                                        <span class="symbol symbol-60px mb-4">
                                                            <span class="symbol-label bg-light-success">
                                                                <i class="fas fa-building text-success fs-2x"></i>
                                                            </span>
                                                        </span>
                                                        <span class="d-flex flex-column align-items-center">
                                                            <span class="fw-bolder fs-5 mb-1">Business</span>
                                                            <span class="fs-7 text-muted">Corporate tenant</span>
                                                        </span>
                                                    </span>
                                                    <span class="form-check form-check-custom form-check-solid mt-4">
                                                        <input class="form-check-input" type="radio" name="tenant_type" value="Business" />
                                                    </span>
                                                </label>
                                                <!--end::Option-->
                                            </div>
                                        </div>
                                        <!--end:Options-->
                                    </div>
                                    <!--end::Input group-->
                                    <!--begin::Tenant Selection Options-->
                                    <div class="tenant-information-section" style="display: none;">
                                    <div class="text-center mb-10">
                                        <h3 class="fw-bolder text-dark mb-3">Tenant Information</h3>
                                        <p class="text-muted fs-6">Choose to add a new tenant or select an existing one</p>
                                    </div>
                            
                                    <!--begin::Tenant Options-->
                                    <div class="fv-row mb-10">
                                        <div class="row justify-content-center">
                                            <div class="col-6">
                                                <!--begin:Option-->
                                                <label class="d-flex flex-column align-items-center cursor-pointer btn btn-outline btn-outline-dashed btn-outline-default p-7 h-100">
                                                    <span class="d-flex flex-column align-items-center text-center">
                                                        <span class="symbol symbol-60px mb-4">
                                                            <span class="symbol-label bg-light-success">
                                                                <i class="fas fa-user-plus text-success fs-2x"></i>
                                                            </span>
                                                        </span>
                                                        <span class="d-flex flex-column align-items-center">
                                                            <span class="fw-bolder fs-5 mb-1">Add New Tenant</span>
                                                            <span class="fs-7 text-muted">Create new tenant profile</span>
                                                        </span>
                                                    </span>
                                                    <span class="form-check form-check-custom form-check-solid mt-4">
                                                        <input class="form-check-input" type="radio" name="tenant_option" value="new" checked />
                                                    </span>
                                                </label>
                                                <!--end::Option-->
                                            </div>
                                    
                                            <div class="col-6">
                                                <!--begin:Option-->
                                                <label class="d-flex flex-column align-items-center cursor-pointer btn btn-outline btn-outline-dashed btn-outline-default p-7 h-100">
                                                    <span class="d-flex flex-column align-items-center text-center">
                                                        <span class="symbol symbol-60px mb-4">
                                                            <span class="symbol-label bg-light-primary">
                                                                <i class="fas fa-users text-primary fs-2x"></i>
                                                            </span>
                                                        </span>
                                                        <span class="d-flex flex-column align-items-center">
                                                            <span class="fw-bolder fs-5 mb-1">Use Existing Tenant</span>
                                                            <span class="fs-7 text-muted">Select from previous tenants</span>
                                                        </span>
                                                    </span>
                                                    <span class="form-check form-check-custom form-check-solid mt-4">
                                                        <input class="form-check-input" type="radio" name="tenant_option" value="existing" />
                                                    </span>
                                                </label>
                                                <!--end::Option-->
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Tenant Options-->
                            
                                    <!--begin::Existing Tenant Selection-->
                                    <div class="existing-tenant-selection" style="display: none;">
                                        <div class="separator separator-dashed mb-8"></div>
                                        <div class="text-center mb-8">
                                            <h4 class="fw-bolder text-dark mb-2">Select Existing Tenant</h4>
                                            <p class="text-muted fs-6">Choose from your previous <span class="selected-tenant-type-text">tenants</span> (including inactive ones)</p>
                                        </div>
                                        
                                        <!--begin::Search and Filter-->
                                        <div class="row mb-6">
                                            <div class="col-md-10">
                                                <div class="fv-row">
                                                    <label class="fs-6 fw-bold form-label mb-2">Search Tenants</label>
                                                    <div class="position-relative">
                                                        <span class="svg-icon svg-icon-1 position-absolute ms-6 mt-3">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                                <rect opacity="0.5" x="17.0365" y="15.1223" width="8.15546" height="2" rx="1" transform="rotate(45 17.0365 15.1223)" fill="black"/>
                                                                <path d="M11 19C6.55556 19 3 15.4444 3 11C3 6.55556 6.55556 3 11 3C15.4444 3 19 6.55556 19 11C19 15.4444 15.4444 19 11 19ZM11 5C7.53333 5 5 7.53333 5 11C5 14.4667 7.53333 17 11 17C14.4667 17 17 14.4667 17 11C17 7.53333 14.4667 5 11 5Z" fill="black"/>
                                                            </svg>
                                                        </span>
                                                        <input type="text" id="existing_tenant_search" class="form-control form-control-solid ps-15" placeholder="Search by name, email, phone...">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="button" class="btn btn-icon w-100 btn-light-primary" onclick="loadExistingTenants()" aria-label="Refresh tenant list">
                                                    <span class="svg-icon svg-icon-1">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                            <path d="M14.5 20.7259C14.6 21.2259 14.2 21.826 13.7 21.926C13.2 22.026 12.6 22.0259 12.1 22.0259C9.5 22.0259 6.9 21.0259 5 19.1259C1.4 15.5259 1.09998 9.72592 4.29998 5.82592L5.70001 7.22595C3.30001 10.3259 3.59999 14.8259 6.39999 17.7259C8.19999 19.5259 10.8 20.426 13.4 19.926C13.9 19.826 14.4 20.2259 14.5 20.7259ZM18.4 16.8259L19.8 18.2259C22.9 14.3259 22.7 8.52593 19 4.92593C16.7 2.62593 13.5 1.62594 10.3 2.12594C9.79998 2.22594 9.4 2.72595 9.5 3.22595C9.6 3.72595 10.1 4.12594 10.6 4.02594C13.1 3.62594 15.7 4.42595 17.6 6.22595C20.5 9.22595 20.7 13.7259 18.4 16.8259Z" fill="black"/>
                                                            <path opacity="0.3" d="M2 3.62592H7C7.6 3.62592 8 4.02592 8 4.62592V9.62589L2 3.62592ZM16 14.4259V19.4259C16 20.0259 16.4 20.4259 17 20.4259H22L16 14.4259Z" fill="black"/>
                                                        </svg>
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                        <!--end::Search and Filter-->
                            
                                        <!--begin::Tenant Selection List-->
                                        <div class="fv-row mb-10">
                                            <label class="required fs-6 fw-bold form-label mb-2">Select Tenant</label>
                                            <div class="existing-tenants-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #e4e6ef; border-radius: 0.475rem; padding: 1rem;">
                                                <!-- Loading state -->
                                                <div class="text-center py-10" id="tenants-loading">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                    <div class="mt-3">Loading previous tenants...</div>
                                                </div>
                            
                                                <!-- Tenants will be loaded here -->
                                                <div id="existing-tenants-container" style="display: none;">
                                                    <!-- Dynamic content -->
                                                </div>
                            
                                                <!-- No results -->
                                                <div class="text-center py-10" id="no-tenants-found" style="display: none;">
                                                    <i class="fas fa-search text-muted fs-2x mb-3"></i>
                                                    <div class="text-muted">No <span class="selected-tenant-type-text">tenants</span> found matching your criteria</div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--end::Tenant Selection List-->
                            
                                        <!--begin::Selected Tenant Preview-->
                                        <div class="selected-tenant-preview" style="display: none;">
                                            <div class="alert alert-info d-flex align-items-center p-6">
                                                <span class="svg-icon svg-icon-2hx svg-icon-info me-4">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="black"/>
                                                        <rect x="11" y="17" width="2" height="2" rx="1" fill="black"/>
                                                        <rect x="13" y="13" width="2" height="4" rx="1" fill="black"/>
                                                    </svg>
                                                </span>
                                                <div class="d-flex flex-column flex-grow-1">
                                                    <h5 class="mb-2 text-dark">Selected <span class="selected-tenant-type-text">Tenant</span></h5>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="text-sm">
                                                                <strong>Name:</strong> <span id="preview-name"></span><br>
                                                                <strong>Type:</strong> <span id="preview-type"></span><br>
                                                                <strong>Email:</strong> <span id="preview-email"></span>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="text-sm">
                                                                <strong>Phone:</strong> <span id="preview-phone"></span><br>
                                                                <strong>Status:</strong> <span id="preview-status"></span><br>
                                                                <strong>Last Property:</strong> <span id="preview-last-property"></span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-icon btn-active-color-primary" onclick="clearSelectedTenant()">
                                                    <span class="svg-icon svg-icon-2">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                                                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                                                        </svg>
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                        <!--end::Selected Tenant Preview-->
                                    </div>
                                    <!--end::Existing Tenant Selection-->
                            
                                    <!--begin::New Tenant Fields (Individual)-->
                                    <div class="new-tenant-fields individual-fields" style="display: none;">
                                        <div class="separator separator-dashed mb-8"></div>
                                        <div class="text-center mb-8">
                                            <h4 class="fw-bolder text-dark mb-2">Individual Tenant Details</h4>
                                            <p class="text-muted fs-6">Enter the personal information for the new individual tenant</p>
                                        </div>

                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-12 fv-row">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                                    <span>Select from Leads</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select property type"></i>
                                                </label>
                                                <!--begin::Input group-->
                                                <div class="fv-row mb-10">
                                                    <!--begin::Input-->
                                                    <select name="individual_lead_id" class="form-select form-select-solid" data-control="select2" data-hide-search="false" data-placeholder="Select Individual Lead" id="individual_lead_id" data-allow-clear="true">
                                                        <option></option>
                                                    </select>
                                                    <!--end::Input-->
                                                    <span class="text-muted fs-7">Optional: select a lead to prefill the tenant details.</span>
                                                </div>
                                                <!--end::Input group-->
                                            </div>
                                            <!--end::Input group-->
                                            <div class="separator separator-dashed mb-8"></div>
                                            <!--begin::Input group-->
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span class="required">Full Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter tenant's full name"></i>
                                                </label>
                                                <input type="text" name="tenant_name" class="form-control form-control-lg form-control-solid" placeholder="Enter full name">
                                            </div>
                                            <!--end::Input group-->
                                            <!--begin::Input group-->
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span class="required">Email Address</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter valid email address"></i>
                                                </label>
                                                <input type="email" name="tenant_email" class="form-control form-control-lg form-control-solid" placeholder="Enter email address">
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        <!--begin::Input group-->
                                        <div class="row">
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span class="required">Phone Number</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter phone number"></i>
                                                </label>
                                                <input type="text" name="tenant_phone" class="form-control form-control-lg form-control-solid" placeholder="Enter phone number">
                                            </div>
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="fs-5 fw-bold mb-2 required">ID / Passport Number</label>
                                                <input type="text" name="tenant_id_number" class="form-control form-control-lg form-control-solid" placeholder="Enter ID or passport number">
                                            </div>
                                        </div>
                                        <!--begin::Input group-->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2">Emergency Contact Name</label>
                                                    <input type="text" name="emergency_contact_name" class="form-control form-control-lg form-control-solid" placeholder="Emergency contact name">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2">Emergency Contact Phone</label>
                                                    <input type="text" name="emergency_contact_phone" class="form-control form-control-lg form-control-solid" placeholder="Emergency contact phone number">
                                                </div>
                                            </div>
                                        </div>
                                        <!--end::Input group-->
                                    </div>
                                    <!--end::New Tenant Fields (Individual)-->
                            
                                    <!--begin::New Tenant Fields (Business)-->
                                    <div class="new-tenant-fields business-fields" style="display: none;">
                                        <div class="separator separator-dashed mb-8"></div>
                                        <div class="text-center mb-8">
                                            <h4 class="fw-bolder text-dark mb-2">Business Tenant Details</h4>
                                            <p class="text-muted fs-6">Enter the business information for the new business tenant</p>
                                        </div>
                                        
                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-12 fv-row">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                                    <span>Select from Leads</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select property type"></i>
                                                </label>
                                                <!--begin::Input group-->
                                                <div class="fv-row mb-10">
                                                    <!--begin::Input-->
                                                    <select name="business_lead_id" class="form-select form-select-solid" data-control="select2" data-hide-search="false" data-placeholder="Select Business Lead" id="business_lead_id" data-allow-clear="true">
                                                        <option></option>
                                                    </select>
                                                    <!--end::Input-->
                                                    <span class="text-muted fs-7">Optional: select a lead to prefill the tenant details.</span>
                                                </div>
                                                <!--end::Input group-->
                                            </div>
                                            <!--end::Input group-->
                                            <div class="separator separator-dashed mb-8"></div>
                                            <!--begin::Input group-->
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span class="required">Business Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter registered business name"></i>
                                                </label>
                                                <input type="text" name="business_name" class="form-control form-control-lg form-control-solid" placeholder="Enter business name">
                                            </div>
                                            <!--end::Input group-->
                                            <!--begin::Input group-->
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span class="required">Business Registration</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter business registration number"></i>
                                                </label>
                                                <input type="text" name="business_registration" class="form-control form-control-lg form-control-solid" placeholder="Enter registration number">
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        <!--begin::Input group-->
                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span class="required">Contact Person Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter primary contact person's name"></i>
                                                </label>
                                                <input type="text" name="business_contact_person" class="form-control form-control-lg form-control-solid" placeholder="Enter contact person name">
                                            </div>
                                            <!--end::Input group-->
                                            <!--begin::Input group-->
                                            <div class="col-12 col-md-6 fv-row mb-10">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span class="required">Business Email</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter business email address"></i>
                                                </label>
                                                <input type="email" name="business_email" class="form-control form-control-lg form-control-solid" placeholder="Enter business email">
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        <!--begin::Input group-->
                                        <div class="fv-row mb-10">
                                            <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                <span class="required">Business Phone</span>
                                                <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter business phone number"></i>
                                            </label>
                                            <input type="text" name="business_phone" class="form-control form-control-lg form-control-solid" placeholder="Enter business phone number">
                                        </div>
                                        <!--end::Input group-->
                                    </div>
                                    <!--end::New Tenant Fields (Business)-->
                            
                                    <!-- Hidden field to store selected existing tenant ID -->
                                    <div class="fv-row" style="display: none;">
                                        <input type="hidden" name="existing_tenant_id" id="existing_tenant_id">
                                    </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Step 1.2-->

                            <!--begin::Step 2-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100">
                                    <!--begin::Centered Header Section-->
                                    <div class="text-center mb-10">
                                        <h3 class="fw-bolder text-dark mb-3">Property Information</h3>
                                        <p class="text-muted fs-6">Choose the property and available space for this tenancy.</p>
                                    </div>
                                    <!--end::Centered Header Section-->

                                    <!--begin::Lead Property Preferences Display-->
                                    <div id="lead_property_preferences_display" style="display: none;">
                                        <!-- Property preferences will be dynamically inserted here -->
                                    </div>
                                    <!--end::Lead Property Preferences Display-->
                                    
                                    <!--begin::Initial Selection Row (Centered)-->
                                    <div class="row justify-content-center">
                                        <!--begin::Input group-->
                                        <div class="col-12 col-md-6 fv-row">
                                            <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                                <span class="required">Property type</span>
                                                <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select property type"></i>
                                            </label>
                                            <!--begin::Input group-->
                                            <div class="fv-row mb-10">
                                                <!--begin::Input-->
                                                <select name="listing_type" class="form-select form-select-solid" data-control="select2" data-hide-search="false" data-allow-clear="true"  data-placeholder="Select Property Type" id="listing_type">
                                                    <option></option>
                                                    @if(count($property_types) > 0)
                                                        @foreach($property_types as $property_type)
                                                            <option value="{{$property_type->property_type}}">{{$property_type->property_type}}</option>
                                                        @endforeach
                                                    @endif
                                                </select>
                                                <!--end::Input-->
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        <!--end::Input group-->
                                        
                                        <!--begin::Input group-->
                                        <div class="col-12 col-md-6 fv-row">
                                            <!--begin::Label-->
                                            <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                                <span class="required">Property</span>
                                                <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Your tenant will be assigned to the selected complex's location."></i>
                                            </label>
                                            <!--end::Label-->
                                            <!--begin::Input group-->
                                            <div class="fv-row mb-10">
                                                <!--begin::Input-->
                                                <select class="form-select form-select-solid" 
                                                        data-allow-clear="true" 
                                                        data-control="select2" 
                                                        data-hide-search="false" 
                                                        data-placeholder="Select Property Type First" 
                                                        id="complex" 
                                                        name="complex"
                                                        disabled>
                                                    <option></option>
                                                    <!-- Options will be loaded via AJAX after listing type selection -->
                                                </select>
                                                <div class="form-text" id="complex-filter-help">
                                                    <i class="fas fa-info-circle text-muted me-1"></i>
                                                    <span id="complex-filter-text">Please select a property type first to enable complex selection</span>
                                                </div>
                                                <!--end::Input-->
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        <!--end::Input group-->
                                    </div>
                                    <!--end::Initial Selection Row-->
                                    

                                    
                                    <!--begin::Non-Commercial Fields-->
                                    <div class="non-commercial" style="display: none;">
                                        <!--begin::Section Header-->
                                        <div class="separator separator-dashed mb-8"></div>
                                        <div class="text-center mb-8">
                                            <h4 class="fw-bolder text-dark mb-2">Residential Unit Details</h4>
                                            <p class="text-muted fs-6">Choose an available unit. Its recorded details are used automatically.</p>
                                        </div>
                                        <!--end::Section Header-->
                                        
                                        <!--begin::Residential Unit Selection (for Complex Apartments)-->
                                        <p data-unit-availability role="status" class="tenant-step-help">Select a property to check availability.</p>
                                        <div id="residential_unit_selection" style="display: none;">
                                            <div class="row mb-8">
                                                <div class="col-md-12">
                                                    <div class="fv-row">
                                                        <label class="d-flex align-items-center fs-5 fw-bold mb-2 required">
                                                            <span>Select Residential Unit</span>
                                                            <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select from available units in the complex"></i>
                                                        </label>
                                                        <select name="residential_unit_id" id="residential_unit_select" data-allow-clear="true" class="form-select form-select-solid" data-control="select2" data-placeholder="Select a unit from the complex">
                                                            <option></option>
                                                        </select>
                                                        <div class="form-text">Select an existing unit from the complex</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="separator separator-dashed mb-8"></div>
                                        </div>
                                        <!--end::Residential Unit Selection-->
                                        <dl class="tenant-unit-facts" data-unit-facts hidden></dl>
                                        <input type="hidden" name="unit_number" id="unit_number_input">
                                        <input type="hidden" name="bedrooms" id="bedrooms_input">
                                        <input type="hidden" name="bathrooms" id="bathrooms_input">
                                        <input type="hidden" name="sq_area">
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <!--begin::Input group-->
                                                <div class="fv-row">
                                                    <!--begin::Label-->
                                                    <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                        Furnishing (optional)
                                                    </label>
                                                    <!--end::Label-->
                                                    <!--begin::Input group-->
                                                    <div class="fv-row mb-10">
                                                        <!--begin::Input-->
                                                        <select name="furnishing" class="form-select form-select-solid" data-control="select2" data-hide-search="false" data-placeholder="Select Furnishing" id="furnishing">
                                                            <option></option>
                                                            <option value="Unfurnished">Unfurnished</option>
                                                            <option value="Furnished">Furnished</option>
                                                            <option value="Partially Furnished">Partially Furnished</option>
                                                        </select>
                                                        <!--end::Input-->
                                                    </div>
                                                    <!--end::Input group-->
                                                </div>
                                                <!--end::Input group-->
                                            </div>
                                            <!-- Occupancy Information -->
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2 required">Number of Occupants</label>
                                                    <input type="number" name="number_of_occupants" class="form-control form-control-lg form-control-solid" min="1" placeholder="1" value="1">
                                                    <div class="form-text">Total number of people living in the unit</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2">Will the tenant have pets?</label>
                                                    <div class="d-flex align-items-center">
                                                        <div class="form-check form-check-custom form-check-solid me-5">
                                                            <input class="form-check-input" type="radio" name="has_pets" id="has_pets_yes" value="1">
                                                            <label class="form-check-label" for="has_pets_yes">
                                                                Yes
                                                            </label>
                                                        </div>
                                                        <div class="form-check form-check-custom form-check-solid">
                                                            <input class="form-check-input" type="radio" name="has_pets" id="has_pets_no" value="0" checked>
                                                            <label class="form-check-label" for="has_pets_no">
                                                                No
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="fv-row mb-10" id="pet_details_section" style="display: none;">
                                                    <label class="fs-5 fw-bold mb-2">Pet Details</label>
                                                    <textarea name="pet_details" class="form-control form-control-lg form-control-solid" rows="3" placeholder="Please provide details about the pets (type, breed, number, etc.)"></textarea>
                                                    <div class="form-text">Describe the type, breed, size, and number of pets</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Non-Commercial Fields-->
                                    
                                    <!--begin::Commercial Fields-->
                                    <div class="commercial" style="display: none;">
                                        <!--begin::Section Header-->
                                        <div class="separator separator-dashed mb-8"></div>
                                        <div class="text-center mb-8">
                                            <h4 class="fw-bolder text-dark mb-2">Commercial Unit Details</h4>
                                            <p class="text-muted fs-6">Select and configure the commercial unit details</p>
                                        </div>
                                        <!--end::Section Header-->
                                        
                                        <div class="row justify-content-center">
                                            <div class="col-md-12">
                                                <!--begin::Input group-->
                                                <div class="fv-row mb-10">
                                                    <!--begin::Label-->
                                                    <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                                        <span class="required">Select Commercial Unit</span>
                                                        <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Choose which specific unit within the complex this tenant will occupy"></i>
                                                    </label>
                                                    <!--end::Label-->
                                                    <!--begin::Input-->
                                                    <select class="form-select form-select-solid" data-control="select2" data-placeholder="Select Unit" id="commercial_unit_id" name="commercial_unit_id">
                                                        <option value="">Select Unit</option>
                                                    </select>
                                                    <!--end::Input-->
                                                </div>
                                                <!--end::Input group-->
                                            </div>
                                        </div>
                                        
                                        <!--begin::Unit Availability Info-->
                                        <div class="row" id="unit_availability_info" style="display: none;">
                                            <div class="col-md-12">
                                                <div class="alert alert-info d-flex align-items-center p-6 mb-8">
                                                    <span class="svg-icon svg-icon-2hx svg-icon-info me-4">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                            <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="black"/>
                                                            <rect x="11" y="17" width="2" height="2" rx="1" fill="black"/>
                                                            <rect x="13" y="13" width="2" height="4" rx="1" fill="black"/>
                                                        </svg>
                                                    </span>
                                                    <div class="d-flex flex-column w-100">
                                                        <h5 class="mb-2 text-dark">Unit Availability Status</h5>
                                                        <div class="row text-sm">
                                                            <div class="col-md-3">
                                                                <span class="text-muted">Total Unit Size:</span>
                                                                <br><span id="total_unit_size" class="fw-bold text-dark">0</span> sq ft
                                                            </div>
                                                            <div class="col-md-3">
                                                                <span class="text-muted">Available Space:</span>
                                                                <br><span id="available_sq_area" class="fw-bold text-success">0</span> sq ft
                                                            </div>
                                                            <div class="col-md-3">
                                                                <span class="text-muted">Min Required:</span>
                                                                <br><span id="min_sq_area" class="fw-bold text-warning">0</span> sq ft
                                                            </div>
                                                            <div class="col-md-3">
                                                                <span class="text-muted">Occupancy:</span>
                                                                <br><span id="occupancy_status" class="fw-bold text-primary">0/0</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--end::Unit Availability Info-->
                                    
                                        <!--begin::Area Warnings-->
                                        <div class="row" id="area_warnings" style="display: none;">
                                            <div class="col-md-12">
                                                <!-- Warnings will be dynamically inserted here -->
                                            </div>
                                        </div>
                                        <!--end::Area Warnings-->
                                        
                                        <!--begin::Commercial Unit Details-->
                                        <div class="row" id="commercial_unit_details" style="display: none;">
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2">Specific Unit Number</label>
                                                    <input type="text" 
                                                        name="specific_unit_number" 
                                                        class="form-control form-control-lg form-control-solid" 
                                                        placeholder="e.g., A-101">
                                                    <div class="form-text">Subdivision identifier within the unit</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2 required">Square Area (sq ft)</label>
                                                    <input type="number" 
                                                        name="occupied_sq_area" 
                                                        class="form-control form-control-lg form-control-solid" 
                                                        min="1" 
                                                        placeholder="Enter area needed" 
                                                        step="0.01">
                                                    <div class="form-text">
                                                        Available: <span id="total_sq_area" class="fw-bold text-success">0</span> sq ft
                                                        • Min: <span id="min_required_space" class="fw-bold text-warning">0</span> sq ft
                                                    </div>
                                                    <div id="area_validation_message" class="form-text text-danger" style="display: none;"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2 required">Rate per sq ft</label>
                                                    <input type="number" 
                                                        name="rate_per_sq_ft" 
                                                        class="form-control form-control-lg form-control-solid" 
                                                        min="1" 
                                                        placeholder="Enter rate" 
                                                        step="0.01">
                                                    <div class="form-text">Recommended: <span id="recommended_rate" class="fw-bold text-success">0</span> price/sq ft</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="fv-row mb-10">
                                                    <label class="fs-5 fw-bold mb-2">Service charge per sq ft (optional)</label>
                                                    <input type="number" 
                                                        value="0" name="service_charge_per_sq_ft"
                                                        class="form-control form-control-lg form-control-solid" 
                                                        min="0"
                                                        placeholder="Enter rate" 
                                                        step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!--begin::Commercial Unit Summary-->
                                        <div class="row" id="commercial_summary" style="display: none;">
                                            <div class="col-md-12">
                                                <div class="alert alert-primary d-flex align-items-center p-8 mb-10">
                                                    <span class="svg-icon svg-icon-2hx svg-icon-primary me-4">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                            <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="black"/>
                                                            <rect x="11" y="17" width="2" height="2" rx="1" fill="black"/>
                                                            <rect x="13" y="13" width="2" height="4" rx="1" fill="black"/>
                                                        </svg>
                                                    </span>
                                                    <div class="d-flex flex-column">
                                                        <h4 class="mb-1 text-dark">Monthly charges</h4>
                                                        <span class="fs-5">
                                                            Rent: <span id="calc_sq_area" class="fw-bold text-primary">0</span> sq ft × <span id="calc_rate" class="fw-bold text-success">0</span> /sq ft =
                                                            <span class="fw-bolder text-dark"><span id="calc_rent">0</span>/month</span>
                                                        </span>
                                                        <span class="fs-6 mt-1">
                                                            Service charge: <span id="calc_service_charge" class="fw-bold text-primary">0</span> /sq ft =
                                                            <span class="fw-bold"><span id="calc_sc_total">0</span>/month</span>, invoiced separately
                                                        </span>
                                                        <span class="fs-6 mt-1">Total per month: <span class="fw-bolder text-dark fs-4"><span id="calc_total">0</span></span></span>
                                                        <small class="text-muted mt-2">
                                                            This will use <span id="calc_percentage" class="fw-bold">0</span>% of available space in this unit
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--end::Commercial Unit Summary-->
                                    </div>
                                    <!--end::Commercial Fields-->
                                </div>
                            </div>
                            <!--end::Step 2-->

                            <!--begin::Step 3-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100">
                                    <h2 class="tenant-step-title">Confirm the agreement</h2><p class="tenant-step-help">Set the start date, an optional end date and who manages this tenancy.</p>
                                    <div class="fv-row mb-10">
                                        <label class="d-flex align-items-center fs-5 fw-bold mb-4">
                                            <span class="required">Agreement Type</span>
                                            <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select agreement type"></i>
                                        </label>
                                        <!--begin:Options-->
                                        <div class="fv-row">
                                            <div id="agreement_type_rental_option">
                                                <!--begin:Option-->
                                                <label class="d-flex flex-stack mb-5 cursor-pointer">
                                                    <span class="d-flex align-items-center me-2">
                                                        <span class="symbol symbol-50px me-6">
                                                            <span class="symbol-label bg-light-primary">
                                                                <i class="fas fa-file-contract text-primary fs-2x"></i>
                                                            </span>
                                                        </span>
                                                        <span class="d-flex flex-column">
                                                            <span class="fw-bolder fs-6">Rental</span>
                                                            <span class="fs-7 text-muted">Standard rental agreement</span>
                                                        </span>
                                                    </span>
                                                    <span class="form-check form-check-custom form-check-solid">
                                                        <input class="form-check-input" type="radio" name="agreement_type" value="rental" />
                                                    </span>
                                                </label>
                                                <!--end::Option-->
                                            </div>

                                            <!--begin:Option-->
                                            <label class="d-flex flex-stack cursor-pointer">
                                                <span class="d-flex align-items-center me-2">
                                                    <span class="symbol symbol-50px me-6">
                                                        <span class="symbol-label bg-light-warning">
                                                            <i class="fas fa-handshake text-warning fs-2x"></i>
                                                        </span>
                                                    </span>
                                                    <span class="d-flex flex-column">
                                                        <span class="fw-bolder fs-6">Lease</span>
                                                        <span class="fs-7 text-muted">Lease agreement</span>
                                                    </span>
                                                </span>
                                                <span class="form-check form-check-custom form-check-solid">
                                                    <input class="form-check-input" type="radio" name="agreement_type" value="lease" />
                                                </span>
                                            </label>
                                            <!--end::Option-->
                                        </div>
                                        <!--end:Options-->
                                    </div>
                                    <!--end::Input group-->
                                    <!--begin::Input group-->
                                    <div class="row mb-10">
                                        <div class="col-md-6">
                                            <div class="fv-row">
                                                <label class="required fs-5 fw-bold mb-2">Start Date</label>
                                                <input type="date" name="start_date" class="form-control form-control-lg form-control-solid" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="fv-row">
                                                <label class="fs-5 fw-bold mb-2">End Date</label>
                                                <input type="date" name="end_date" class="form-control form-control-lg form-control-solid"><div class="form-text">Leave blank for an open-ended agreement.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Input group-->

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Assigned Agent (Optional)</label>
                                        <select name="assigned_agent_id" id="assigned_agent" class="form-select form-select-solid" data-control="select2" data-allow-clear="true" data-placeholder="Select an agent" data-ajax-url="{{ route('agents.search') }}">
                                            <option></option>
                                        </select>
                                        <div class="form-text">Choose the agent responsible for this tenancy, or leave it unassigned.</div>
                                    </div>
                                   
                                </div>
                            </div>
                            <div data-kt-stepper-element="content">
                                <div class="w-100">
                                    <h2 class="tenant-step-title">Set up billing</h2>
                                    <p class="tenant-step-help">Enter the monthly rent. Billing multiplies it by the selected payment interval. Deposits and payments are recorded separately after onboarding.</p>
                                    <div class="tenant-billing-preview" data-billing-preview role="status"></div>
                                    <div class="row mb-10">
                                        <div class="col-md-4 mb-7 fv-row">
                                            <!--begin::Label-->
                                            <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                <span class="required">Currency</span>
                                            </label>
                                            <!--end::Label-->
                                            <!--begin::Input group-->
                                            <div class="fv-row mb-5">
                                                <select class="form-select form-select-solid" data-control="select2" data-placeholder="Select an option" name="currency" id="currency">
                                                    <option value=""></option>
                                                    <option value="USD">Dollar (USD)</option>
                                                    <option value="KES">Kenyan Shilling (KES)</option>
                                                    <option value="GBP">Pound (GBP)</option>
                                                    <option value="EUR">Euro (EUR)</option>
                                                    <option value="JPY">Yen (JPY)</option>
                                                    <option value="DHS">Dirham (DHS)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-7">
                                            <div class="fv-row">
                                                <label class="required fs-5 fw-bold mb-2">Monthly rent</label>
                                                <input type="number" name="monthly_payment" class="form-control form-control-lg form-control-solid" min="0" step="0.01" placeholder="0.00" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4 mb-7">
                                            <div class="fv-row">
                                                <label class="fs-5 fw-bold mb-2">Security Deposit</label>
                                                <input type="number" name="security_deposit" class="form-control form-control-lg form-control-solid" min="0" value="0" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="agreement_build_out_section">
                                            <div class="fv-row">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span>Build-out period (days, optional)</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Number of days allocated for building out the property before occupancy."></i>
                                                </label>
                                                <input type="number" name="build_out_period" class="form-control form-control-lg form-control-solid" min="0" step="1" value="0" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="fv-row">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2">
                                                    <span>Grace period (days, optional)</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Rent is billed at the start of each period and is due this many days later. A late fee applies after that."></i>
                                                </label>
                                                <input type="number" name="grace_period" class="form-control form-control-lg form-control-solid" min="0" step="1" value="0" placeholder="0">
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-7" data-rent-paid-up-to>
                                            <div class="fv-row">
                                                <label class="d-flex align-items-center fs-5 fw-bold mb-2" for="rent_paid_up_to">
                                                    <span>Rent paid up to (already living there)</span>
                                                </label>
                                                <input type="date" name="rent_paid_up_to" id="rent_paid_up_to" class="form-control form-control-lg form-control-solid">
                                                <div class="form-text">Only for a tenant who moved in before being added to {{ config('app.name') }}. Rent periods ending on or before this date are recorded as already paid; later periods are invoiced. Leave empty for a new move-in.</div>
                                            </div>
                                        </div>
                                    
                                        <!--begin::Late Fee and Penalty Section-->
                                        <div class="col-md-6 mb-7">
                                            <div class="fv-row">
                                                <label class="fs-5 fw-bold mb-2 required">Payment Interval</label>
                                                <select class="form-select form-select-solid" data-control="select2" data-hide-search="false" data-placeholder="Select payment interval" name="payment_interval" id="payment_interval" data-allow-clear="true">
                                                    <option></option>
                                                    <option value="Monthly">Monthly</option>
                                                    <option value="Quarterly">Quarterly</option>
                                                    <option value="Annually">Annually</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-7 d-none" id="tenancy_quarterly_payment_type_section">
                                            <div class="fv-row">
                                                <label class="fs-5 fw-bold mb-2 required">Quarterly payment timing</label>
                                                <select class="form-select form-select-solid" data-control="select2" data-hide-search="false" data-placeholder="Select payment type" name="tenancy_quarterly_payment_type" id="tenancy_quarterly_payment_type" data-allow-clear="true">
                                                    <option></option>
                                                    <option value="pre_pay">Pre-pay (Due at start of quarter)</option>
                                                    <option value="post_pay">Post-pay (Due at end of quarter)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="agreement_late_fee_section">
                                            <div class="fv-row">
                                                <label class="fs-5 fw-bold mb-2">Late fee (optional)</label>
                                                <input type="number" name="late_fee_amount" class="form-control form-control-lg form-control-solid" min="0" value="0" placeholder="0.00" step="0.01">
                                                <div class="form-text">Fee charged for late rent payments</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="agreement_penalty_section">
                                            <div class="fv-row">
                                                <label class="fs-5 fw-bold mb-2">Daily late-payment penalty (optional)</label>
                                                <input type="number" name="penalty_amount" class="form-control form-control-lg form-control-solid" min="0" value="0" placeholder="0.00" step="0.01">
                                                <div class="form-text">Amount per day after the grace period; leave zero if none.</div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Late Fee and Penalty Section-->
                                </div>
                            </div>
                            <!--end::Step 3-->

                            <!--begin::Step 4-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100"><h2 class="tenant-step-title">Documents & parking</h2><p class="tenant-step-help">Attach supporting documents and allocate existing parking spaces. These items are optional; no payment is recorded in this step.</p>

                                    <!--begin::Section: Document Upload-->
                                    <div class="mb-10 border-bottom pb-6">
                                        <h5 class="fw-bold text-gray-800 mb-4">
                                            <i class="fas fa-file-upload me-2 text-primary"></i> Document Management
                                        </h5>

                                        <!-- Upload Documents -->
                                        <div class="fv-row mb-6">
                                            <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                <span>Upload New Documents</span>
                                                <i class="fas fa-info-circle ms-2 fs-7 text-muted" data-bs-toggle="tooltip" title="Upload relevant user documents"></i>
                                            </label>
                                            <input type="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" name="documents[]" class="form-control form-control-lg form-control-solid" multiple>
                                            <div class="form-text">PDF, Word, JPG or PNG. Maximum 10 MB per file.</div>
                                        </div>

                                        <!-- Select Existing Legal Files -->
                                        <div class="fv-row">
                                            <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                <span>Legal files to send (optional)</span>
                                                <i class="fas fa-info-circle ms-2 fs-7 text-muted" data-bs-toggle="tooltip" title="Choose from existing legal documents stored in the system"></i>
                                            </label>
                                            @if($legalFiles->isNotEmpty())
                                            <select name="legal_files[]" id="legal_files" class="form-select form-select-lg form-select-solid" data-control="select2" data-placeholder="Select legal files" multiple>
                                                @foreach ($legalFiles as $file)
                                                    <option value="{{ $file->file_name }}">
                                                        {{ $file->type_name }} — {{ $file->file_name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @else
                                            <p class="form-text mb-0">No reusable legal files are available. You can attach supporting documents above.</p>
                                            @endif
                                        </div>
                                    </div>
                                    <!--end::Section-->

                                    <!--begin::Section: Property Details-->
                                    <div class="mb-10 border-bottom pb-6">
                                        <h5 class="fw-bold text-gray-800 mb-4">
                                            <i class="fas fa-building me-2 text-primary"></i> Property Details
                                        </h5>


                                        <!-- Parking Slots -->
                                        <div class="fv-row mb-6" id="parking_slots_section">
                                            <label class="fs-6 fw-semibold mb-2">Parking spaces (optional)</label>
                                            <select class="form-select form-select-solid" data-control="select2" data-hide-search="false"
                                                    data-placeholder="Select Parking Slot" name="parking_slot[]" id="parking_slots" data-allow-clear="true" multiple>
                                                <option></option>
                                            </select>
                                        </div>

                                        <p class="form-text">Need another space? Add it in the property's Facilities section before assigning it here.</p>
                                    </div>
                                    <!--end::Section-->

                                    <!--begin::Section: Tenancy notes & special terms-->
                                    <div>
                                        <h5 class="fw-bold text-gray-800 mb-4">
                                            <i class="fas fa-sticky-note me-2 text-primary"></i> Tenancy notes & special terms
                                        </h5>

                                        <div class="fv-row">
                                            <label class="fs-6 fw-semibold mb-2">Notes</label>
                                            <textarea name="notes" class="form-control form-control-lg form-control-solid" rows="4"
                                                    placeholder="Add any additional notes or comments..."></textarea>
                                        </div>
                                    </div>
                                    <!--end::Section-->

                                </div>
                            </div>
                            <!--end::Step 4-->

                            <!--begin::Step 5-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100">
                                    <h2 class="tenant-review-title">Review the tenancy</h2>
                                    <p class="tenant-review-intro">Check the tenant, property and agreement. Creating the tenancy reserves the selected unit, creates the lease and sets up initial billing.</p>
                                    <div data-onboard-review class="tenant-review"></div>
                                    <label class="tenant-confirm"><input type="checkbox" name="onboarding_confirmed" value="1" data-onboard-confirm><span>I have checked these details and confirm this tenancy is ready to create.</span></label>
                                </div>
                            </div>
                            <!--end::Step 5-->

                            </div>
                            <!--begin::Actions-->
                            <div class="d-flex flex-stack pt-10 tenant-onboard-actions">
                                <!--begin::Wrapper-->
                                <div class="me-2">
                                    <button type="button" class="btn btn-lg btn-light-primary me-3" data-kt-stepper-action="previous">
                                        <span class="svg-icon svg-icon-3 me-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <rect opacity="0.5" x="6" y="11" width="13" height="2" rx="1" fill="black" />
                                                <path d="M8.56569 11.4343L12.75 7.25C13.1642 6.83579 13.1642 6.16421 12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75L5.70711 11.2929C5.31658 11.6834 5.31658 12.3166 5.70711 12.7071L11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25C13.1642 17.8358 13.1642 17.1642 12.75 16.75L8.56569 12.5657C8.25327 12.2533 8.25327 11.7467 8.56569 11.4343Z" fill="black" />
                                            </svg>
                                        </span>Back
                                    </button>
                                </div>
                                <!--end::Wrapper-->
                                <!--begin::Wrapper-->
                                <div>
                                    <button type="button" class="btn btn-lg btn-primary" data-kt-stepper-action="submit">
                                        <span class="indicator-label">Create tenancy
                                            <span class="svg-icon svg-icon-3 ms-2 me-0">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                    <rect opacity="0.5" x="18" y="13" width="13" height="2" rx="1" transform="rotate(-180 18 13)" fill="black" />
                                                    <path d="M15.4343 12.5657L11.25 16.75C10.8358 17.1642 10.8358 17.8358 11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25L18.2929 12.7071C18.6834 12.3166 18.6834 11.6834 18.2929 11.2929L12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75C10.8358 6.16421 10.8358 6.83579 11.25 7.25L15.4343 11.4343C15.7467 11.7467 15.7467 12.2533 15.4343 12.5657Z" fill="black" />
                                                </svg>
                                            </span>
                                        </span>
                                        <span class="indicator-progress">Please wait...
                                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                        </span>
                                    </button>
                                    <button type="button" class="btn btn-lg btn-primary" data-kt-stepper-action="next">Continue
                                        <span class="svg-icon svg-icon-3 ms-1 me-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <rect opacity="0.5" x="18" y="13" width="13" height="2" rx="1" transform="rotate(-180 18 13)" fill="black" />
                                                <path d="M15.4343 12.5657L11.25 16.75C10.8358 17.1642 10.8358 17.8358 11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25L18.2929 12.7071C18.6834 12.3166 18.6834 11.6834 18.2929 11.2929L12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75C10.8358 6.16421 10.8358 6.83579 11.25 7.25L15.4343 11.4343C15.7467 11.7467 15.7467 12.2533 15.4343 12.5657Z" fill="black" />
                                            </svg>
                                        </span>
                                    </button>
                                </div>
                                <!--end::Wrapper-->
                            </div>
                            <!--end::Actions-->
                        </form>
                        <!--end::Form-->
                    </div>
                    <!--end::Content-->
                </div>
                <!--end::Stepper-->
            </div>
        </div>

    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

@if($conversionLead)
<script>
    // Prefill the tenant step from the lead being converted. Runs after the stepper and the form's
    // own handlers are ready, and uses jQuery events so the form reveals the matching fields.
    window.addEventListener('load', function () {
        const banner = document.querySelector('[data-conversion-lead]');
        if (!banner || !window.jQuery) return;
        const lead = JSON.parse(banner.dataset.lead);
        const form = document.getElementById('kt_modal_add_tenant_form');
        const $ = window.jQuery;
        setTimeout(() => {
            $(form).find(`input[name="tenant_type"][value="${lead.type}"]`).prop('checked', true).trigger('change');
            $(form).find('input[name="tenant_option"][value="new"]').prop('checked', true).trigger('change');
            // Fill after the form's fade transitions (which clear the other type's fields).
            setTimeout(() => {
                const fields = lead.type === 'Business'
                    ? ['business_name', 'business_registration', 'business_contact_person', 'business_email', 'business_phone']
                    : ['tenant_name', 'tenant_email', 'tenant_phone', 'occupation'];
                fields.forEach(name => {
                    const input = form.querySelector(`[name="${name}"]`);
                    if (input && lead[name] && !input.value) $(input).val(lead[name]).trigger('input').trigger('change');
                });
                const leadSelect = form.querySelector(lead.type === 'Business' ? '[name="business_lead_id"]' : '[name="individual_lead_id"]');
                if (leadSelect) $(leadSelect).append(new Option(lead.label, lead.id, true, true)).trigger('change').prop('disabled', true);
                const agent = form.querySelector('#assigned_agent');
                if (agent && lead.agent) { $(agent).append(new Option(lead.agent.name, lead.agent.id, true, true)).trigger('change'); $(agent).data('userPicked', true); }
                form.dataset.leadPrefilled = '1';
            }, 800);
        }, 300);
    });
</script>
@endif
@endsection
