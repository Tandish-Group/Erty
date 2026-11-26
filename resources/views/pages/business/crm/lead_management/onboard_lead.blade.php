@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
		<!--begin::Page title-->
		<div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
			<!--begin::Heading-->
			<h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Onboarding Lead</h1>
			<!--end::Heading-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
				<li class="breadcrumb-item text-muted">Home</li>
				<li class="breadcrumb-item text-muted">{{auth()->user()->business['business_name']}}</li>
				<li class="breadcrumb-item text-dark">Onboarding Lead</li>
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
<div class="content d-flex flex-column flex-column-fluid lead-capture" id="kt_content">
	<!--begin::Container-->
    <div class="container-xxl lead-capture-content" id="kt_content_container">
        <header class="lead-page-heading">
            <div>
                @businessCan('crm_management', 'view')<a href="/customer_relationship" class="lead-back-link">← CRM dashboard</a>@endbusinessCan
                <span class="lead-eyebrow">Lead management</span>
                <h1>Capture a lead</h1>
                <p>Add a prospect and the details your team needs to follow up.</p>
            </div>
        </header>
        <section class="lead-capture-card lead-onboard-shell">
            <div class="lead-onboard-progress">
                <span data-lead-progress-label role="status">Step 1 of 4 · Lead type</span>
                <span>Added to your pipeline after completion</span>
                <progress data-lead-progress max="4" value="1" aria-label="Lead capture progress"></progress>
            </div>
            <div class="card-body">
                <!--begin::Stepper-->
                <div class="stepper stepper-pills stepper-column d-flex flex-column flex-xl-row flex-row-fluid lead-onboard-stepper" id="kt_modal_add_lead_stepper">
                    <!--begin::Aside-->
                    <div class="lead-step-sidebar">
                        <!--begin::Nav-->
                        <div class="stepper-nav ps-lg-10">
                            <!--begin::Step 1-->
                            <div class="stepper-item current" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">1</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Lead Type</h3>
                                    <div class="stepper-desc">Individual or Business</div>
                                </div>
                            </div>
                            <!--end::Step 1-->
                            
                            <!--begin::Step 2-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">2</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Contact Info</h3>
                                    <div class="stepper-desc">Personal details</div>
                                </div>
                            </div>
                            <!--end::Step 2-->
                            
                            <!--begin::Step 3-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">3</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Requirements</h3>
                                    <div class="stepper-desc">Property preferences</div>
                                </div>
                            </div>
                            <!--end::Step 3-->
                            
                            <!--begin::Step 4-->
                            <div class="stepper-item" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">4</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Additional Info</h3>
                                    <div class="stepper-desc">Source & notes</div>
                                </div>
                            </div>
                            <!--end::Step 4-->
                        </div>
                        <!--end::Nav-->
                    </div>
                    <!--end::Aside-->

                    <!--begin::Content-->
                    <div class="lead-step-content flex-row-fluid">
                        <!--begin::Form-->
                        <form class="form" novalidate="novalidate" id="kt_modal_add_lead_form">
                            <!--begin::Step 1 - Lead Type-->
                            <div class="current" data-kt-stepper-element="content">
                                <div class="w-100">
                                    <!--begin::Input group-->
                                    <div class="fv-row">
                                        <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                            <span class="required">Lead Type</span>
                                            <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select whether this is an individual or business lead"></i>
                                        </label>
                                        <p class="text-muted mb-6">Choose the type of lead to capture the appropriate information for follow-up.</p>
                                        
                                        <!--begin:Options-->
                                        <div class="fv-row row justify-content-center lead-type-options">
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
                                                            <span class="fs-7 text-muted">Personal lead</span>
                                                        </span>
                                                    </span>
                                                    <span class="form-check form-check-custom form-check-solid mt-4">
                                                        <input class="form-check-input" type="radio" name="lead_type" value="Individual" />
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
                                                            <span class="fs-7 text-muted">Corporate lead</span>
                                                        </span>
                                                    </span>
                                                    <span class="form-check form-check-custom form-check-solid mt-4">
                                                        <input class="form-check-input" type="radio" name="lead_type" value="Business" />
                                                    </span>
                                                </label>
                                                <!--end::Option-->
                                            </div>
                                        </div>
                                        <!--end:Options-->
                                    </div>
                                    <!--end::Input group-->
                                </div>
                            </div>
                            <!--end::Step 1-->

                            <!--begin::Step 2 - Contact Information-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100 scroll h-400px" style="overflow-x: hidden">
                                    <!--begin::Profile Mode Selection-->
                                    <div class="profile-mode-selection">
                                        <div class="text-center mb-8">
                                            <h3 class="fw-bolder text-dark mb-3">Lead Profile</h3>
                                            <p class="text-muted fs-6">Create new profile or use existing contact</p>
                                        </div>
                                        
                                        <!--begin::Input group-->
                                        <div class="fv-row">
                                            <label class="d-flex align-items-center fs-5 fw-bold mb-4 justify-content-center">
                                                <span class="required">Profile Mode</span>
                                                <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Choose whether to create a new profile or use an existing one"></i>
                                            </label>
                                            
                                            <!--begin:Options-->
                                            <div class="fv-row row justify-content-center">
                                                <div class="col-6">
                                                    <!--begin:Option-->
                                                    <input type="radio" class="btn-check" name="profile_mode" value="new" id="kt_profile_mode_new" />
                                                    <label class="btn btn-outline btn-outline-dashed btn-outline-default p-14 d-flex flex-column justify-content-center align-items-center mb-5" for="kt_profile_mode_new">
                                                        <!--begin::Svg Icon-->
                                                        <span class="svg-icon svg-icon-4x">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                                <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="5" fill="currentColor"/>
                                                                <rect x="10.8891" y="17.8033" width="12" height="2" rx="1" transform="rotate(-90 10.8891 17.8033)" fill="currentColor"/>
                                                                <rect x="6.01041" y="10.9247" width="12" height="2" rx="1" fill="currentColor"/>
                                                            </svg>
                                                        </span>
                                                        <!--end::Svg Icon-->
                                                        <span class="d-block fw-semibold text-center">
                                                            <span class="text-dark fw-bold d-block fs-5">New Profile</span>
                                                            <span class="text-muted fw-semibold fs-6">Create a new contact profile</span>
                                                        </span>
                                                    </label>
                                                    <!--end::Option-->
                                                </div>
                                                
                                                <div class="col-6">
                                                    <!--begin:Option-->
                                                    <input type="radio" class="btn-check" name="profile_mode" value="existing" id="kt_profile_mode_existing" />
                                                    <label class="btn btn-outline btn-outline-dashed btn-outline-default p-14 d-flex flex-column justify-content-center align-items-center mb-5" for="kt_profile_mode_existing">
                                                        <!--begin::Svg Icon-->
                                                        <span class="svg-icon svg-icon-4x">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                                <path d="M16.0173 9H15.3945C14.2833 9 13.263 9.61425 12.7431 10.5963L12.154 11.7091C12.0645 11.8781 12.1072 12.0868 12.2559 12.2071L12.6402 12.5183C13.2631 13.0225 13.7556 13.6691 14.0764 14.4035L14.2321 14.7601C14.2957 14.9058 14.4396 15 14.5987 15H18.6747C19.7297 15 20.4057 13.8774 19.912 12.945L18.6686 10.5963C18.1487 9.61425 17.1285 9 16.0173 9Z" fill="currentColor"/>
                                                                <rect opacity="0.3" x="14" y="4" width="4" height="4" rx="2" fill="currentColor"/>
                                                                <path d="M4.65486 14.8559C5.40389 13.1224 7.11161 12 9 12C10.8884 12 12.5961 13.1224 13.3451 14.8559L14.793 18.2067C15.3636 19.5271 14.3955 21 12.9571 21H5.04292C3.60453 21 2.63644 19.5271 3.20698 18.2067L4.65486 14.8559Z" fill="currentColor"/>
                                                                <rect opacity="0.3" x="6" y="5" width="6" height="6" rx="3" fill="currentColor"/>
                                                            </svg>
                                                        </span>
                                                        <!--end::Svg Icon-->
                                                        <span class="d-block fw-semibold text-center">
                                                            <span class="text-dark fw-bold d-block fs-5">Existing Profile</span>
                                                            <span class="text-muted fw-semibold fs-6">Select from existing contacts</span>
                                                        </span>
                                                    </label>
                                                    <!--end::Option-->
                                                </div>
                                            </div>
                                            <!--end:Options-->
                                        </div>
                                        <!--end::Input group-->
                                    </div>
                                    <!--end::Profile Mode Selection-->
                            
                                    <!--begin::Existing Profile Search-->
                                    <div class="existing-profile-search" style="display: none;">
                                        <!--begin::Back Button-->
                                        <div class="mb-5">
                                            <button type="button" class="btn btn-sm btn-light-primary" id="back_to_profile_mode">
                                                <span class="svg-icon svg-icon-3 me-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <path d="M9.60001 11H21C21.6 11 22 11.4 22 12C22 12.6 21.6 13 21 13H9.60001V11Z" fill="currentColor"/>
                                                        <path opacity="0.3" d="M9.6 20V4L2.3 11.3C1.9 11.7 1.9 12.3 2.3 12.7L9.6 20Z" fill="currentColor"/>
                                                    </svg>
                                                </span>
                                                Back to Profile Mode
                                            </button>
                                        </div>
                                        <!--end::Back Button-->
                                        
                                        <div class="text-center mb-8">
                                            <h3 class="fw-bolder text-dark mb-3">Select Existing Profile</h3>
                                            <p class="text-muted fs-6">Search and select an existing lead profile</p>
                                        </div>
                                        
                                        <div class="fv-row mb-7">
                                            <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                <span class="required">Search Profile</span>
                                                <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Search by name, email, phone, or company"></i>
                                            </label>
                                            <input type="hidden" name="existing_profile_id" id="existing_profile_id" />
                                            <select name="profile_search" id="profile_search" class="form-select form-select-solid" data-control="select2" data-placeholder="Type to search...">
                                                <option></option>
                                            </select>
                                            <div class="form-text">Search by name, email, phone number, or company name</div>
                                        </div>
                            
                                        <!--begin::Selected Profile Preview-->
                                        <div id="selected_profile_preview" class="card border border-dashed border-gray-300 mb-7" style="display: none;">
                                            <div class="card-body">
                                                <h5 class="card-title mb-3">Selected Profile</h5>
                                                <div id="profile_preview_content"></div>
                                            </div>
                                        </div>
                                        <!--end::Selected Profile Preview-->
                                    </div>
                                    <!--end::Existing Profile Search-->
                                    
                                    <!--begin::Individual Fields-->
                                    <div class="individual-fields lead-profile-details" style="display: none;">
                                        <!--begin::Back Button-->
                                        <div class="mb-5">
                                            <button type="button" class="btn btn-sm btn-light-primary back-to-profile-mode-btn">
                                                <span class="svg-icon svg-icon-3 me-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <path d="M9.60001 11H21C21.6 11 22 11.4 22 12C22 12.6 21.6 13 21 13H9.60001V11Z" fill="currentColor"/>
                                                        <path opacity="0.3" d="M9.6 20V4L2.3 11.3C1.9 11.7 1.9 12.3 2.3 12.7L9.6 20Z" fill="currentColor"/>
                                                    </svg>
                                                </span>
                                                Back to Profile Mode
                                            </button>
                                        </div>
                                        <!--end::Back Button-->
                                        
                                        <div class="text-center mb-8">
                                            <h3 class="fw-bolder text-dark mb-3">Individual Lead Details</h3>
                                            <p class="text-muted fs-6">Enter the personal information for the individual lead</p>
                                        </div>
                                        
                                        <!-- Rest of individual fields remain the same -->
                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">First Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter lead's first name"></i>
                                                </label>
                                                <input type="text" name="first_name" class="form-control form-control-solid" placeholder="Enter first name">
                                            </div>
                                            <!--end::Input group-->
                                            
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Last Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter lead's last name"></i>
                                                </label>
                                                <input type="text" name="last_name" class="form-control form-control-solid" placeholder="Enter last name">
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Email Address</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter valid email address"></i>
                                                </label>
                                                <input type="email" name="email" class="form-control form-control-solid" placeholder="Enter email address">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Phone Number</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter phone number"></i>
                                                </label>
                                                <input type="text" name="phone" class="form-control form-control-solid" placeholder="Enter phone number">
                                            </div>
                                        </div>
                            
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Occupation</label>
                                                <input type="text" name="occupation" class="form-control form-control-solid" placeholder="Enter occupation">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Date of Birth</label>
                                                <input type="date" name="date_of_birth" class="form-control form-control-solid">
                                            </div>
                                        </div>
                            
                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Current Address</label>
                                            <textarea name="current_address" class="form-control form-control-solid" rows="3" placeholder="Enter current residential address"></textarea>
                                        </div>
                                    </div>
                                    <!--end::Individual Fields-->
                            
                                    <!--begin::Business Fields-->
                                    <div class="business-fields lead-profile-details" style="display: none;">
                                        <!--begin::Back Button-->
                                        <div class="mb-5">
                                            <button type="button" class="btn btn-sm btn-light-primary back-to-profile-mode-btn">
                                                <span class="svg-icon svg-icon-3 me-1">
                                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                        <path d="M9.60001 11H21C21.6 11 22 11.4 22 12C22 12.6 21.6 13 21 13H9.60001V11Z" fill="currentColor"/>
                                                        <path opacity="0.3" d="M9.6 20V4L2.3 11.3C1.9 11.7 1.9 12.3 2.3 12.7L9.6 20Z" fill="currentColor"/>
                                                    </svg>
                                                </span>
                                                Back to Profile Mode
                                            </button>
                                        </div>
                                        <!--end::Back Button-->
                                        
                                        <div class="text-center mb-8">
                                            <h3 class="fw-bolder text-dark mb-3">Business Lead Details</h3>
                                            <p class="text-muted fs-6">Enter the business information for the corporate lead</p>
                                        </div>
                                        
                                        <!-- Rest of business fields remain the same -->
                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Company Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter registered company name"></i>
                                                </label>
                                                <input type="text" name="company_name" class="form-control form-control-solid" placeholder="Enter company name">
                                            </div>
                                            <!--end::Input group-->
                                            
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span>Registration Number</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter business registration number"></i>
                                                </label>
                                                <input type="text" name="registration_number" class="form-control form-control-solid" placeholder="Enter registration number">
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        
                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Contact Person</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter primary contact person's name"></i>
                                                </label>
                                                <input type="text" name="contact_person" class="form-control form-control-solid" placeholder="Enter contact person name">
                                            </div>
                                            <!--end::Input group-->
                                            
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span>Job Title</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter contact person's job title"></i>
                                                </label>
                                                <input type="text" name="contact_title" class="form-control form-control-solid" placeholder="Enter job title">
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        
                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Business Email</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter business email address"></i>
                                                </label>
                                                <input type="email" name="business_email" class="form-control form-control-solid" placeholder="Enter business email">
                                            </div>
                                            <!--end::Input group-->
                                            
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Business Phone</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter business phone number"></i>
                                                </label>
                                                <input type="text" name="business_phone" class="form-control form-control-solid" placeholder="Enter business phone">
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                            
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Industry</label>
                                                <select name="industry" class="form-select form-select-solid" data-control="select2" data-placeholder="Select industry">
                                                    <option></option>
                                                    <option value="technology">Technology</option>
                                                    <option value="healthcare">Healthcare</option>
                                                    <option value="finance">Finance</option>
                                                    <option value="retail">Retail</option>
                                                    <option value="manufacturing">Manufacturing</option>
                                                    <option value="education">Education</option>
                                                    <option value="consulting">Consulting</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Company Size</label>
                                                <select name="company_size" class="form-select form-select-solid" data-control="select2" data-placeholder="Select size">
                                                    <option></option>
                                                    <option value="1-10">1-10 employees</option>
                                                    <option value="11-50">11-50 employees</option>
                                                    <option value="51-200">51-200 employees</option>
                                                    <option value="201-1000">201-1000 employees</option>
                                                    <option value="1000+">1000+ employees</option>
                                                </select>
                                            </div>
                                        </div>
                            
                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Business Address</label>
                                            <textarea name="business_address" class="form-control form-control-solid" rows="3" placeholder="Enter business address"></textarea>
                                        </div>
                                    </div>
                                    <!--end::Business Fields-->
                                </div>
                            </div>
                            <!--end::Step 2-->

                            <!--begin::Step 3 - Property Requirements-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100 scroll h-400px" style="overflow-x: hidden;">
                                    <div class="text-center mb-8">
                                        <h3 class="fw-bolder text-dark mb-3">Property Requirements</h3>
                                        <p class="text-muted fs-6">Define the property preferences and requirements for this lead</p>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2 required">Property Type</label>
                                            <select name="property_type" id="lead_property_type" class="form-select form-select-solid" data-control="select2" data-placeholder="Select property type">
                                                <option></option>
                                                @if(count($property_types) > 0)
                                                    @foreach($property_types as $property_type)
                                                        <option value="{{$property_type->property_type}}">{{$property_type->property_type}}</option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>
                            
                                        <div class="col-6 fv-row">
                                            <!--begin::Label-->
                                            <label class="d-flex align-items-center fs-5 fw-bold mb-2 justify-content-center">
                                                <span class="required">Select Complex</span>
                                                <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Select the complex for this lead"></i>
                                            </label>
                                            <!--end::Label-->
                                            <!--begin::Input group-->
                                            <div class="fv-row">
                                                <!--begin::Input-->
                                                <select class="form-select form-select-solid" 
                                                        data-allow-clear="true" 
                                                        data-control="select2" 
                                                        data-hide-search="false" 
                                                        data-placeholder="Select Property Type First" 
                                                        id="lead_complex" 
                                                        name="complex"
                                                        disabled>
                                                    <option></option>
                                                    <!-- Options will be loaded via AJAX after property type selection -->
                                                </select>
                                                <!--end::Input-->
                                            </div>
                                            <!--end::Input group-->
                                        </div>
                                        
                                        <!--begin::Complex Units Display-->
                                        <div class="col-12 mb-7" id="lead_complex_units_container" style="display: none;">
                                            <div class="card rounded border border-secondary">
                                                <div class="card-header">
                                                    <h3 class="card-title text-primary">
                                                        <i class="fas fa-building me-2"></i>Available Units in Complex
                                                    </h3>
                                                    <p>Below are the listed units in the selected complex.</p>
                                                </div>
                                                <div class="card-body p-5">
                                                    <div id="lead_complex_units_list" class="row g-3">
                                                        <!-- Units will be loaded here dynamically -->
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--end::Complex Units Display-->
                                        
                                        <div class="col-12 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2 required">Purpose</label>
                                            <select name="purpose" class="form-select form-select-solid" data-control="select2" data-placeholder="Select purpose">
                                                <option></option>
                                                <option value="rent">Rent</option>
                                                <option value="buy">Buy</option>
                                                <option value="lease">Lease</option>
                                            </select>
                                        </div>
                                    </div>
                            
                                    <div class="row">
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Budget Min</label>
                                            <input type="number" class="form-control form-control-solid" placeholder="0" name="budget_min" step="0.01" />
                                        </div>
                                        
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Budget Max</label>
                                            <input type="number" class="form-control form-control-solid" placeholder="0" name="budget_max" step="0.01" />
                                        </div>
                                    </div>
                            
                                    <!--begin::Residential-specific fields-->
                                    <div class="residential-property-fields d-none">
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Preferred Bedrooms</label>
                                                <select name="bedrooms" class="form-select form-select-solid" data-control="select2" data-placeholder="Select bedrooms">
                                                    <option></option>
                                                    <option value="studio">Studio</option>
                                                    <option value="1">1 Bedroom</option>
                                                    <option value="2">2 Bedrooms</option>
                                                    <option value="3">3 Bedrooms</option>
                                                    <option value="4">4 Bedrooms</option>
                                                    <option value="5+">5+ Bedrooms</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Preferred Bathrooms</label>
                                                <select name="bathrooms" class="form-select form-select-solid" data-control="select2" data-placeholder="Select bathrooms">
                                                    <option></option>
                                                    <option value="1">1 Bathroom</option>
                                                    <option value="1.5">1.5 Bathrooms</option>
                                                    <option value="2">2 Bathrooms</option>
                                                    <option value="2.5">2.5 Bathrooms</option>
                                                    <option value="3">3 Bathrooms</option>
                                                    <option value="3+">3+ Bathrooms</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Residential-specific fields-->
                            
                                    <!--begin::Commercial-specific fields-->
                                    <div class="commercial-property-fields d-none">
                                        <div class="row">
                                            <div class="col-12 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Business Type/Use</label>
                                                <select name="business_use" class="form-select form-select-solid" data-control="select2" data-placeholder="Select business type">
                                                    <option></option>
                                                    <option value="retail">Retail</option>
                                                    <option value="office">Office</option>
                                                    <option value="restaurant">Restaurant/Café</option>
                                                    <option value="warehouse">Warehouse</option>
                                                    <option value="industrial">Industrial</option>
                                                    <option value="medical">Medical/Healthcare</option>
                                                    <option value="education">Education</option>
                                                    <option value="mixed_use">Mixed Use</option>
                                                    <option value="other">Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Commercial-specific fields-->
                            
                                    <!--begin::Square Area (Always visible)-->
                                    <div class="row">
                                        <div class="col-12 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Preferred Square Area (sq ft)</label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <input type="number" class="form-control form-control-solid" placeholder="Min sq ft" name="min_sq_area" step="0.01" />
                                                </div>
                                                <div class="col-6">
                                                    <input type="number" class="form-control form-control-solid" placeholder="Max sq ft" name="max_sq_area" step="0.01" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Square Area-->
                            
                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Location Preference</label>
                                        <input type="text" class="form-control form-control-solid" placeholder="Preferred area, neighborhood, or specific location" name="location_preference" />
                                    </div>
                            
                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Move-in Timeline</label>
                                        <select name="timeline" class="form-select form-select-solid" data-control="select2" data-placeholder="Select timeline">
                                            <option></option>
                                            <option value="immediately">Immediately</option>
                                            <option value="within_1_month">Within 1 month</option>
                                            <option value="1_3_months">1-3 months</option>
                                            <option value="3_6_months">3-6 months</option>
                                            <option value="6_months_plus">6+ months</option>
                                            <option value="flexible">Flexible</option>
                                        </select>
                                    </div>
                            
                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Special Requirements</label>
                                        <textarea class="form-control form-control-solid" rows="3" name="special_requirements" placeholder="Pet-friendly, parking, amenities, accessibility needs, etc."></textarea>
                                    </div>
                                </div>
                            </div>
                            <!--end::Step 3-->

                            <!--begin::Step 4 - Additional Information-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100 scroll h-400px" style="overflow-x: hidden;">
                                    <div class="text-center mb-8">
                                        <h3 class="fw-bolder text-dark mb-3">Additional Information</h3>
                                        <p class="text-muted fs-6">Lead source, priority level, and additional notes for follow-up</p>
                                    </div>

                                    <div class="row">
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2 required">Lead Source</label>
                                            <select name="source" class="form-select form-select-solid" data-control="select2" data-placeholder="Select source">
                                                <option></option>
                                                <option value="website">Website</option>
                                                <option value="referral">Referral</option>
                                                <option value="social_media">Social Media</option>
                                                <option value="google_ads">Google Ads</option>
                                                <option value="facebook_ads">Facebook Ads</option>
                                                <option value="walk_in">Walk-in</option>
                                                <option value="phone_call">Phone Call</option>
                                                <option value="email">Email Inquiry</option>
                                                <option value="agent_referral">Agent Referral</option>
                                                <option value="event">Event/Trade Show</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2 required">Priority Level</label>
                                            <select name="priority" class="form-select form-select-solid" data-control="select2" data-placeholder="Select priority">
                                                <option></option>
                                                <option value="hot">Hot - Ready to move</option>
                                                <option value="warm">Warm - Actively looking</option>
                                                <option value="cold">Cold - Just browsing</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2 required">Preferred Contact Method</label>
                                            <select name="contact_method" class="form-select form-select-solid" data-control="select2" data-placeholder="Select method">
                                                <option></option>
                                                <option value="phone">Phone Call</option>
                                                <option value="email">Email</option>
                                                <option value="sms">SMS/Text</option>
                                                <option value="whatsapp">WhatsApp</option>
                                                <option value="any">Any Method</option>
                                            </select>
                                        </div>
                                        
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Best Time to Contact</label>
                                            <select name="contact_time" class="form-select form-select-solid" data-control="select2" data-placeholder="Select time">
                                                <option></option>
                                                <option value="morning">Morning (9AM-12PM)</option>
                                                <option value="afternoon">Afternoon (12PM-5PM)</option>
                                                <option value="evening">Evening (5PM-8PM)</option>
                                                <option value="weekend">Weekends</option>
                                                <option value="anytime">Anytime</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="fv-row mb-7" id="referral_details_section" style="display: none;">
                                        <label class="fs-6 fw-semibold mb-2">Referral Details</label>
                                        <input type="text" name="referral_details" class="form-control form-control-solid" placeholder="Who referred this lead? (Name, relationship, etc.)">
                                    </div>

                                    <div class="fv-row mb-7">
                                        <!-- Hidden fields to store complex's assigned agent -->
                                        <input type="hidden" id="complex_assigned_agent_id" name="complex_assigned_agent_id">
                                        <input type="hidden" id="complex_assigned_agent_name" name="complex_assigned_agent_name">
                                        
                                        <label class="fs-6 fw-semibold mb-2">Assigned Agent (Optional)</label>
                                        <select name="assigned_agent" id="lead_assigned_agent" class="form-select form-select-solid" data-control="select2" data-placeholder="Select an agent" data-ajax-url="{{ route('agents.search') }}">
                                            <option></option>
                                        </select>
                                        <div class="form-text">Leave empty to use the complex's assigned agent (if available)</div>
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Initial Notes</label>
                                        <textarea class="form-control form-control-solid" rows="4" name="notes" placeholder="Initial conversation notes, specific interests, concerns, or any other relevant information about this lead..."></textarea>
                                    </div>

                                    <div class="fv-row">
                                        <label class="fs-6 fw-semibold mb-2">Follow-up Date</label>
                                        <input type="datetime-local" name="follow_up_date" class="form-control form-control-solid">
                                        <div class="form-text">Set when this lead should be contacted next</div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Step 4-->

                            <!--begin::Actions-->
                            <div class="d-flex flex-stack pt-10 lead-onboard-actions">
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
                                        <span class="indicator-label">Add Lead
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
        </section>

    </div>
    <!--end::Container-->
</div>
<!--end::Content-->

@endsection
