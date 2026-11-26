<div class="card">
    <!--begin::Card header-->
    <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#kt_account_deactivate" aria-expanded="true" aria-controls="kt_account_deactivate">
        <div class="card-title m-0">
            <h3 class="fw-bolder m-0">Delete Account</h3>
        </div>
    </div>
    <!--end::Card header-->
    <!--begin::Content-->
    <div id="kt_account_settings_deactivate" class="collapse show">
        <!--begin::Form-->
        <form id="kt_account_deactivate_form" class="form">
            <!--begin::Card body-->
            <div class="card-body border-top p-9">
                <!--begin::Notice-->
                <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed mb-9 p-6">
                    <!--begin::Icon-->
                    <!--begin::Svg Icon | path: icons/duotune/general/gen044.svg-->
                    <span class="svg-icon svg-icon-2tx svg-icon-warning me-4">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.3" x="2" y="2" width="20" height="20" rx="10" fill="black" />
                            <rect x="11" y="14" width="7" height="2" rx="1" transform="rotate(-90 11 14)" fill="black" />
                            <rect x="11" y="17" width="2" height="2" rx="1" transform="rotate(-90 11 17)" fill="black" />
                        </svg>
                    </span>
                    <!--end::Svg Icon-->
                    <!--end::Icon-->
                    <!--begin::Wrapper-->
                    <div class="d-flex flex-stack flex-grow-1">
                        <!--begin::Content-->
                        <div class="fw-bold">
                            <h4 class="text-gray-900 fw-bolder">You Are Deleting Your Account</h4>
                            <div class="fs-6 text-gray-700">For extra security, this requires you to confirm your email when you deactivate your password. Please not this is a permanent action and all records shall be removed from our systems.
                            <a class="fw-bolder" href="#" data-bs-toggle="modal" data-bs-target="#perma_delete">Learn more</a></div>
                        </div>
                        <!--end::Content-->
                    </div>
                    <!--end::Wrapper-->
                </div>
                <!--end::Notice-->
                <div class="d-flex flex-column justify-content-center">
                    <!--begin::Form input row-->
                    <div class="form-check-solid fv-row mb-7">
                        <label class="form-check-label fw-bold ps-2 fs-6 mb-4" for="deactivate">Enter your account email</label>
                        <input type="email" class="form-control form-control-lg form-control-solid" id="mail" placeholder="Email Address" name="mail" />
                    </div>
                    <!--end::Form input row-->
                    <!--begin::Form input row-->
                    <div class="form-check-solid fv-row">
                        <input name="deactivate" class="form-check-input" type="checkbox" value="" id="deactivate" />
                        <label class="form-check-label fw-bold ps-2 fs-6" for="deactivate">I confirm my account deletion</label>
                    </div>
                    <!--end::Form input row-->
                </div>

                <!--begin::Modal-->

                <div class="modal fade" tabindex="-1" id="perma_delete">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Permanent Delete Policy</h5>
                
                                <!--begin::Close-->
                                <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                                    <span class="svg-icon svg-icon-2x">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black"></rect>
                                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black"></rect>
                                        </svg>
                                    </span>
                                </div>
                                <!--end::Close-->
                            </div>
                
                            <div class="modal-body">
                                {{-- Stored, admin-written HTML (descriptions table). --}}
{!! \App\Models\Description::where('type', 'delete_policy')->value('description') !!}
                            </div>
                
                            
                        </div>
                    </div>
                </div>

                <!--end::Modal-->
                
            </div>
            <!--end::Card body-->
            <!--begin::Card footer-->
            <div class="card-footer d-flex justify-content-end py-6 px-9">
                <button id="kt_account_deactivate_account_submit" type="submit" class="btn btn-danger fw-bold">Delete Account</button>
            </div>
            <!--end::Card footer-->
        </form>
        <!--end::Form-->
    </div>
    <!--end::Content-->
</div>