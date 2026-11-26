<!--begin::Modal - Upload Leads-->
<div class="modal fade" id="kt_modal_upload_leads" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <form class="form" action="#" id="kt_modal_upload_leads_form">
                <!--begin::Modal header-->
                <div class="modal-header" id="kt_modal_upload_leads_header">
                    <h2 class="fw-bold">Upload Leads</h2>
                    <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                        <span class="svg-icon svg-icon-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"></rect>
                                <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"></rect>
                            </svg>
                        </span>
                    </div>
                </div>
                <!--end::Modal header-->
                <!--begin::Modal body-->
                <div class="modal-body py-10 px-lg-17">
                    <!--begin::Scroll-->
                    <div class="scroll-y me-n7 pe-7" id="kt_modal_upload_leads_scroll" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-max-height="auto" data-kt-scroll-dependencies="#kt_modal_upload_leads_header" data-kt-scroll-wrappers="#kt_modal_upload_leads_scroll" data-kt-scroll-offset="300px">
                        <!--begin::Input group-->
                        <div class="fv-row mb-7">
                            <!--begin::Dropzone-->
                            <div class="dropzone" id="kt_modal_upload_leads_dropzone">
                                <div class="dz-message needsclick">
                                    <i class="ki-duotone ki-file-up fs-3x text-primary">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    <div class="ms-4">
                                        <h3 class="fs-5 fw-bold text-gray-900 mb-1">Drop files here or click to upload.</h3>
                                        <span class="fs-7 fw-semibold text-gray-400">Upload CSV or Excel files with lead data</span>
                                    </div>
                                </div>
                            </div>
                            <!--end::Dropzone-->
                        </div>
                        <!--end::Input group-->
                        
                        <!--begin::Input group-->
                        <div class="fv-row mb-7">
                            <label class="fs-6 fw-semibold form-label mb-2">
                                <span class="required">Default Lead Source</span>
                                <i class="fas fa-exclamation-circle ms-2 fs-7" data-bs-toggle="popover" data-bs-trigger="hover" data-bs-html="true" data-bs-content="Select the default source for uploaded leads"></i>
                            </label>
                            <select name="default_source" class="form-select form-select-solid" data-control="select2" data-placeholder="Select a source">
                                <option></option>
                                <option value="website">Website</option>
                                <option value="referral">Referral</option>
                                <option value="social_media">Social Media</option>
                                <option value="advertisement">Advertisement</option>
                                <option value="trade_show">Trade Show</option>
                                <option value="cold_call">Cold Call</option>
                                <option value="import">Data Import</option>
                            </select>
                        </div>
                        <!--end::Input group-->
                        
                        <!--begin::Notice-->
                        <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-6">
                            <i class="ki-duotone ki-information fs-2tx text-warning me-4">
                                <span class="path1"></span>
                                <span class="path2"></span>
                                <span class="path3"></span>
                            </i>
                            <div class="d-flex flex-stack flex-grow-1">
                                <div class="fw-semibold">
                                    <h4 class="text-gray-900 fw-bold">File Format Requirements</h4>
                                    <div class="fs-6 text-gray-700">
                                        Ensure your file includes these columns:
                                        <br/>• First Name, Last Name, Email, Phone
                                        <br/>• Property Type, Budget Min, Budget Max
                                        <br/>• Location Preference (optional)
                                        <br/>
                                        <a href="#" class="fw-bold">Download Sample Template</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!--end::Notice-->
                    </div>
                    <!--end::Scroll-->
                </div>
                <!--end::Modal body-->
                <!--begin::Modal footer-->
                <div class="modal-footer flex-center">
                    <button type="reset" id="kt_modal_upload_leads_cancel" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="kt_modal_upload_leads_submit" class="btn btn-primary">
                        <span class="indicator-label">Upload Leads</span>
                        <span class="indicator-progress">Please wait...
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
                <!--end::Modal footer-->
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Upload Leads-->

