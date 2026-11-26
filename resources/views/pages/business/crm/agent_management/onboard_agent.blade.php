@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
	<!--begin::Container-->
	<div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
		<!--begin::Page title-->
		<div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
			<!--begin::Heading-->
			<h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Onboarding Agent</h1>
			<!--end::Heading-->
			<!--begin::Breadcrumb-->
			<ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
				<li class="breadcrumb-item text-muted">Home</li>
				<li class="breadcrumb-item text-muted">{{auth()->user()->business['business_name']}}</li>
				<li class="breadcrumb-item text-dark">Onboarding Agent</li>
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
<div class="content d-flex flex-column flex-column-fluid crm-agent-onboarding" id="kt_content">
	<!--begin::Container-->
    <div class="container-xxl crm-agent-onboarding-content" id="kt_content_container">
        <header class="agent-onboard-heading">
            <div>
                <span class="agent-onboard-eyebrow">Team workspace</span>
                <h2>Create an agent profile</h2>
                <p>Add a team member, define their work arrangement, and configure compensation.</p>
            </div>
            <a href="/agents" class="agent-onboard-back"><i class="bi bi-arrow-left" aria-hidden="true"></i><span>Back to agents</span></a>
        </header>

        <section class="card rounded crm-agent-onboard-card">
            <div class="agent-onboard-progress">
                <span data-agent-onboard-progress-label role="status">Step 1 of 4 · Agent details</span>
                <span>Agent details are saved when you finish</span>
                <progress data-agent-onboard-progress max="4" value="1" aria-label="Agent onboarding progress"></progress>
            </div>
            <div class="card-body">
                <!--begin::Stepper-->
                <div class="stepper stepper-pills agent-onboard-stepper" id="kt_modal_add_agent_stepper">
                    <!--begin::Aside-->
                    <div class="agent-onboard-aside">
                        <!--begin::Nav-->
                        <div class="stepper-nav agent-onboard-nav">
                            <!--begin::Step 1-->
                            <div class="stepper-item current" data-kt-stepper-element="nav">
                                <div class="stepper-line w-40px"></div>
                                <div class="stepper-icon w-40px h-40px">
                                    <i class="stepper-check fas fa-check"></i>
                                    <span class="stepper-number">1</span>
                                </div>
                                <div class="stepper-label">
                                    <h3 class="stepper-title">Agent Details</h3>
                                    <div class="stepper-desc">Basic details</div>
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
                                    <h3 class="stepper-title">Employment</h3>
                                    <div class="stepper-desc">Role & department</div>
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
                                    <h3 class="stepper-title">Compensation</h3>
                                    <div class="stepper-desc">Commission & salary</div>
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
                                    <div class="stepper-desc">Documents & notes</div>
                                </div>
                            </div>
                            <!--end::Step 4-->
                        </div>
                        <!--end::Nav-->
                    </div>
                    <!--end::Aside-->

                    <!--begin::Content-->
                    <div class="flex-row-fluid agent-onboard-content">
                        <!--begin::Form-->
                        <form class="form" novalidate="novalidate" id="kt_modal_add_agent_form">

                            <!--begin::Step 1 - Personal Information-->
                            <div class="current" data-kt-stepper-element="content">
                                <div class="w-100 agent-step-content">
                                    <div class="text-center mb-8">
                                        <h3 class="fw-bolder text-dark mb-3">Agent Type & Basic Information</h3>
                                        <p class="text-muted fs-6">Select agent type and enter basic details</p>
                                    </div>
                                    
                                    <!--begin::Agent Type Selection-->
                                    <div class="fv-row mb-10">
                                        <label class="d-flex align-items-center fs-5 fw-bold mb-4">
                                            <span class="required">Select Agent Type</span>
                                            <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Choose whether this is an agency, employee, or contractor"></i>
                                        </label>
                                        
                                        <div class="row g-5">
                                            <!--Agency Option-->
                                            <div class="col-md-4">
                                                <input type="radio" class="btn-check" name="agent_type" value="agency" id="kt_agent_type_agency">
                                                <label class="btn btn-outline btn-outline-dashed btn-outline-default p-14 d-flex flex-column align-items-center h-100 agent-type-option" for="kt_agent_type_agency">
                                                    <i class="las la-building fs-3x text-primary mb-3"></i>
                                                    <span class="fw-bolder fs-5 mb-2 pt-4">Agency</span>
                                                    <span class="text-muted fs-7 text-center">External company managing leads</span>
                                                </label>
                                            </div>
                                            
                                            <!--Employee Option-->
                                            <div class="col-md-4">
                                                <input type="radio" class="btn-check" name="agent_type" value="employee" id="kt_agent_type_employee">
                                                <label class="btn btn-outline btn-outline-dashed btn-outline-default p-14 d-flex flex-column align-items-center h-100 agent-type-option" for="kt_agent_type_employee">
                                                    <i class="las la-user-tie fs-3x text-success mb-3"></i>
                                                    <span class="fw-bolder fs-5 mb-2 pt-4">Employee</span>
                                                    <span class="text-muted fs-7 text-center">Direct employee with benefits</span>
                                                </label>
                                            </div>
                                            
                                            <!--Contractor Option-->
                                            <div class="col-md-4">
                                                <input type="radio" class="btn-check" name="agent_type" value="contractor" id="kt_agent_type_contractor">
                                                <label class="btn btn-outline btn-outline-dashed btn-outline-default p-14 d-flex flex-column align-items-center h-100 agent-type-option" for="kt_agent_type_contractor">
                                                    <i class="las la-handshake fs-3x text-info mb-3"></i>
                                                    <span class="fw-bolder fs-5 mb-2 pt-4">Contractor</span>
                                                    <span class="text-muted fs-7 text-center">Hired individual on contract</span>
                                                </label>
                                            </div>
                                        </div>
                                        <p id="agent-type-hint" class="agent-type-hint mb-0">Choose an agent type to see the details needed for that profile.</p>
                                    </div>
                                    <!--end::Agent Type Selection-->

                                    <div id="agent_type_details" style="display: none;">
                                    <div class="separator separator-dashed my-8"></div>
                                    
                                    <!--begin::Agency Fields (hidden by default)-->
                                    <div id="agency_fields" style="display: none;">
                                        <h4 class="fw-bolder text-dark mb-5">Company Information</h4>
                                        
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Company Name</span>
                                                </label>
                                                <input type="text" name="company_name" class="form-control form-control-solid" placeholder="Enter company name">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">
                                                    <span>Agency License</span>
                                                </label>
                                                <input type="text" name="agency_license" class="form-control form-control-solid" placeholder="Enter license number">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">
                                                    <span>Tax ID</span>
                                                </label>
                                                <input type="text" name="tax_id" class="form-control form-control-solid" placeholder="Enter tax ID">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">
                                                    <span>Business Registration Number</span>
                                                </label>
                                                <input type="text" name="business_registration_number" class="form-control form-control-solid" placeholder="Enter registration number">
                                            </div>
                                        </div>
                                        
                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Company Address</label>
                                            <textarea name="company_address" class="form-control form-control-solid" rows="2" placeholder="Enter company address"></textarea>
                                        </div>
                                        
                                        <h5 class="fw-bold text-dark mb-4 mt-8">Contact Person</h5>
                                        
                                        <div class="row">
                                            <div class="col-12 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">
                                                    <span class="required">Contact Person Name</span>
                                                </label>
                                                <input type="text" name="contact_person_name" class="form-control form-control-solid" placeholder="Enter contact person name">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">
                                                    <span class="required">Contact Email</span>
                                                </label>
                                                <input type="email" name="contact_person_email" class="form-control form-control-solid" placeholder="Enter contact email">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">
                                                    <span class="required">Contact Phone</span>
                                                </label>
                                                <input type="text" name="contact_person_phone" class="form-control form-control-solid" placeholder="Enter contact phone">
                                            </div>
                                        </div>
                                        
                                        <div class="separator separator-dashed my-8"></div>
                                    </div>
                                    <!--end::Agency Fields-->
                                    
                                    <!--begin::Individual Fields (Employee/Contractor)-->
                                    <div id="individual_fields">
                                        <h4 class="fw-bolder text-dark mb-5">Personal Information</h4>
                                        
                                        <div class="row">
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">First Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter agent's first name"></i>
                                                </label>
                                                <input type="text" name="first_name" class="form-control form-control-solid" placeholder="Enter first name">
                                            </div>
                                            <!--end::Input group-->
                                            
                                            <!--begin::Input group-->
                                            <div class="col-6 fv-row mb-7">
                                                <label class="d-flex align-items-center fs-6 fw-semibold mb-2">
                                                    <span class="required">Last Name</span>
                                                    <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="tooltip" title="Enter agent's last name"></i>
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
                                                <label class="fs-6 fw-semibold mb-2">Date of Birth</label>
                                                <input type="date" name="date_of_birth" class="form-control form-control-solid">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Gender</label>
                                                <select name="gender" class="form-select form-select-solid" data-control="select2" data-placeholder="Select gender">
                                                    <option></option>
                                                    <option value="male">Male</option>
                                                    <option value="female">Female</option>
                                                    <option value="other">Other</option>
                                                    <option value="prefer_not_to_say">Prefer not to say</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Residential Address</label>
                                            <textarea name="address" class="form-control form-control-solid" rows="3" placeholder="Enter residential address"></textarea>
                                        </div>

                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">National ID/Passport</label>
                                                <input type="text" name="national_id" class="form-control form-control-solid" placeholder="Enter ID number">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Emergency Contact</label>
                                                <input type="text" name="emergency_contact" class="form-control form-control-solid" placeholder="Enter emergency contact number">
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Individual Fields-->
                                    </div>
                                </div>
                            </div>
                            <!--end::Step 1-->

                            <!--begin::Step 2 - Employment Details-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100 agent-step-content">
                                    <div class="text-center mb-8">
                                        <h3 class="fw-bolder text-dark mb-3">Employment Details</h3>
                                        <p class="text-muted fs-6">Define role, department, and work arrangement</p>
                                    </div>
                                    
                                    <!--begin::Common Fields (All Types)-->
                                    <div class="row">
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2 required">Department</label>
                                            <select name="department" class="form-select form-select-solid" data-control="select2" data-placeholder="Select department">
                                                <option></option>
                                                @foreach($agentDepartments as $department)
                                                    <option value="{{$department->id}}">{{$department->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2 required">Role/Position</label>
                                            <select name="role" class="form-select form-select-solid" data-control="select2" data-placeholder="Select role">
                                                <option></option>
                                                @foreach($agentRoles as $role)
                                                    <option value="{{$role->id}}">{{$role->name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <!--end::Common Fields-->
                                    
                                    <!--begin::Employee/Individual Fields-->
                                    <div id="employment_fields_employee">
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Employment Type</label>
                                                <select name="employment_type" class="form-select form-select-solid" data-control="select2" data-placeholder="Select type">
                                                    <option></option>
                                                    <option value="full_time">Full Time</option>
                                                    <option value="part_time">Part Time</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Start Date</label>
                                                <input type="date" name="start_date" class="form-control form-control-solid">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Reporting Manager</label>
                                                <select name="reporting_manager" class="form-select form-select-solid" data-control="select2" data-placeholder="Select manager">
                                                    <option></option>
                                                    <option value="1">John Smith - Sales Director</option>
                                                    <option value="2">Sarah Wilson - Operations Manager</option>
                                                    <option value="3">David Brown - Team Lead</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Work Location</label>
                                                <select name="work_location" class="form-select form-select-solid" data-control="select2" data-placeholder="Select location">
                                                    <option></option>
                                                    @foreach($agentWorkLocations as $department)
                                                        <option value="{{$department->id}}">{{$department->name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Probation Period (Months)</label>
                                                <input type="number" name="probation_period" class="form-control form-control-solid" placeholder="0" min="0" max="12" step="1">
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Employee Fields-->
                                    
                                    <!--begin::Contractor Fields-->
                                    <div id="employment_fields_contractor" style="display: none;">
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Contract Start Date</label>
                                                <input type="date" name="contract_start_date" class="form-control form-control-solid">
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Contract End Date</label>
                                                <input type="date" name="contract_end_date" class="form-control form-control-solid">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Contract Type</label>
                                                <select name="contract_type" class="form-select form-select-solid" data-control="select2" data-placeholder="Select type">
                                                    <option></option>
                                                    <option value="hourly">Hourly</option>
                                                    <option value="project">Project-based</option>
                                                    <option value="retainer">Retainer</option>
                                                    <option value="commission_only">Commission Only</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Work Location</label>
                                                <select name="work_location_contractor" class="form-select form-select-solid" data-control="select2" data-placeholder="Select location">
                                                    <option></option>
                                                    @foreach($agentWorkLocations as $department)
                                                        <option value="{{$department->id}}">{{$department->name}}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-12 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Payment Terms</label>
                                                <textarea name="payment_terms" class="form-control form-control-solid" rows="2" placeholder="Describe payment terms and conditions"></textarea>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Contractor Fields-->

                                    <!--begin::Common Fields (Individual & Contractor)-->
                                    <div id="common_individual_fields">
                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Specializations/Expertise</label>
                                            <select name="specializations[]" class="form-select form-select-solid" data-control="select2" data-placeholder="Select specializations" multiple="multiple">
                                                <option value="residential_sales">Residential Sales</option>
                                                <option value="commercial_sales">Commercial Sales</option>
                                                <option value="luxury_properties">Luxury Properties</option>
                                                <option value="rental_leasing">Rental & Leasing</option>
                                                <option value="property_management">Property Management</option>
                                                <option value="investment_advisory">Investment Advisory</option>
                                            </select>
                                        </div>

                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Job Description</label>
                                            <textarea name="job_description" class="form-control form-control-solid" rows="3" placeholder="Brief description of responsibilities and duties"></textarea>
                                        </div>
                                    </div>
                                    <!--end::Common Individual Fields-->
                                </div>
                            </div>
                            <!--end::Step 2-->

                            <!--begin::Step 3 - Compensation Details-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100 agent-step-content">
                                    <div class="text-center mb-8">
                                        <h3 class="fw-bolder text-dark mb-3">Compensation Structure</h3>
                                        <p class="text-muted fs-6">Configure salary, commission, and benefits</p>
                                    </div>
                                    
                                    <!--begin::Employee/Agency Compensation-->
                                    <div id="compensation_employee_agency">
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Base Salary (Monthly)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="base_salary" class="form-control form-control-solid" placeholder="0.00" step="0.01">
                                                </div>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2 required">Commission Rate (%)</label>
                                                <div class="input-group">
                                                    <input type="number" name="commission_rate" class="form-control form-control-solid" placeholder="0" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Commission Type</label>
                                                <select name="commission_type" class="form-select form-select-solid" data-control="select2" data-placeholder="Select type">
                                                    <option></option>
                                                    <option value="sales_value">Based on Sales Value</option>
                                                    <option value="net_profit">Based on Net Profit</option>
                                                    <option value="flat_rate">Flat Rate per Deal</option>
                                                    <option value="tiered">Tiered Commission</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Performance Bonus (%)</label>
                                                <div class="input-group">
                                                    <input type="number" name="bonus_rate" class="form-control form-control-solid" placeholder="0" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Payment Frequency</label>
                                            <select name="payment_frequency" class="form-select form-select-solid" data-control="select2" data-placeholder="Select frequency">
                                                <option></option>
                                                <option value="weekly">Weekly</option>
                                                <option value="bi_weekly">Bi-Weekly</option>
                                                <option value="monthly">Monthly</option>
                                                <option value="quarterly">Quarterly</option>
                                            </select>
                                        </div>
                                        
                                        <!--begin::Employee Benefits-->
                                        <div id="benefits_employee">
                                            <div class="fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-4">Benefits & Allowances</label>
                                                <div class="row">
                                                    <div class="col-6">
                                                        <div class="form-check form-check-custom form-check-solid mb-3">
                                                            <input class="form-check-input" type="checkbox" name="benefits[]" value="health_insurance" id="health_insurance">
                                                            <label class="form-check-label" for="health_insurance">
                                                                Health Insurance
                                                            </label>
                                                        </div>
                                                        <div class="form-check form-check-custom form-check-solid mb-3">
                                                            <input class="form-check-input" type="checkbox" name="benefits[]" value="transport_allowance" id="transport_allowance">
                                                            <label class="form-check-label" for="transport_allowance">
                                                                Transport Allowance
                                                            </label>
                                                        </div>
                                                        <div class="form-check form-check-custom form-check-solid mb-3">
                                                            <input class="form-check-input" type="checkbox" name="benefits[]" value="phone_allowance" id="phone_allowance">
                                                            <label class="form-check-label" for="phone_allowance">
                                                                Phone Allowance
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="col-6">
                                                        <div class="form-check form-check-custom form-check-solid mb-3">
                                                            <input class="form-check-input" type="checkbox" name="benefits[]" value="pension" id="pension">
                                                            <label class="form-check-label" for="pension">
                                                                Pension/Retirement Plan
                                                            </label>
                                                        </div>
                                                        <div class="form-check form-check-custom form-check-solid mb-3">
                                                            <input class="form-check-input" type="checkbox" name="benefits[]" value="paid_leave" id="paid_leave">
                                                            <label class="form-check-label" for="paid_leave">
                                                                Paid Leave
                                                            </label>
                                                        </div>
                                                        <div class="form-check form-check-custom form-check-solid mb-3">
                                                            <input class="form-check-input" type="checkbox" name="benefits[]" value="training" id="training">
                                                            <label class="form-check-label" for="training">
                                                                Professional Training
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!--end::Employee Benefits-->

                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Monthly Sales Target</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="sales_target" class="form-control form-control-solid" placeholder="0.00" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Employee/Agency Compensation-->
                                    
                                    <!--begin::Contractor Compensation-->
                                    <div id="compensation_contractor" style="display: none;">
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Hourly Rate</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="hourly_rate" class="form-control form-control-solid" placeholder="0.00" step="0.01">
                                                    <span class="input-group-text">/hr</span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Project Fee (Fixed)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="project_fee" class="form-control form-control-solid" placeholder="0.00" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Commission Rate (%)</label>
                                                <div class="input-group">
                                                    <input type="number" name="commission_rate_contractor" class="form-control form-control-solid" placeholder="0" min="0" max="100" step="0.1">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                            
                                            <div class="col-6 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Commission Type</label>
                                                <select name="commission_type_contractor" class="form-select form-select-solid" data-control="select2" data-placeholder="Select type">
                                                    <option></option>
                                                    <option value="sales_value">Based on Sales Value</option>
                                                    <option value="net_profit">Based on Net Profit</option>
                                                    <option value="flat_rate">Flat Rate per Deal</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Payment Frequency</label>
                                            <select name="payment_frequency_contractor" class="form-select form-select-solid" data-control="select2" data-placeholder="Select frequency">
                                                <option></option>
                                                <option value="upon_completion">Upon Project Completion</option>
                                                <option value="weekly">Weekly</option>
                                                <option value="bi_weekly">Bi-Weekly</option>
                                                <option value="monthly">Monthly</option>
                                                <option value="milestone">Milestone-based</option>
                                            </select>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-12 fv-row mb-7">
                                                <label class="fs-6 fw-semibold mb-2">Monthly Sales Target (Optional)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="number" name="sales_target_contractor" class="form-control form-control-solid" placeholder="0.00" step="0.01">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!--end::Contractor Compensation-->

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Bank Account Number</label>
                                        <input type="text" name="bank_account" class="form-control form-control-solid" placeholder="Enter account number">
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Compensation Notes</label>
                                        <textarea name="compensation_notes" class="form-control form-control-solid" rows="3" placeholder="Additional compensation details or special arrangements"></textarea>
                                    </div>
                                </div>
                            </div>
                            <!--end::Step 3-->

                            <!--begin::Step 4 - Additional Information-->
                            <div data-kt-stepper-element="content">
                                <div class="w-100 agent-step-content">
                                    <div class="text-center mb-8">
                                        <h3 class="fw-bolder text-dark mb-3">Additional Information</h3>
                                        <p class="text-muted fs-6">Documents, certifications, and other details</p>
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Upload Profile Photo</label>
                                        <input type="file" name="profile_photo" class="form-control form-control-solid" accept="image/*">
                                        <div class="form-text">Accepted formats: JPG, PNG. Max size: 2MB</div>
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Upload Resume/CV</label>
                                        <input type="file" name="resume" class="form-control form-control-solid" accept=".pdf,.doc,.docx">
                                        <div class="form-text">Accepted formats: PDF, DOC, DOCX. Max size: 5MB</div>
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Certifications & Licenses</label>
                                        <textarea name="certifications" class="form-control form-control-solid" rows="3" placeholder="Real estate licenses, certifications, professional memberships, etc."></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Years of Experience</label>
                                            <input type="number" name="years_experience" class="form-control form-control-solid" placeholder="0" min="0" step="1">
                                        </div>
                                        
                                        <div class="col-6 fv-row mb-7">
                                            <label class="fs-6 fw-semibold mb-2">Previous Employer</label>
                                            <input type="text" name="previous_employer" class="form-control form-control-solid" placeholder="Enter previous employer">
                                        </div>
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">LinkedIn Profile</label>
                                        <input type="url" name="linkedin_profile" class="form-control form-control-solid" placeholder="https://linkedin.com/in/...">
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-4">System Access Permissions</label>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="form-check form-check-custom form-check-solid mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="view_leads" id="view_leads" checked>
                                                    <label class="form-check-label" for="view_leads">
                                                        View Leads
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-custom form-check-solid mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_leads" id="manage_leads" checked>
                                                    <label class="form-check-label" for="manage_leads">
                                                        Manage Leads
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-custom form-check-solid mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="view_properties" id="view_properties" checked>
                                                    <label class="form-check-label" for="view_properties">
                                                        View Properties
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="form-check form-check-custom form-check-solid mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="create_deals" id="create_deals" checked>
                                                    <label class="form-check-label" for="create_deals">
                                                        Create Deals
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-custom form-check-solid mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="view_reports" id="view_reports">
                                                    <label class="form-check-label" for="view_reports">
                                                        View Reports
                                                    </label>
                                                </div>
                                                <div class="form-check form-check-custom form-check-solid mb-3">
                                                    <input class="form-check-input" type="checkbox" name="permissions[]" value="manage_team" id="manage_team">
                                                    <label class="form-check-label" for="manage_team">
                                                        Manage Team
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="fv-row mb-7">
                                        <label class="fs-6 fw-semibold mb-2">Reference Contacts</label>
                                        <textarea name="references" class="form-control form-control-solid" rows="3" placeholder="Name, contact info, and relationship of professional references"></textarea>
                                    </div>

                                    <div class="fv-row">
                                        <label class="fs-6 fw-semibold mb-2">Additional Notes</label>
                                        <textarea name="notes" class="form-control form-control-solid" rows="3" placeholder="Any additional information about the agent"></textarea>
                                    </div>
                                </div>
                            </div>
                            <!--end::Step 4-->
                            
                            <!--begin::Actions-->
                            <div class="d-flex flex-stack pt-8 agent-onboard-actions">
                                <!--begin::Wrapper-->
                                <div class="me-2">
                                    <button type="button" class="btn btn-lg btn-light-primary me-3" data-kt-stepper-action="previous">
                                    <!--begin::Svg Icon | path: icons/duotune/arrows/arr063.svg-->
                                    <span class="svg-icon svg-icon-3 me-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <rect opacity="0.5" x="6" y="11" width="13" height="2" rx="1" fill="black" />
                                            <path d="M8.56569 11.4343L12.75 7.25C13.1642 6.83579 13.1642 6.16421 12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75L5.70711 11.2929C5.31658 11.6834 5.31658 12.3166 5.70711 12.7071L11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25C13.1642 17.8358 13.1642 17.1642 12.75 16.75L8.56569 12.5657C8.25327 12.2533 8.25327 11.7467 8.56569 11.4343Z" fill="black" />
                                        </svg>
                                    </span>
                                    <!--end::Svg Icon-->Back</button>
                                </div>
                                <!--end::Wrapper-->
                                <!--begin::Wrapper-->
                                <div>
                                    <button type="button" class="btn btn-lg btn-primary" data-kt-stepper-action="submit" id="kt_modal_add_agent_submit_button">
                                        <span class="indicator-label">Add Agent
                                        <!--begin::Svg Icon | path: icons/duotune/arrows/arr064.svg-->
                                        <span class="svg-icon svg-icon-3 ms-2 me-0">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                                <rect opacity="0.5" x="18" y="13" width="13" height="2" rx="1" transform="rotate(-180 18 13)" fill="black" />
                                                <path d="M15.4343 12.5657L11.25 16.75C10.8358 17.1642 10.8358 17.8358 11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25L18.2929 12.7071C18.6834 12.3166 18.6834 11.6834 18.2929 11.2929L12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75C10.8358 6.16421 10.8358 6.83579 11.25 7.25L15.4343 11.4343C15.7467 11.7467 15.7467 12.2533 15.4343 12.5657Z" fill="black" />
                                            </svg>
                                        </span>
                                        <!--end::Svg Icon--></span>
                                        <span class="indicator-progress">Please wait...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                                    </button>
                                    <button type="button" class="btn btn-lg btn-primary" data-kt-stepper-action="next">Continue
                                    <!--begin::Svg Icon | path: icons/duotune/arrows/arr064.svg-->
                                    <span class="svg-icon svg-icon-3 ms-1 me-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <rect opacity="0.5" x="18" y="13" width="13" height="2" rx="1" transform="rotate(-180 18 13)" fill="black" />
                                            <path d="M15.4343 12.5657L11.25 16.75C10.8358 17.1642 10.8358 17.8358 11.25 18.25C11.6642 18.6642 12.3358 18.6642 12.75 18.25L18.2929 12.7071C18.6834 12.3166 18.6834 11.6834 18.2929 11.2929L12.75 5.75C12.3358 5.33579 11.6642 5.33579 11.25 5.75C10.8358 6.16421 10.8358 6.83579 11.25 7.25L15.4343 11.4343C15.7467 11.7467 15.7467 12.2533 15.4343 12.5657Z" fill="black" />
                                        </svg>
                                    </span>
                                    <!--end::Svg Icon-->
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
