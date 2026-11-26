<!--begin::Modal - Give Notice-->
<div class="modal fade" id="kt_modal_give_notice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <!--begin::Modal header-->
            <div class="modal-header">
                <h2 class="fw-bolder">Give Notice to Tenant</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <!--end::Modal header-->

            <form id="kt_modal_give_notice_form" class="form">

                <!--begin::Modal body-->
                <div class="modal-body scroll h-450px mx-5 mx-xl-15 my-7">
                    <!--begin::Form-->
                
                    <input type="hidden" id="notice_tenant_id" name="tenant_id" />

                    <div id="notice_type_description" class="alert alert-info d-flex align-items-center p-5 d-none">
                        <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <span class="fw-bold fs-6"></span>
                        </div>
                    </div>
                    
                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Notice Type</label>
                        <select name="notice_type" class="form-select form-select-solid" data-hide-search="true" data-allow-clear="true" data-control="select2" data-placeholder="Select notice type">
                            <option></option>
                            <option value="lease_termination">Lease Termination</option>
                            <option value="rent_increase">Rent Increase</option>
                            <option value="lease_violation">Lease Violation</option>
                            <option value="property_inspection">Property Inspection</option>
                            <option value="maintenance_notice">Maintenance Notice</option>
                            <option value="eviction_notice">Eviction Notice</option>
                            <option value="lease_renewal">Lease Renewal</option>
                            <option value="notice_to_enter">Notice to Enter</option>
                            <option value="non_renewal_notice">Non-Renewal Notice</option>
                            <option value="change_in_terms">Change in Terms Notice</option>
                            <option value="unconditional_quit">Unconditional Quit Notice</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="required fs-6 fw-bold form-label mb-2">Notice Date</label>
                                <input type="date" name="notice_date" class="form-control form-control-solid" value="{{ date('Y-m-d') }}" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="required fs-6 fw-bold form-label mb-2">Effective Date</label>
                                <input type="date" name="effective_date" class="form-control form-control-solid" />
                            </div>
                        </div>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Notice Period (Days)</label>
                        <input type="number" name="notice_period" class="form-control form-control-solid" placeholder="30" min="1" />
                        <div class="form-text">Standard notice period (e.g., 30 days for termination)</div>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Subject</label>
                        <input type="text" name="notice_subject" class="form-control form-control-solid" placeholder="Enter notice subject" />
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Notice Details</label>
                        <textarea name="notice_details" class="form-control form-control-solid" rows="5" placeholder="Enter detailed notice information..."></textarea>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Delivery Method</label>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="delivery_method[]" value="email" id="delivery_email" checked>
                                    <label class="form-check-label" for="delivery_email">
                                        Email
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="delivery_method[]" value="sms" id="delivery_sms">
                                    <label class="form-check-label" for="delivery_sms">
                                        SMS
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="delivery_method[]" value="physical" id="delivery_physical">
                                    <label class="form-check-label" for="delivery_physical">
                                        Physical Copy
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Input group-->
                </div>
                <!--end::Modal body-->

                <div class="modal-footer">
                    <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-kt-notice-modal-action="submit">
                        <span class="indicator-label">Send Notice</span>
                        <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Give Notice-->