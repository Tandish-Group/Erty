@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Create Lease Agreement</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    <a href="/tenant_management" class="text-muted">Tenant Management</a>
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="/tenant_management/tenancy/{{ $tenantManagement->tenancy_ref }}/view" class="text-muted">{{ $tenantManagement->tenancy_ref }}</a>
                </li>
                <li class="breadcrumb-item text-dark">Create Lease</li>
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

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        
        {{-- Tenant Information Card --}}
        <div class="card mb-5">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="symbol symbol-circle symbol-50px overflow-hidden me-4">
                        <div class="symbol-label fs-2 {{ $tenantManagement->tenantProfile->tenant_type === 'Business' ? 'bg-light-success text-success' : 'bg-light-primary text-primary' }}">
                            <i class="fas fa-{{ $tenantManagement->tenantProfile->tenant_type === 'Business' ? 'building' : 'user' }}"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <h3 class="text-gray-800 mb-1">{{ $tenantManagement->tenantProfile->display_name }}</h3>
                        <div class="text-muted fs-6">
                            <span class="badge badge-light-primary me-2">{{ $tenantManagement->tenancy_ref }}</span>
                            <span>{{ $tenantManagement->tenantProfile->primary_email }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Lease Form --}}
        <form id="lease_form" class="form">
            @csrf
            <input type="hidden" name="tenant_management_id" value="{{ $tenantManagement->id }}">
            
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Lease Agreement Details</h3>
                </div>
                <div class="card-body">
                    
                    {{-- Lease Period --}}
                    <div class="row mb-8">
                        <div class="col-12">
                            <h4 class="text-gray-700 mb-5">Lease Period</h4>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Lease Start Date</label>
                            <input type="date" class="form-control" name="lease_start_date" value="{{ $tenantManagement->lease_start_date ? $tenantManagement->lease_start_date->format('Y-m-d') : '' }}" required>
                        </div>
                        <div class="col-md-6 mb-5" id="lease_end_date_field">
                            <label class="form-label" id="lease_end_date_label">Lease End Date</label>
                            <input type="date" class="form-control" name="lease_end_date" id="lease_end_date" value="{{ $tenantManagement->lease_end_date ? $tenantManagement->lease_end_date->format('Y-m-d') : '' }}">
                            <div class="form-text text-muted" id="lease_end_date_hint" style="display:none;">Leave blank for open-ended month-to-month lease</div>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Lease Type</label>
                            <select class="form-select" name="lease_type" required>
                                <option value="">Select Lease Type</option>
                                <option value="fixed-term">Fixed Term</option>
                                <option value="month-to-month">Month-to-Month</option>
                                <option value="periodic">Periodic</option>
                            </select>
                        </div>
                    </div>

                    <div class="separator separator-dashed my-8"></div>

                    {{-- Financial Terms --}}
                    <div class="row mb-8">
                        <div class="col-12">
                            <h4 class="text-gray-700 mb-5">Financial Terms</h4>
                        </div>
                        <div class="col-md-4 mb-5">
                            <label class="form-label required">Currency</label>
                            <select class="form-select" name="currency" required>
                                <option value="KES" selected>KES - Kenyan Shilling</option>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-5">
                            <label class="form-label required">Rent Amount</label>
                            <input type="number" class="form-control" name="rent_amount" value="{{ $tenantManagement->monthly_payment }}" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-4 mb-5">
                            <label class="form-label">Security Deposit</label>
                            <input type="number" class="form-control" name="security_deposit" value="{{ $tenantManagement->security_deposit }}" step="0.01" min="0">
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Payment Frequency</label>
                            <select class="form-select" name="payment_frequency" required>
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="semi-annually">Semi-Annually</option>
                                <option value="annually">Annually</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label required">Payment Day of Month</label>
                            <input type="number" class="form-control" name="payment_day_of_month" value="1" min="1" max="31" required>
                            <div class="form-text">Day of the month payment is due (1-31)</div>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Late Fee Amount</label>
                            <input type="number" class="form-control" name="late_fee_amount" step="0.01" min="0">
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Grace Period (Days)</label>
                            <input type="number" class="form-control" name="grace_period_days" value="0" min="0">
                        </div>
                    </div>

                    <div class="separator separator-dashed my-8"></div>

                    {{-- Renewal Terms --}}
                    <div class="row mb-8">
                        <div class="col-12">
                            <h4 class="text-gray-700 mb-5">Renewal Terms</h4>
                        </div>
                        <div class="col-md-12 mb-5">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_renewal" value="1" id="auto_renewal">
                                <label class="form-check-label" for="auto_renewal">
                                    Enable Auto-Renewal
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-5" id="renewal_notice_field" style="display: none;">
                            <label class="form-label">Renewal Notice Period (Days)</label>
                            <input type="number" class="form-control" name="renewal_notice_days" min="0">
                            <div class="form-text">Days before lease end to notify about renewal</div>
                        </div>
                        <div class="col-md-6 mb-5" id="renewal_increase_field" style="display: none;">
                            <label class="form-label">Rent Increase Percentage</label>
                            <input type="number" class="form-control" name="renewal_rent_increase_percentage" step="0.01" min="0" max="100">
                            <div class="form-text">Percentage increase upon renewal</div>
                        </div>
                    </div>

                    <div class="separator separator-dashed my-8"></div>

                    {{-- Utilities and Restrictions --}}
                    <div class="row mb-8">
                        <div class="col-12">
                            <h4 class="text-gray-700 mb-5">Utilities & Restrictions</h4>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Utilities Included</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities_included[]" value="water" id="utility_water">
                                <label class="form-check-label" for="utility_water">Water</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities_included[]" value="electricity" id="utility_electricity">
                                <label class="form-check-label" for="utility_electricity">Electricity</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities_included[]" value="gas" id="utility_gas">
                                <label class="form-check-label" for="utility_gas">Gas</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities_included[]" value="internet" id="utility_internet">
                                <label class="form-check-label" for="utility_internet">Internet</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="utilities_included[]" value="cable" id="utility_cable">
                                <label class="form-check-label" for="utility_cable">Cable TV</label>
                            </div>
                        </div>
                        <div class="col-md-6 mb-5">
                            <label class="form-label">Restrictions</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="restrictions[]" value="no_pets" id="restriction_pets">
                                <label class="form-check-label" for="restriction_pets">No Pets</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="restrictions[]" value="no_smoking" id="restriction_smoking">
                                <label class="form-check-label" for="restriction_smoking">No Smoking</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="restrictions[]" value="no_subletting" id="restriction_subletting">
                                <label class="form-check-label" for="restriction_subletting">No Subletting</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="restrictions[]" value="no_commercial_use" id="restriction_commercial">
                                <label class="form-check-label" for="restriction_commercial">No Commercial Use</label>
                            </div>
                        </div>
                    </div>

                    <div class="separator separator-dashed my-8"></div>

                    {{-- Additional Terms --}}
                    <div class="row mb-5">
                        <div class="col-12">
                            <h4 class="text-gray-700 mb-5">Additional Terms</h4>
                        </div>
                        <div class="col-md-12 mb-5">
                            <label class="form-label">Special Terms</label>
                            <textarea class="form-control" name="special_terms" rows="4" placeholder="Enter any special terms or conditions specific to this lease..."></textarea>
                        </div>
                        <div class="col-md-12 mb-5">
                            <label class="form-label">Terms and Conditions</label>
                            <textarea class="form-control" name="terms_and_conditions" rows="6" placeholder="Enter the standard terms and conditions for this lease agreement..."></textarea>
                        </div>
                    </div>

                </div>
                <div class="card-footer d-flex justify-content-end">
                    <a href="/tenant_management/tenancy/{{ $tenantManagement->tenancy_ref }}/view" class="btn btn-light me-3">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="submit_btn">
                        <span class="indicator-label">Create Lease</span>
                        <span class="indicator-progress" style="display: none;">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Toggle end date required state based on lease type
    function updateLeaseEndDate() {
        const leaseType = $('select[name="lease_type"]').val();
        const isOpenEnded = leaseType === 'month-to-month' || leaseType === 'periodic';
        $('#lease_end_date').prop('required', !isOpenEnded);
        $('#lease_end_date_label').toggleClass('required', !isOpenEnded);
        $('#lease_end_date_hint').toggle(isOpenEnded);
    }
    $('select[name="lease_type"]').on('change', updateLeaseEndDate);
    updateLeaseEndDate();

    // Toggle renewal fields
    $('#auto_renewal').on('change', function() {
        if ($(this).is(':checked')) {
            $('#renewal_notice_field, #renewal_increase_field').slideDown();
        } else {
            $('#renewal_notice_field, #renewal_increase_field').slideUp();
            $('input[name="renewal_notice_days"], input[name="renewal_rent_increase_percentage"]').val('');
        }
    });

    // Form submission
    $('#lease_form').on('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = $('#submit_btn');
        const formData = new FormData(this);
        
        // Show loading state
        submitBtn.prop('disabled', true);
        submitBtn.find('.indicator-label').hide();
        submitBtn.find('.indicator-progress').show();
        
        $.ajax({
            url: @json(route('tenant_lease.store')),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.status === 200) {
                    toastr.success(response.message);
                    setTimeout(() => {
                        window.location.href = '/tenant_management/tenancy/{{ $tenantManagement->tenancy_ref }}/view';
                    }, 1500);
                }
            },
            error: function(xhr) {
                const errors = xhr.responseJSON?.errors || {};
                let errorMessage = xhr.responseJSON?.message || 'Failed to create lease';
                
                if (Object.keys(errors).length > 0) {
                    errorMessage += ':<br>';
                    Object.values(errors).forEach(error => {
                        errorMessage += `- ${error[0]}<br>`;
                    });
                }
                
                toastr.error(errorMessage);
            },
            complete: function() {
                submitBtn.prop('disabled', false);
                submitBtn.find('.indicator-label').show();
                submitBtn.find('.indicator-progress').hide();
            }
        });
    });
});
</script>
@endpush
