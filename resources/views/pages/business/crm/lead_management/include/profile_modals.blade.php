{{-- Update Status Modal --}}
<div class="modal fade" id="kt_modal_update_status" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Update Lead Status</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <form id="kt_modal_update_status_form" class="form">
                <div class="modal-body">
                    
                    <input type="hidden" name="lead_id" value="{{ encrypt($lead->id) }}">
                    
                    <div class="mb-10">
                        <label class="required fs-6 fw-bold mb-2">New Status</label>
                        <select name="status" class="form-select form-select-solid" data-control="select2" data-hide-search="true" data-placeholder="Select status">
                            <option value="">Select status...</option>
                            <option value="new" {{ $lead->status === 'new' ? 'selected' : '' }}>New</option>
                            <option value="contacted" {{ $lead->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                            <option value="qualified" {{ $lead->status === 'qualified' ? 'selected' : '' }}>Qualified</option>
                            <option value="proposal" {{ $lead->status === 'proposal' ? 'selected' : '' }}>Proposal</option>
                            <option value="negotiation" {{ $lead->status === 'negotiation' ? 'selected' : '' }}>Negotiation</option>
                            <option value="closed_won" {{ $lead->status === 'closed_won' ? 'selected' : '' }}>Closed Won</option>
                            <option value="closed_lost" {{ $lead->status === 'closed_lost' ? 'selected' : '' }}>Closed Lost</option>
                        </select>
                    </div>
                    
                    <div class="mb-10">
                        <label class="fs-6 fw-bold mb-2">Notes</label>
                        <textarea name="notes" class="form-control form-control-solid" rows="3" placeholder="Add notes about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Update Status</span>
                        <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Add Interaction Modal --}}
<div class="modal fade" id="kt_modal_add_interaction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="fw-bolder">Add Interaction</h2>
                <div class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <span class="svg-icon svg-icon-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <rect opacity="0.5" x="6" y="17.3137" width="16" height="2" rx="1" transform="rotate(-45 6 17.3137)" fill="black" />
                            <rect x="7.41422" y="6" width="16" height="2" rx="1" transform="rotate(45 7.41422 6)" fill="black" />
                        </svg>
                    </span>
                </div>
            </div>
            <form id="kt_modal_add_interaction_form" class="form">
                <div class="modal-body">
                    
                    <input type="hidden" name="lead_id" value="{{ encrypt($lead->id) }}">
                    
                    <div class="mb-10">
                        <label class="required fs-6 fw-bold mb-2">Interaction Type</label>
                        <select name="interaction_type" class="form-select form-select-solid" data-control="select2" data-hide-search="true" data-placeholder="Select type">
                            <option value="">Select type...</option>
                            <option value="call">Phone Call</option>
                            <option value="email">Email</option>
                            <option value="meeting">Meeting</option>
                            <option value="note">Note</option>
                            <option value="property_viewing">Property Viewing</option>
                        </select>
                    </div>
                    
                    <div class="mb-10">
                        <label class="required fs-6 fw-bold mb-2">Details</label>
                        <textarea name="details" class="form-control form-control-solid" rows="4" placeholder="Describe the interaction..."></textarea>
                    </div>
                    
                    <div class="mb-10">
                        <label class="fs-6 fw-bold mb-2">Follow-up Required?</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="requires_followup" id="requires_followup">
                            <label class="form-check-label" for="requires_followup">
                                Yes, schedule a follow-up
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-10 d-none" id="followup_date_section">
                        <label class="fs-6 fw-bold mb-2">Follow-up Date</label>
                        <input type="datetime-local" name="followup_date" class="form-control form-control-solid">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="reset" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <span class="indicator-label">Add Interaction</span>
                        <span class="indicator-progress">Please wait...
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
