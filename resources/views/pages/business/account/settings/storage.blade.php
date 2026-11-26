<!--begin::Storage Management Card-->
<div class="card mb-5 mb-xl-10">
    <!--begin::Card header-->
    <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#kt_storage_management" aria-expanded="true" aria-controls="kt_storage_management">
        <div class="card-title m-0">
            <h3 class="fw-bolder m-0">Storage</h3>
        </div>
    </div>
    <!--end::Card header-->
    <!--begin::Content-->
    <div id="kt_storage_management" class="collapse show">
        <div class="card-body border-top p-9">

            <!--begin::Storage Overview-->
            <div class="d-flex flex-wrap flex-sm-nowrap mb-8">
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fs-5 fw-bold text-gray-800">Storage used</span>
                        @businessOwner
                        <button type="button" class="btn btn-sm btn-light-primary" id="btnRecalculate" title="Recalculate storage usage">
                            <i class="ki-duotone ki-arrows-circle fs-4"><span class="path1"></span><span class="path2"></span></i>
                            Sync
                        </button>
                        @endbusinessOwner
                    </div>
                    <div class="fw-bolder fs-3 text-gray-800" id="storageUsedText">--</div>
                    <div class="text-muted fs-7 mt-1">Files your business has uploaded: photos, floor plans, tenant and legal documents.</div>
                </div>
            </div>
            <!--end::Storage Overview-->

            <div class="separator separator-dashed my-6"></div>

            <!--begin::Storage Breakdown-->
            <div class="mb-8">
                <h5 class="fw-bold text-gray-800 mb-5">Storage Breakdown</h5>
                <div class="row g-5" id="storageBreakdownContainer">
                    <!-- Populated via JS -->
                    <div class="text-center text-muted py-5">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span class="ms-2">Loading breakdown...</span>
                    </div>
                </div>
            </div>
            <!--end::Storage Breakdown-->


        </div>
    </div>
    <!--end::Content-->
</div>
<!--end::Storage Management Card-->
