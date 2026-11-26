<div class="modal fade" id="kt_modal_end_tenancy" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-900px">
        <div class="modal-content">
            <!--begin::Modal header-->
            <div class="modal-header">
                <h2 class="fw-bolder">End Tenancy</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal" aria-label="Close">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"/>
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"/>
                        </svg>
                    </span>
                </div>
            </div>
            <!--end::Modal header-->

            <!--begin::Modal body-->
            <div class="modal-body scroll h-400px py-10 px-lg-17">
                <!--begin::Form-->
                <form class="form" id="kt_modal_end_tenancy_form">
                    <!--begin::Hidden Fields-->
                    <input type="hidden" name="tenant_id" id="tenancy_tenant_id" />
                    <!--end::Hidden Fields-->

                    <!--begin::Termination Date-->
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-bold form-label mb-2 required">Termination Date</label>
                        <input type="date" 
                               name="termination_date" 
                               class="form-control form-control-solid" 
                               placeholder="Select termination date"
                               required />
                        <div class="form-text">The date when the tenancy officially ends</div>
                    </div>
                    <!--end::Termination Date-->

                    <!--begin::Termination Reason-->
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-bold form-label mb-2 required">Reason for Termination</label>
                        <select name="termination_reason" 
                                class="form-select form-select-solid" 
                                data-control="select2"
                                data-placeholder="Select termination reason"
                                data-allow-clear="true"
                                required>
                            <option></option>
                            <option value="lease_expired">Lease Expired (Natural End)</option>
                            <option value="tenant_left">Tenant Left Voluntarily</option>
                            <option value="mutual_agreement">Mutual Agreement</option>
                            <option value="non_payment">Non-Payment of Rent</option>
                            <option value="lease_violation">Lease Violation</option>
                            <option value="eviction">Eviction</option>
                            <option value="property_renovation">Property Renovation/Demolition</option>
                            <option value="property_sale">Property Sale</option>
                            <option value="business_closure">Business Closure (Commercial)</option>
                            <option value="death">Death of Tenant</option>
                            <option value="other">Other Reason</option>
                        </select>
                        <div class="form-text">Select the primary reason for ending the tenancy</div>
                    </div>
                    <!--end::Termination Reason-->

                    <!--begin::Other Reason (shown when "other" is selected)-->
                    <div class="fv-row mb-8" id="other_reason_section" style="display: none;">
                        <label class="fs-6 fw-bold form-label mb-2 required">Specify Other Reason</label>
                        <input type="text" 
                               name="other_reason" 
                               class="form-control form-control-solid" 
                               placeholder="Please specify the reason" />
                    </div>
                    <!--end::Other Reason-->

                    <!--begin::Notice Period Compliance-->
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-bold form-label mb-2">Notice Period Compliance</label>
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="radio" name="notice_compliance" id="notice_compliance_proper" value="proper_notice" checked />
                                    <span class="form-check-label fw-bold">
                                        Proper Notice Given
                                    </span>
                                </label>
                                <div class="form-text">Required notice period was followed</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="radio" name="notice_compliance" id="notice_compliance_emergency" value="emergency_termination" />
                                    <span class="form-check-label fw-bold">
                                        Emergency Termination
                                    </span>
                                </label>
                                <div class="form-text">Immediate termination required</div>
                            </div>
                        </div>

                        <!--begin::Notice Selector (visible when proper_notice is selected)-->
                        <div id="notice_selection_section">
                            <label class="fs-7 fw-bold form-label mb-2 required">Select the Notice This Termination Is Based On</label>

                            <!--begin::Loading state-->
                            <div id="notice_selector_loading" class="d-flex align-items-center text-muted fs-7 py-3" style="display: none !important;">
                                <span class="spinner-border spinner-border-sm me-2"></span> Loading notices...
                            </div>
                            <!--end::Loading state-->

                            <!--begin::Empty state-->
                            <div id="notice_selector_empty" class="alert alert-warning d-flex align-items-center p-4" style="display: none !important;">
                                <i class="fas fa-exclamation-triangle fs-3 me-3 text-warning"></i>
                                <div>
                                    <div class="fw-bold">No termination notice found</div>
                                    <div class="fs-7">A <strong>Lease Termination</strong> or <strong>Eviction Notice</strong> must be issued to this tenant before the tenancy can be ended. Click <strong>Give Notice</strong> first.</div>
                                </div>
                            </div>
                            <!--end::Empty state-->

                            <!--begin::Notice dropdown-->
                            <div id="notice_selector_dropdown" style="display: none;">
                                <select name="notice_id" id="tenancy_notice_id"
                                        class="form-select form-select-solid"
                                        data-control="select2"
                                        data-placeholder="Select the notice..."
                                        data-allow-clear="true">
                                    <option></option>
                                </select>
                                <div class="form-text">Select the notice that was previously sent to the tenant</div>
                            </div>
                            <!--end::Notice dropdown-->
                        </div>
                        <!--end::Notice Selector-->
                    </div>
                    <!--end::Notice Period Compliance-->

                    <!--begin::Financial Settlement-->
                    <div class="fv-row mb-8" data-settlement>
                        <label class="fs-6 fw-bold form-label mb-2">Settlement</label>
                        <p class="text-muted fs-7 mb-4">Worked out from the tenant's account for the leaving date. Rent for periods that start after they leave is cancelled, damages are charged, the deposit pays what is still owed, and the rest of the deposit and any prepaid rent is owed back to the tenant.</p>
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <label class="fs-7 fw-bold form-label mb-2" for="damage_charges">Damages to charge</label>
                                <input type="number" name="damage_charges" id="damage_charges" class="form-control form-control-solid" placeholder="0.00" step="0.01" min="0" />
                            </div>
                            <div class="col-md-6">
                                <label class="fs-7 fw-bold form-label mb-2" for="damage_description">What the damages are for</label>
                                <input type="text" name="damage_description" id="damage_description" class="form-control form-control-solid" placeholder="e.g. Broken window, repainting" maxlength="255" />
                            </div>
                        </div>
                        <div class="border rounded p-4 mb-5 fs-7" data-settle-figures>
                            <div class="d-flex justify-content-between"><span>Deposit held</span><span class="fw-bold" data-settle="deposit_held">–</span></div>
                            <div class="d-flex justify-content-between"><span>Owed by the tenant (incl. damages)</span><span class="fw-bold" data-settle="owed_before_deposit">–</span></div>
                            <div class="d-flex justify-content-between"><span>Deposit used for what is owed</span><span class="fw-bold" data-settle="deposit_applied">–</span></div>
                            <div class="d-flex justify-content-between"><span>Owed back to the tenant (deposit left and prepaid rent)</span><span class="fw-bold" data-settle="refundable">–</span></div>
                            <div class="d-flex justify-content-between"><span>Still owed by the tenant after the deposit</span><span class="fw-bold" data-settle="still_owed">–</span></div>
                        </div>
                        <div class="row mb-5">
                            <div class="col-md-4">
                                <label class="fs-7 fw-bold form-label mb-2" for="security_deposit_refund">Refund now</label>
                                <input type="number" name="security_deposit_refund" id="security_deposit_refund" class="form-control form-control-solid" placeholder="0.00" step="0.01" min="0" />
                                <div class="form-text" data-settle="refund_hint">Anything not refunded now stays as credit to refund later.</div>
                            </div>
                            <div class="col-md-4">
                                <label class="fs-7 fw-bold form-label mb-2" for="refund_method">Refund paid by</label>
                                <select name="refund_method" id="refund_method" class="form-select form-select-solid">
                                    <option value="bank_transfer">Bank transfer</option>
                                    <option value="mobile_money">Mobile money</option>
                                    <option value="cash">Cash</option>
                                    <option value="check">Cheque</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="fs-7 fw-bold form-label mb-2" for="refund_reference">Refund reference</label>
                                <input type="text" name="refund_reference" id="refund_reference" class="form-control form-control-solid" maxlength="255" />
                            </div>
                        </div>
                        <div class="alert alert-light-info p-4 mb-0">
                            <div class="d-flex justify-content-between"><span class="fw-bold">Net settlement</span><span class="fw-bold text-primary" id="net_settlement_amount">–</span></div>
                            <div class="form-text">Positive = owed back to the tenant. Negative = the tenant still owes this.</div>
                        </div>
                    </div>
                    <!--end::Financial Settlement-->

                    <!--begin::Property Condition-->
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-bold form-label mb-2">Property Condition</label>
                        <div class="row">
                            <div class="col-md-4">
                                <label class="form-check form-check-custom form-check-solid mb-3">
                                    <input class="form-check-input" type="radio" name="property_condition" value="good" checked />
                                    <span class="form-check-label fw-bold">Good Condition</span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-check form-check-custom form-check-solid mb-3">
                                    <input class="form-check-input" type="radio" name="property_condition" value="fair" />
                                    <span class="form-check-label fw-bold">Fair Condition</span>
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-check form-check-custom form-check-solid mb-3">
                                    <input class="form-check-input" type="radio" name="property_condition" value="damaged" />
                                    <span class="form-check-label fw-bold">Damaged</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <!--end::Property Condition-->

                    <!--begin::Keys Returned-->
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-bold form-label mb-2">Keys and Access</label>
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="keys_returned" value="1" />
                                    <span class="form-check-label fw-bold">Keys Returned</span>
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check form-check-custom form-check-solid">
                                    <input class="form-check-input" type="checkbox" name="access_cards_returned" value="1" />
                                    <span class="form-check-label fw-bold">Access Cards Returned</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <!--end::Keys Returned-->

                    <!--begin::Forwarding Address (Optional)-->
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-bold form-label mb-2">Tenant's New Address (Optional)</label>
                        <textarea name="forwarding_address" 
                                  class="form-control form-control-solid" 
                                  rows="3" 
                                  placeholder="Enter tenant's forwarding address for future correspondence"></textarea>
                        <div class="form-text">This will be used for sending final settlement or future correspondence</div>
                    </div>
                    <!--end::Forwarding Address-->

                    <!--begin::Additional Notes-->
                    <div class="fv-row mb-8">
                        <label class="fs-6 fw-bold form-label mb-2">Additional Notes</label>
                        <textarea name="termination_notes" 
                                  class="form-control form-control-solid" 
                                  rows="4" 
                                  placeholder="Any additional details about the tenancy termination..."></textarea>
                        <div class="form-text">Include any relevant details about the termination process</div>
                    </div>
                    <!--end::Additional Notes-->
                </form>
                <!--end::Form-->
            </div>
            <!--end::Modal body-->

            <!--begin::Modal footer-->
            <div class="modal-footer">
                <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                <button type="button" 
                        class="btn btn-danger" 
                        data-kt-tenancy-modal-action="submit">
                    <span class="indicator-label">End Tenancy</span>
                    <span class="indicator-progress">Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
            </div>
            <!--end::Modal footer-->
        </div>
    </div>
</div>
<script>
(function () {
    // Settlement figures are the server's own calculation for the chosen date and damages (nothing is saved).
    let figures = null, timer = null;
    const fmt = (v, cur) => (cur || 'KES') + ' ' + Number(v || 0).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const modal = () => document.getElementById('kt_modal_end_tenancy');
    function paint() {
        if (!figures) return;
        const m = modal();
        ['deposit_held', 'owed_before_deposit', 'deposit_applied', 'refundable', 'still_owed'].forEach(k => {
            const el = m.querySelector('[data-settle="' + k + '"]'); if (el) el.textContent = fmt(figures[k], figures.currency);
        });
        const refundInput = document.getElementById('security_deposit_refund');
        refundInput.max = Number(figures.refundable).toFixed(2);
        if (refundInput.dataset.touched !== '1') refundInput.value = Number(figures.refundable).toFixed(2);
        const net = Number(figures.refundable) - Number(figures.still_owed);
        const el = document.getElementById('net_settlement_amount');
        el.textContent = (net > 0 ? '+' : net < 0 ? '−' : '') + fmt(Math.abs(net), figures.currency);
        el.className = 'fw-bold ' + (net > 0 ? 'text-success' : net < 0 ? 'text-danger' : 'text-primary');
    }
    function load() {
        const m = modal(); if (!m) return;
        const id = document.getElementById('tenancy_tenant_id').value;
        const date = m.querySelector('input[name="termination_date"]').value || new Date().toISOString().slice(0, 10);
        if (!id) return;
        const q = new URLSearchParams({tenant_id: id, termination_date: date, damage_charges: document.getElementById('damage_charges').value || 0});
        fetch('/tenant_management/end-tenancy/preview?' + q, {headers: {'Accept': 'application/json'}})
            .then(r => r.ok ? r.json() : Promise.reject(r)).then(json => { figures = json.settlement; paint(); }).catch(() => {});
    }
    const later = () => { clearTimeout(timer); timer = setTimeout(load, 350); };
    document.addEventListener('shown.bs.modal', function (e) {
        if (e.target.id !== 'kt_modal_end_tenancy') return;
        document.getElementById('security_deposit_refund').dataset.touched = '0';
        figures = null; load();
    });
    document.addEventListener('input', function (e) {
        if (!modal() || !modal().contains(e.target)) return;
        if (e.target.id === 'security_deposit_refund') { e.target.dataset.touched = '1'; return; }
        if (e.target.id === 'damage_charges' || e.target.name === 'termination_date') later();
    });
    document.addEventListener('change', function (e) {
        if (modal() && modal().contains(e.target) && e.target.name === 'termination_date') later();
    });
})();
</script>
