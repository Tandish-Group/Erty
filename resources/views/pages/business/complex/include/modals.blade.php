<div class="modal fade" tabindex="-1" id="kt_modal_upload_complexes" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-750px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Bulk Upload Data</h2>
                <div class="btn btn-sm btn-icon btn-active-color-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <div class="modal-body scroll h-400px">
                <!-- Upload Type Selection -->
                <div class="row mb-6">
                    <div class="col-12">
                        <label class="required form-label">Select Upload Type</label>
                        <select class="form-select" id="upload_type_select" data-control="select2" data-placeholder="Choose what you want to upload">
                            <option></option>
                            <option value="complexes" selected>Complexes Data</option>
                            <option value="units">Units Data</option>
                        </select>
                    </div>
                </div>

                <!-- Upload Instructions -->
                <div id="upload_instructions" class="alert alert-info d-none">
                    <div class="d-flex align-items-center">
                        <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                            <span class="path3"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h5 class="mb-1">Upload Instructions</h5>
                            <span id="instruction_text"></span>
                        </div>
                    </div>
                </div>

                <!-- Sample Download Links -->
                <div id="sample_download_section" class="row mb-6 d-none">
                    <div class="col-12">
                        <div class="card bg-light-primary">
                            <div class="card-body text-center py-5">
                                <i class="ki-duotone ki-document fs-3x text-primary mb-3">
                                    <span class="path1"></span>
                                    <span class="path2"></span>
                                </i>
                                <h5 class="text-primary mb-2">Download Sample Template</h5>
                                <p class="text-muted mb-4">Use the sample document as reference before uploading your data.</p>
                                <a href="#" id="sample_download_link" class="btn btn-primary">
                                    <i class="ki-duotone ki-down fs-3">
                                        <span class="path1"></span>
                                        <span class="path2"></span>
                                    </i>
                                    Download Sample
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upload Area -->
                <div id="upload_area_container" class="d-none">
                    <div class="card">
                        <div class="card-body">
                            <form id="bulk_upload_form" enctype="multipart/form-data">
                                @csrf
                                <div class="row">
                                    <div class="col-12">
                                        <label class="form-label required">Select Excel File</label>
                                        <input type="file" 
                                               class="form-control" 
                                               id="upload_file" 
                                               name="file" 
                                               accept=".xlsx,.xls"
                                               required>
                                        <div class="form-text" id="file_help_text">
                                            Please select an Excel file (.xlsx or .xls format only)
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mt-5">
                                    <div class="col-12 d-flex justify-content-end">
                                        <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">
                                            Cancel
                                        </button>
                                        <button type="submit" class="btn btn-primary" id="upload_submit_btn">
                                            <span class="indicator-label">
                                                <i class="fas fa-upload me-2"></i>Upload File
                                            </span>
                                            <span class="indicator-progress">
                                                <span class="spinner-border spinner-border-sm align-middle me-2"></span>
                                                Uploading...
                                            </span>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Upload Status -->
                <div id="upload_status" class="alert d-none">
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-40px me-3">
                            <span class="symbol-label">
                                <i class="ki-duotone ki-check fs-2 text-success"></i>
                            </span>
                        </div>
                        <div class="d-flex flex-column flex-grow-1">
                            <span class="fw-bold text-gray-800 text-hover-primary fs-6">Upload Successful!</span>
                            <span class="text-muted fw-semibold" id="upload_message">Your data has been processed successfully.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
