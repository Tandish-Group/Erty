{{-- Payment Methods Section --}}
<div class="card mb-5 mb-xl-10" id="kt_payment_methods_card">
    <!--begin::Card header-->
    <div class="card-header border-0 cursor-pointer d-flex flex-row justify-content-between" role="button"
         data-bs-toggle="collapse" data-bs-target="#kt_payment_methods_body"
         aria-expanded="true" aria-controls="kt_payment_methods_body">
        <div class="card-title m-0">
            <h3 class="fw-bolder m-0">Payment Methods</h3>
        </div>
    </div>
    <!--end::Card header-->

    <!--begin::Collapse-->
    <div id="kt_payment_methods_body" class="collapse show">
        <div class="card-body border-top p-9">

            <!--begin::Notice-->
            <p class="text-muted fs-6 mb-7">
                Define the payment methods you accept. These will be shared with your tenants on invoices and billing communications.
                Mark one as <strong>Primary</strong> to display it prominently.
            </p>
            <!--end::Notice-->

            <!--begin::Methods List-->
            <div id="payment_methods_list">
                {{-- Skeleton shown while loading --}}
                <div id="pm_loading" class="text-center py-10">
                    <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                    <span class="text-muted fs-7">Loading payment methods…</span>
                </div>
                {{-- Empty state --}}
                <div id="pm_empty" class="d-none text-center py-10">
                    <i class="fas fa-credit-card fs-3x text-muted mb-4"></i>
                    <p class="text-muted fw-semibold">No payment methods added yet.</p>
                    <button type="button" class="btn btn-sm btn-primary" id="btn_add_pm_empty">
                        <i class="fas fa-plus me-1"></i> Add First Method
                    </button>
                </div>
                {{-- Table --}}
                <div id="pm_table_wrapper" class="d-none">
                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                        <thead>
                            <tr class="fw-bolder text-muted bg-light">
                                <th class="ps-4 min-w-200px rounded-start">Name / Method</th>
                                <th class="min-w-200px">Details</th>
                                <th class="min-w-100px text-center">Status</th>
                                <th class="min-w-120px text-end rounded-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pm_table_body"></tbody>
                    </table>
                </div>
            </div>
            <!--end::Methods List-->

        </div>
    </div>
    <!--end::Collapse-->
</div>

<!--begin::Add / Edit Payment Method Modal-->
<div class="modal fade" id="kt_modal_payment_method" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px">
        <div class="modal-content">
            <!--begin::Modal header-->
            <div class="modal-header">
                <h2 class="fw-bolder" id="pm_modal_title">Add Payment Method</h2>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <!--end::Modal header-->

            <!--begin::Modal body-->
            <div class="modal-body px-10 py-8">
                <input type="hidden" id="pm_editing_id">

                <!--begin::Method Type-->
                <div class="mb-7">
                    <label class="form-label required fw-bold fs-6">Method Type</label>
                    <select id="pm_method_type" class="form-select form-select-solid">
                        <option value="">— select type —</option>
                        <option value="mpesa_paybill">M-Pesa Paybill</option>
                        <option value="mpesa_till">M-Pesa Till</option>
                        <option value="mpesa_send_money">M-Pesa Send Money</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                    </select>
                    <div class="text-danger mt-1 d-none" id="pm_type_error">Please select a method type.</div>
                </div>
                <!--end::Method Type-->

                <!--begin::Label-->
                <div class="mb-7">
                    <label class="form-label required fw-bold fs-6">Display Name</label>
                    <input type="text" id="pm_label" class="form-control form-control-solid" placeholder="e.g. KCB Main Account, M-Pesa Business">
                    <div class="form-text">This name is shown to your tenants on invoices.</div>
                    <div class="text-danger mt-1 d-none" id="pm_label_error">Please enter a display name.</div>
                </div>
                <!--end::Label-->

                <!--begin::Dynamic fields-->
                <div id="pm_fields_wrapper">

                    {{-- M-Pesa Paybill --}}
                    <div id="pm_fields_mpesa_paybill" class="pm-type-fields d-none">
                        <div class="row g-6 mb-6">
                            <div class="col-md-6">
                                <label class="form-label required fw-bold fs-6">Paybill Number</label>
                                <input type="text" id="pm_paybill_number" class="form-control form-control-solid" placeholder="e.g. 522522">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-6">Account Number Format</label>
                                <input type="text" id="pm_paybill_account_format" class="form-control form-control-solid" placeholder="e.g. Tenant Ref, Phone No.">
                                <div class="form-text">Instructions for what tenants should enter as account number.</div>
                            </div>
                        </div>
                    </div>

                    {{-- M-Pesa Till --}}
                    <div id="pm_fields_mpesa_till" class="pm-type-fields d-none">
                        <div class="row g-6 mb-6">
                            <div class="col-md-6">
                                <label class="form-label required fw-bold fs-6">Till Number</label>
                                <input type="text" id="pm_till_number" class="form-control form-control-solid" placeholder="e.g. 123456">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-6">Account Name</label>
                                <input type="text" id="pm_till_account_name" class="form-control form-control-solid" placeholder="Business name on M-Pesa">
                            </div>
                        </div>
                    </div>

                    {{-- M-Pesa Send Money --}}
                    <div id="pm_fields_mpesa_send_money" class="pm-type-fields d-none">
                        <div class="row g-6 mb-6">
                            <div class="col-md-6">
                                <label class="form-label required fw-bold fs-6">Phone Number</label>
                                <input type="text" id="pm_sendmoney_phone" class="form-control form-control-solid" placeholder="e.g. 0712 345 678">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-6">Account Name</label>
                                <input type="text" id="pm_sendmoney_name" class="form-control form-control-solid" placeholder="Name registered on M-Pesa">
                            </div>
                        </div>
                    </div>

                    {{-- Bank Transfer --}}
                    <div id="pm_fields_bank_transfer" class="pm-type-fields d-none">
                        <div class="row g-6 mb-6">
                            <div class="col-md-6">
                                <label class="form-label required fw-bold fs-6">Bank Name</label>
                                <input type="text" id="pm_bank_name" class="form-control form-control-solid" placeholder="e.g. KCB, Equity">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label required fw-bold fs-6">Account Name</label>
                                <input type="text" id="pm_bank_account_name" class="form-control form-control-solid" placeholder="Name on the bank account">
                            </div>
                        </div>
                        <div class="row g-6 mb-6">
                            <div class="col-md-6">
                                <label class="form-label required fw-bold fs-6">Account Number</label>
                                <input type="text" id="pm_bank_account_number" class="form-control form-control-solid" placeholder="Bank account number">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold fs-6">Branch</label>
                                <input type="text" id="pm_bank_branch" class="form-control form-control-solid" placeholder="Branch name / code">
                            </div>
                        </div>
                        <div class="mb-6">
                            <label class="form-label fw-bold fs-6">SWIFT / BIC Code <span class="text-muted">(optional, for international)</span></label>
                            <input type="text" id="pm_bank_swift" class="form-control form-control-solid" placeholder="e.g. KCBLKENX">
                        </div>
                    </div>

                    {{-- Cash --}}
                    <div id="pm_fields_cash" class="pm-type-fields d-none">
                        <div class="mb-6">
                            <label class="form-label fw-bold fs-6">Payment Instructions</label>
                            <textarea id="pm_cash_instructions" class="form-control form-control-solid" rows="3"
                                placeholder="e.g. Pay at our office at ABC Building, 1st floor, Mon–Fri 8am–5pm"></textarea>
                        </div>
                    </div>

                </div>
                <!--end::Dynamic fields-->

                <!--begin::Primary checkbox-->
                <div class="separator separator-dashed my-6"></div>
                <div class="form-check form-check-custom form-check-solid">
                    <input class="form-check-input" type="checkbox" id="pm_is_primary">
                    <label class="form-check-label fw-semibold" for="pm_is_primary">
                        Set as primary payment method
                        <span class="text-muted d-block fs-7 mt-1">Primary method is displayed first on invoices sent to tenants.</span>
                    </label>
                </div>
                <!--end::Primary checkbox-->

            </div>
            <!--end::Modal body-->

            <!--begin::Modal footer-->
            <div class="modal-footer flex-end">
                <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="pm_save_btn">
                    <span class="indicator-label">Save Method</span>
                    <span class="indicator-progress d-none">
                        <span class="spinner-border spinner-border-sm align-middle me-2"></span>Saving…
                    </span>
                </button>
            </div>
            <!--end::Modal footer-->

        </div>
    </div>
</div>
<!--end::Modal-->
