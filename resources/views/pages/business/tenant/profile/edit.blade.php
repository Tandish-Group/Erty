@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Edit Tenant Profile</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    <a href="/tenant_management" class="text-muted">Tenant Management</a>
                </li>
                <li class="breadcrumb-item text-dark">Edit Profile</li>
            </ul>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="/tenant_management/tenant/{{ $tenant->tenant_ref }}/view" class="btn btn-sm btn-light">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
        </div>
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <div class="row">
            <div class="col-xxl-12">
                <!--begin::Card-->
                <div class="card">
                    <!--begin::Card header-->
                    <div class="card-header d-flex flex-row justify-content-between border-0 pt-6">
                        <div class="card-title">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-user-edit fs-2 text-primary me-3"></i>
                                <div>
                                    <h3 class="fw-bolder m-0">Edit Tenant Profile</h3>
                                    <span class="text-muted fs-7">Update tenant information and contact details</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-toolbar">
                            <span class="badge badge-light-{{ $tenant->tenant_type === 'Business' ? 'success' : 'primary' }} fs-6">
                                <i class="fas fa-{{ $tenant->tenant_type === 'Business' ? 'briefcase' : 'user' }} me-1"></i>
                                {{ $tenant->tenant_type }} Profile
                            </span>
                        </div>
                    </div>
                    <!--end::Card header-->

                    <!--begin::Card body-->
                    <div class="card-body pt-8">
                        <form id="kt_tenant_profile_edit_form" class="form" method="POST" action="{{ route('tenant_management.update_tenant_profile', $tenant->tenant_ref) }}">
                            @csrf
                            @method('PUT')

                            <!--begin::Basic Information-->
                            <div class="card mb-8">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-info-circle me-2 text-primary"></i>Basic Information
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Tenant Reference</label>
                                            <input type="text" class="form-control form-control-solid" value="{{ $tenant->tenant_ref }}" readonly />
                                            <div class="form-text">Unique identifier (cannot be changed)</div>
                                        </div>

                                        <div class="col-md-4 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Profile Type</label>
                                            <input type="text" class="form-control form-control-solid" value="{{ $tenant->tenant_type }}" readonly />
                                            <div class="form-text">Profile type (cannot be changed)</div>
                                        </div>

                                        <div class="col-md-4 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Profile Status</label>
                                            <select name="profile_status" class="form-select form-select-solid" data-control="select2" required>
                                                <option value="Active" {{ $tenant->profile_status === 'Active' ? 'selected' : '' }}>Active</option>
                                                <option value="Inactive" {{ $tenant->profile_status === 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                                <option value="Blacklisted" {{ $tenant->profile_status === 'Blacklisted' ? 'selected' : '' }}>Blacklisted</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Basic Information-->

                            <!--begin::Individual Information-->
                            @if($tenant->tenant_type === 'Individual')
                            <div class="card mb-8">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-user me-2 text-primary"></i>Individual Information
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Full Name</label>
                                            <input type="text" name="tenant_name" class="form-control form-control-solid" 
                                                   value="{{ $tenant->tenant_name }}" placeholder="Enter full name" required />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Email Address</label>
                                            <input type="email" name="tenant_email" class="form-control form-control-solid" 
                                                   value="{{ $tenant->tenant_email }}" placeholder="Enter email address" required />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Phone Number</label>
                                            <input type="text" name="tenant_phone" class="form-control form-control-solid" 
                                                   value="{{ $tenant->tenant_phone }}" placeholder="Enter phone number" required />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">ID/Passport Number</label>
                                            <input type="text" name="tenant_id_number" class="form-control form-control-solid" 
                                                   value="{{ $tenant->tenant_id_number }}" placeholder="Enter ID or passport number" />
                                        </div>

                                        <div class="col-md-4 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Date of Birth</label>
                                            <input type="date" name="date_of_birth" class="form-control form-control-solid" 
                                                   value="{{ $tenant->date_of_birth?->format('Y-m-d') }}" />
                                        </div>

                                        <div class="col-md-4 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Gender</label>
                                            <select name="gender" class="form-select form-select-solid" data-control="select2">
                                                <option value="">Select gender</option>
                                                <option value="Male" {{ $tenant->gender === 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ $tenant->gender === 'Female' ? 'selected' : '' }}>Female</option>
                                                <option value="Other" {{ $tenant->gender === 'Other' ? 'selected' : '' }}>Other</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Nationality</label>
                                            <input type="text" name="nationality" class="form-control form-control-solid" 
                                                   value="{{ $tenant->nationality }}" placeholder="Enter nationality" />
                                        </div>

                                        <div class="col-md-12 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Residential Address</label>
                                            <textarea name="address" class="form-control form-control-solid" rows="3" 
                                                      placeholder="Enter residential address">{{ $tenant->address }}</textarea>
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Occupation</label>
                                            <input type="text" name="occupation" class="form-control form-control-solid" 
                                                   value="{{ $tenant->occupation }}" placeholder="Enter occupation" />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Employer</label>
                                            <input type="text" name="employer" class="form-control form-control-solid" 
                                                   value="{{ $tenant->employer }}" placeholder="Enter employer name" />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Monthly Income (KES)</label>
                                            <input type="number" name="monthly_income" class="form-control form-control-solid" 
                                                   value="{{ $tenant->monthly_income }}" placeholder="0.00" step="0.01" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <!--end::Individual Information-->

                            <!--begin::Business Information-->
                            @if($tenant->tenant_type === 'Business')
                            <div class="card mb-8">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-building me-2 text-primary"></i>Business Information
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Business Name</label>
                                            <input type="text" name="business_name" class="form-control form-control-solid" 
                                                   value="{{ $tenant->business_name }}" placeholder="Enter business name" required />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Business Registration Number</label>
                                            <input type="text" name="business_registration" class="form-control form-control-solid" 
                                                   value="{{ $tenant->business_registration }}" placeholder="Enter registration number" />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Contact Person</label>
                                            <input type="text" name="business_contact_person" class="form-control form-control-solid" 
                                                   value="{{ $tenant->business_contact_person }}" placeholder="Enter contact person name" required />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Business Email</label>
                                            <input type="email" name="business_email" class="form-control form-control-solid" 
                                                   value="{{ $tenant->business_email }}" placeholder="Enter business email" required />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="required fw-bold fs-6 mb-2">Business Phone</label>
                                            <input type="text" name="business_phone" class="form-control form-control-solid" 
                                                   value="{{ $tenant->business_phone }}" placeholder="Enter business phone" required />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Business Type</label>
                                            <input type="text" name="business_type" class="form-control form-control-solid" 
                                                   value="{{ $tenant->business_type }}" placeholder="e.g., Restaurant, Retail, Office" />
                                        </div>

                                        <div class="col-md-12 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Business Address</label>
                                            <textarea name="business_address" class="form-control form-control-solid" rows="3" 
                                                      placeholder="Enter business address">{{ $tenant->business_address }}</textarea>
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Tax ID Number</label>
                                            <input type="text" name="tax_id_number" class="form-control form-control-solid" 
                                                   value="{{ $tenant->tax_id_number }}" placeholder="Enter tax ID/PIN" />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Years in Business</label>
                                            <input type="number" name="years_in_business" class="form-control form-control-solid" 
                                                   value="{{ $tenant->years_in_business }}" placeholder="0" min="0" />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Annual Revenue (KES)</label>
                                            <input type="number" name="annual_revenue" class="form-control form-control-solid" 
                                                   value="{{ $tenant->annual_revenue }}" placeholder="0.00" step="0.01" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <!--end::Business Information-->

                            <!--begin::Emergency Contact-->
                            <div class="card mb-8">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-phone-alt me-2 text-danger"></i>Emergency Contact
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Contact Name</label>
                                            <input type="text" name="emergency_contact_name" class="form-control form-control-solid" 
                                                   value="{{ $tenant->emergency_contact_name }}" placeholder="Enter emergency contact name" />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Contact Phone</label>
                                            <input type="text" name="emergency_contact_phone" class="form-control form-control-solid" 
                                                   value="{{ $tenant->emergency_contact_phone }}" placeholder="Enter emergency contact phone" />
                                        </div>

                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Relationship</label>
                                            <input type="text" name="emergency_contact_relationship" class="form-control form-control-solid" 
                                                   value="{{ $tenant->emergency_contact_relationship }}" placeholder="e.g., Spouse, Parent, Friend" />
                                        </div>

                                        <div class="col-md-12 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Emergency Contact Address</label>
                                            <textarea name="emergency_contact_address" class="form-control form-control-solid" rows="3" 
                                                      placeholder="Enter emergency contact address">{{ $tenant->emergency_contact_address }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Emergency Contact-->

                            <!--begin::Additional Information-->
                            <div class="card mb-8">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-clipboard me-2 text-primary"></i>Additional Information
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-12 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Additional Notes</label>
                                            <textarea name="additional_notes" class="form-control form-control-solid" rows="5" 
                                                      placeholder="Any additional notes, comments, or important information about this tenant">{{ $tenant->additional_notes }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--end::Additional Information-->

                            <!--begin::Blacklist Section (Only if blacklisted)-->
                            @if($tenant->profile_status === 'Blacklisted')
                            <div class="card mb-8 border-danger">
                                <div class="card-header bg-danger">
                                    <h3 class="card-title text-white">
                                        <i class="fas fa-ban me-2"></i>Blacklist Information
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-danger d-flex align-items-center mb-5">
                                        <i class="fas fa-exclamation-triangle fs-2x me-4"></i>
                                        <div>
                                            <strong>This profile is currently blacklisted.</strong> Please provide a reason and date.
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Blacklisted Date</label>
                                            <input type="date" name="blacklisted_date" class="form-control form-control-solid" 
                                                   value="{{ $tenant->blacklisted_date?->format('Y-m-d') }}" />
                                        </div>

                                        <div class="col-12 fv-row mb-7">
                                            <label class="fw-bold fs-6 mb-2">Blacklist Reason</label>
                                            <textarea name="blacklist_reason" class="form-control form-control-solid" rows="4" 
                                                      placeholder="Enter the reason for blacklisting this tenant">{{ $tenant->blacklist_reason }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endif
                            <!--end::Blacklist Section-->

                            <!--begin::Actions-->
                            <div class="d-flex justify-content-end mt-10">
                                <a href="/tenant_management" class="btn btn-light me-3">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" id="kt_tenant_profile_edit_submit">
                                    <span class="indicator-label">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </span>
                                    <span class="indicator-progress">Please wait...
                                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                    </span>
                                </button>
                            </div>
                            <!--end::Actions-->
                        </form>
                    </div>
                    <!--end::Card body-->
                </div>
                <!--end::Card-->
            </div>
        </div>
    </div>
</div>

@endsection