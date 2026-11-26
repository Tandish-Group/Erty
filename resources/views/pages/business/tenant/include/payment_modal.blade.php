<!--begin::Modal - Record Payment-->
<div class="modal fade" id="kt_modal_record_payment" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <!--begin::Modal header-->
            <div class="modal-header">
                <h2 class="fw-bolder">Record Payment</h2>
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
            <form id="kt_modal_record_payment_form" class="form">
                <!--begin::Modal body-->
                <div class="modal-body scroll h-450px mx-5 mx-xl-15 my-7">
                    <!--begin::Form-->
                    <input type="hidden" id="payment_tenant_id" name="tenant_id" />

                    <!--begin::Payment Details-->
                    <div class="row">
                        <!--begin::Input group-->
                        <div class="col-md-12">
                            <div class="fv-row mb-7">
                                <label class="required fs-6 fw-bold form-label mb-2">Tenancy Invoice</label>
                                <select name="invoice_id" class="form-select form-select-solid" data-control="select2" data-placeholder="Select invoice" data-allow-clear="true">
                                    <option></option>
                                </select>
                            </div>
                        </div>
                        <!--end::Input group-->
                       
                    </div>

                    <div class="row">
                        <!--begin::Input group-->
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="required fs-6 fw-bold form-label mb-2">Payment Date</label>
                                <input type="date" name="payment_date" class="form-control form-control-solid" value="{{ date('Y-m-d') }}" />
                            </div>
                        </div>
                        <!--end::Input group-->

                        <!--begin::Input group-->
                        <div class="col-md-6">
                            <div class="fv-row mb-7">
                                <label class="required fs-6 fw-bold form-label mb-2">Payment Method</label>
                                <select name="payment_method" class="form-select form-select-solid" data-control="select2" data-placeholder="Select method">
                                    <option></option>
                                    <option value="cash">Cash</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="mobile_money">Mobile Money (M-Pesa)</option>
                                    <option value="check">Check</option>
                                    <option value="card">Card Payment</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <!--end::Input group-->
                    </div>

                    <!--begin::Input group-->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="fv-row mb-7">
                                <label class="fs-6 fw-bold form-label mb-2">Transaction Reference</label>
                                <input type="text" name="transaction_reference" class="form-control form-control-solid" placeholder="Enter reference number" />
                                <div class="form-text">M-Pesa code, check number, etc.</div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="fv-row mb-7">
                                <label class="fs-6 fw-bold form-label mb-2">Late Fee (if applicable)</label>
                                <div class="input-group mb-4">
                                    <span class="input-group-text">{{ default_currency() }}</span>
                                    <input type="number" name="late_fee" class="form-control form-control-solid" placeholder="0.00" step="0.01" min="0" />
                                </div>

                                <!-- Late Fee Payment Option - Hidden by default -->
                                <div id="late_fee_payment_option" class="mt-3" style="display: none;">
                                    <div class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="checkbox" name="late_fee_paid_now" id="late_fee_paid_now" value="1" />
                                        <label class="form-check-label fw-bold text-gray-700" for="late_fee_paid_now">
                                            Late fee paid with this payment
                                        </label>
                                    </div>
                                    <div class="form-text text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        If unchecked, late fee will be transferred to next billing cycle
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Payment Status-->
                    <div class="fv-row mb-7">
                        <label class="required fs-6 fw-bold form-label mb-2">Payment Status</label>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="d-flex flex-stack mb-3 cursor-pointer">
                                    <span class="d-flex align-items-center me-2">
                                        <span class="symbol symbol-50px me-6">
                                            <span class="symbol-label bg-light-success">
                                                <i class="fas fa-check text-success fs-2x"></i>
                                            </span>
                                        </span>
                                        <span class="d-flex flex-column">
                                            <span class="fw-bolder fs-6">Paid in Full</span>
                                            <span class="fs-7 text-muted">Complete payment</span>
                                        </span>
                                    </span>
                                    <span class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="radio" name="payment_status" value="paid" checked />
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="d-flex flex-stack mb-3 cursor-pointer">
                                    <span class="d-flex align-items-center me-2">
                                        <span class="symbol symbol-50px me-6">
                                            <span class="symbol-label bg-light-warning">
                                                <i class="fas fa-clock text-warning fs-2x"></i>
                                            </span>
                                        </span>
                                        <span class="d-flex flex-column">
                                            <span class="fw-bolder fs-6">Partial Payment</span>
                                            <span class="fs-7 text-muted">Partial amount</span>
                                        </span>
                                    </span>
                                    <span class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="radio" name="payment_status" value="partial" />
                                    </span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="d-flex flex-stack mb-3 cursor-pointer">
                                    <span class="d-flex align-items-center me-2">
                                        <span class="symbol symbol-50px me-6">
                                            <span class="symbol-label bg-light-info">
                                                <i class="fas fa-hourglass-half text-info fs-2x"></i>
                                            </span>
                                        </span>
                                        <span class="d-flex flex-column">
                                            <span class="fw-bolder fs-6">Pending</span>
                                            <span class="fs-7 text-muted">Awaiting clearance</span>
                                        </span>
                                    </span>
                                    <span class="form-check form-check-custom form-check-solid">
                                        <input class="form-check-input" type="radio" name="payment_status" value="pending" />
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <!--end::Payment Status-->

                    <!--begin::Hidden Full Amount Input-->
                    <input type="hidden" name="amount_paid" id="amount_paid" value="0" />
                    <!--end::Hidden Full Amount Input-->

                    <!--begin::Payment Allocation (shown only for partial payments)-->
                    <div class="fv-row mb-7" id="payment_allocation_section" style="display: none;">
                        <label class="required fs-6 fw-bold form-label mb-2">Payment Allocation</label>
                        <div class="alert alert-info d-flex align-items-center p-3 mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <span>Since this is a partial payment, please specify how much goes to each invoice.</span>
                        </div>
                        
                        <!-- Single Invoice Allocation -->
                        <div id="single_invoice_allocation" style="display: none;">
                            <div class="row">
                                <div class="col-md-12">
                                    <label class="fs-7 fw-bold text-gray-600 mb-2">Payment Amount</label>
                                    <div class="input-group">
                                        {{-- <span class="input-group-text">{{ default_currency() }}</span> --}}
                                        <input type="number" name="single_invoice_amount" class="form-control" placeholder="0.00" step="0.01" min="0" />
                                    </div>
                                    <div class="form-text">
                                        Balance: <span id="single_invoice_balance">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Combined Invoice Allocation -->
                        <div id="combined_invoice_allocation" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label class="fs-7 fw-bold text-gray-600 mb-2">Tenancy Payment</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ default_currency() }}</span>
                                        <input type="number" name="lease_amount" class="form-control allocation-input" placeholder="0.00" step="0.01" min="0" data-invoice-type="lease" />
                                    </div>
                                    <div class="form-text">
                                        Balance: {{ default_currency() }} <span id="lease_balance">0</span>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label class="fs-7 fw-bold text-gray-600 mb-2">Service Charge Payment</label>
                                    <div class="input-group">
                                        <span class="input-group-text">{{ default_currency() }}</span>
                                        <input type="number" name="service_charge_amount" class="form-control allocation-input" placeholder="0.00" step="0.01" min="0" data-invoice-type="service_charge" />
                                    </div>
                                    <div class="form-text">
                                        Balance: {{ default_currency() }} <span id="service_charge_balance">0</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Allocation Summary -->
                            <div class="alert alert-secondary">
                                <div class="row">
                                    <div class="col-md-4">
                                        <small><strong>Total Allocated:</strong></small><br>
                                        <span class="fs-6 fw-bold">{{ default_currency() }} <span id="total_allocated">0</span></span>
                                    </div>
                                    <div class="col-md-4">
                                        <small><strong>Amount Paid:</strong></small><br>
                                        <span class="fs-6 fw-bold">{{ default_currency() }} <span id="total_amount_paid">0</span></span>
                                    </div>
                                    <div class="col-md-4">
                                        <small><strong>Difference:</strong></small><br>
                                        <span class="fs-6 fw-bold" id="allocation_difference">{{ default_currency() }} 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!--end::Payment Allocation-->

                    <!--begin::Input group-->
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Notes</label>
                        <textarea name="payment_notes" class="form-control form-control-solid" rows="3" placeholder="Additional payment notes or comments..."></textarea>
                    </div>
                    <!--end::Input group-->

                    <!--begin::Upload Receipt-->
                    <div class="fv-row mb-7">
                        <label class="fs-6 fw-bold form-label mb-2">Upload Receipt/Proof</label>
                        <input type="file" name="payment_receipt" class="form-control form-control-solid" accept="image/*,.pdf" />
                        <div class="form-text">Upload receipt, screenshot, or payment proof (optional)</div>
                    </div>
                    <!--end::Upload Receipt-->
                </div>
                <!--end::Modal body-->
                <div class="modal-footer">
                    <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" data-kt-payment-modal-action="submit">
                        <span class="indicator-label">Record Payment</span>
                        <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Record Payment-->