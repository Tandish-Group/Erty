{{-- "Create ticket" modal, on every business page. Script: public/js/pages/business/general.js. --}}
<div class="modal fade" id="support" tabindex="-1" aria-labelledby="support_title" aria-hidden="true">
    <div class="modal-dialog mw-650px modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="kt_modal_create_ticket" onsubmit="return false">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="support_title">Contact support</h5>
                        <div class="text-muted fs-7 mt-1">
                            Read the <a href="#" class="fw-semibold" id="kt_explore_toggle">support guidelines</a>, or see
                            <a href="{{ route('support.tickets.index') }}" class="fw-semibold">your tickets</a>.
                        </div>
                    </div>
                    <button type="button" class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times fs-4" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="modal-body px-8 py-6">
                    <div class="mb-5">
                        <label for="support_topic" class="form-label fw-semibold required">Topic</label>
                        <select id="support_topic" class="form-select form-select-solid" name="support_topic" data-placeholder="Choose what it is about" data-allow-clear="true">
                            <option></option>
                        </select>
                        <div id="custom_topic_container" class="mt-3" style="display: none;">
                            <label for="custom_topic_input" class="visually-hidden">Your topic</label>
                            <input type="text" class="form-control form-control-solid" name="custom_topic" id="custom_topic_input" maxlength="255" placeholder="What is it about?">
                        </div>
                    </div>

                    <div class="mb-5">
                        <label for="support_editor" class="form-label fw-semibold required">What do you need help with?</label>
                        <textarea class="form-control form-control-solid" rows="5" name="support_description" id="support_editor" placeholder="Say which page you were on, what you did and what you expected to happen."></textarea>
                    </div>

                    <div>
                        <span class="form-label fw-semibold d-block mb-2">Files <span class="text-muted fw-normal">(optional)</span></span>
                        <div id="support-uppy-dashboard"></div>
                        <div class="form-text text-muted mt-2" data-support-file-note></div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="kt_button_ticket">
                        <span class="indicator-label">Send ticket</span>
                        <span class="indicator-progress">Sending… <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
