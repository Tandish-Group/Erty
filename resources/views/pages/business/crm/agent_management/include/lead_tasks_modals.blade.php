
<!--Qualification Phase Modals-->
<div class="modal fade" id="modal_acknowledge_assignment" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Acknowledge Lead Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="acknowledge_assignment">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">Have you reviewed all lead details?</label>
                        <select class="form-select" name="reviewed_details" required>
                            <option value="">Select...</option>
                            <option value="yes">Yes, I have reviewed all details</option>
                            <option value="incomplete">No, some information is missing</option>
                        </select>
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Missing Information (if any)</label>
                        <textarea class="form-control" name="missing_info" rows="3" placeholder="List any missing or incomplete information..."></textarea>
                    </div>
                    <div class="mb-5">
                        <label class="form-label required">Acknowledgment Time</label>
                        <input type="datetime-local" class="form-control" name="acknowledged_at" required>
                    </div>
                    <div class="mb-5">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Any additional notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Complete Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_review_details" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Lead Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="review_details">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">Contact Information Complete?</label>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="contact_complete" value="yes" required>
                            <label class="form-check-label">Complete</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="contact_complete" value="no">
                            <label class="form-check-label">Incomplete - needs update</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Budget Information</label>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="budget_verified" value="verified" required>
                            <label class="form-check-label">Verified and realistic</label>
                        </div>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="budget_verified" value="needs_adjustment">
                            <label class="form-check-label">Needs adjustment</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="budget_verified" value="missing">
                            <label class="form-check-label">Not provided</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Property Requirements Clarity</label>
                        <select class="form-select" name="requirements_clarity" required>
                            <option value="">Select...</option>
                            <option value="very_clear">Very clear and specific</option>
                            <option value="moderately_clear">Moderately clear</option>
                            <option value="vague">Vague - needs clarification</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Timeline</label>
                        <select class="form-select" name="timeline_urgency" required>
                            <option value="">Select...</option>
                            <option value="immediate">Immediate (within 1 week)</option>
                            <option value="short_term">Short term (1-4 weeks)</option>
                            <option value="medium_term">Medium term (1-3 months)</option>
                            <option value="long_term">Long term (3+ months)</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Critical Missing Information</label>
                        <textarea class="form-control" name="missing_critical_info" rows="3" placeholder="List any critical information needed..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Review Notes</label>
                        <textarea class="form-control" name="review_notes" rows="3" placeholder="Overall assessment and next steps..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Complete Review</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_verify_availability" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Property Availability</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="verify_availability">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">Properties Found</label>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="properties_available" value="yes" required>
                            <label class="form-check-label">Yes, matching properties available</label>
                        </div>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="properties_available" value="limited">
                            <label class="form-check-label">Limited options available</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="properties_available" value="no">
                            <label class="form-check-label">No matching properties</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Number of Matching Properties</label>
                        <input type="number" class="form-control" name="property_count" min="0" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Property IDs (comma-separated)</label>
                        <input type="text" class="form-control" name="property_ids" placeholder="e.g., 101, 205, 312">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Budget Match</label>
                        <select class="form-select" name="budget_match" required>
                            <option value="">Select...</option>
                            <option value="perfect">Perfect match within budget</option>
                            <option value="close">Close match (slightly over/under)</option>
                            <option value="needs_adjustment">Needs budget adjustment discussion</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Property Match Details</label>
                        <textarea class="form-control" name="match_details" rows="4" placeholder="Describe how well properties match requirements..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Recommendations</label>
                        <textarea class="form-control" name="recommendations" rows="3" placeholder="Next steps and recommendations..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Complete Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_initial_contact" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Initial Contact Attempt</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="initial_contact">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">Contact Method Used</label>
                        <select class="form-select" name="contact_method" required>
                            <option value="">Select...</option>
                            <option value="phone">Phone Call</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                            <option value="in_person">In Person</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Contact Date & Time</label>
                        <input type="datetime-local" class="form-control" name="contact_datetime" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Contact Outcome</label>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="contact_outcome" value="successful" required>
                            <label class="form-check-label">Successful - lead engaged</label>
                        </div>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="contact_outcome" value="no_answer">
                            <label class="form-check-label">No answer - left message</label>
                        </div>
                        <div class="form-check mb-5">
                            <input class="form-check-input" type="radio" name="contact_outcome" value="unreachable">
                            <label class="form-check-label">Unreachable - wrong number/email</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="contact_outcome" value="postponed">
                            <label class="form-check-label">Postponed - callback requested</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Lead's Interest Level</label>
                        <select class="form-select" name="interest_level">
                            <option value="">Select...</option>
                            <option value="very_high">Very High - ready to view</option>
                            <option value="high">High - interested</option>
                            <option value="moderate">Moderate - still exploring</option>
                            <option value="low">Low - not urgent</option>
                            <option value="not_interested">Not interested</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Next Meeting/Call Scheduled</label>
                        <input type="datetime-local" class="form-control" name="next_contact_datetime">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Key Discussion Points</label>
                        <textarea class="form-control" name="discussion_points" rows="4" placeholder="What was discussed during the contact..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Lead's Questions/Concerns</label>
                        <textarea class="form-control" name="lead_concerns" rows="3" placeholder="Any questions or concerns raised by the lead..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Contact Notes</label>
                        <textarea class="form-control" name="contact_notes" rows="3" placeholder="Overall notes from the contact..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Complete Contact Log</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_update_status" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Update Lead Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="update_status">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">New Lead Status</label>
                        <select class="form-select" name="new_status" required>
                            <option value="">Select...</option>
                            <option value="qualified">Qualified - proceed to engagement</option>
                            <option value="contacted">Contacted - needs follow up</option>
                            <option value="not_qualified">Not Qualified</option>
                            <option value="closed_lost">Closed Lost</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Priority Level</label>
                        <select class="form-select" name="priority" required>
                            <option value="">Select...</option>
                            <option value="hot">Hot - High priority</option>
                            <option value="warm">Warm - Medium priority</option>
                            <option value="cold">Cold - Low priority</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Qualification Reason</label>
                        <textarea class="form-control" name="qualification_reason" rows="3" required placeholder="Explain the status change decision..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Next Steps</label>
                        <textarea class="form-control" name="next_steps" rows="3" placeholder="Planned next actions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--Conversion Phase Modals-->
<div class="modal fade" id="modal_initiate_onboarding" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Initiate Tenant Onboarding</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="initiate_onboarding">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">Property Selected</label>
                        <input type="text" class="form-control" name="property_selected" required placeholder="Property ID or Name">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Lease Start Date</label>
                        <input type="date" class="form-control" name="lease_start_date" required>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Lease Duration</label>
                        <select class="form-select" name="lease_duration" required>
                            <option value="">Select...</option>
                            <option value="6_months">6 Months</option>
                            <option value="12_months">12 Months</option>
                            <option value="24_months">24 Months</option>
                            <option value="custom">Custom Duration</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Monthly Rent Amount</label>
                        <input type="number" class="form-control" name="monthly_rent" required step="0.01">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Security Deposit Amount</label>
                        <input type="number" class="form-control" name="security_deposit" required step="0.01">
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Special Terms/Conditions</label>
                        <textarea class="form-control" name="special_terms" rows="3" placeholder="Any special lease terms or conditions..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Onboarding Form Shared?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="form_shared" value="yes" required>
                            <label class="form-check-label">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="form_shared" value="no">
                            <label class="form-check-label">No - will share next</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="onboarding_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Initiate Onboarding</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_verify_documents" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Verify Tenant Documentation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="verify_documents">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">ID Document</label>
                        <select class="form-select" name="id_document_status" required>
                            <option value="">Select...</option>
                            <option value="verified">Verified and approved</option>
                            <option value="pending">Received - pending verification</option>
                            <option value="resubmit">Needs resubmission</option>
                            <option value="missing">Not received</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Proof of Income</label>
                        <select class="form-select" name="income_proof_status" required>
                            <option value="">Select...</option>
                            <option value="verified">Verified and sufficient</option>
                            <option value="pending">Received - pending verification</option>
                            <option value="insufficient">Insufficient income</option>
                            <option value="missing">Not received</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">References</label>
                        <select class="form-select" name="references_status" required>
                            <option value="">Select...</option>
                            <option value="verified">All references verified</option>
                            <option value="partial">Partially verified</option>
                            <option value="pending">Pending verification</option>
                            <option value="missing">Not provided</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Business Documents (if applicable)</label>
                        <select class="form-select" name="business_docs_status">
                            <option value="">Not applicable</option>
                            <option value="verified">Verified</option>
                            <option value="pending">Pending verification</option>
                            <option value="missing">Not received</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Credit/Background Check</label>
                        <select class="form-select" name="background_check" required>
                            <option value="">Select...</option>
                            <option value="passed">Passed</option>
                            <option value="conditional">Conditional approval</option>
                            <option value="failed">Failed - discuss with management</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Document Issues/Concerns</label>
                        <textarea class="form-control" name="document_issues" rows="3" placeholder="Any issues or concerns with documentation..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Verification Notes</label>
                        <textarea class="form-control" name="verification_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Complete Verification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--Finalization Phase Modals-->
<div class="modal fade" id="modal_activate_tenant" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Activate Tenant Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="activate_tenant">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">Tenant Record Complete?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="record_complete" value="yes" required>
                            <label class="form-check-label">Yes, all information captured</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="record_complete" value="no">
                            <label class="form-check-label">No, missing information</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Billing Setup Complete?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="billing_setup" value="yes" required>
                            <label class="form-check-label">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="billing_setup" value="no">
                            <label class="form-check-label">No</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">First Invoice Generated?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="first_invoice" value="yes" required>
                            <label class="form-check-label">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="first_invoice" value="no">
                            <label class="form-check-label">No</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Tenant Portal Access Provided?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="portal_access" value="yes" required>
                            <label class="form-check-label">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="portal_access" value="no">
                            <label class="form-check-label">No - will provide</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Activation Notes</label>
                        <textarea class="form-control" name="activation_notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Activate Tenant</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modal_close_lead" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Close Lead Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form class="task-completion-form" data-task-template="close_lead">
                <div class="modal-body scroll h-400px">
                    <div class="mb-5">
                        <label class="form-label required">Lead-Tenant Linkage Verified?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="linkage_verified" value="yes" required>
                            <label class="form-check-label">Yes, linkage confirmed</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="linkage_verified" value="no">
                            <label class="form-check-label">No, needs correction</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">All Tasks Completed?</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="all_tasks_complete" value="yes" required>
                            <label class="form-check-label">Yes</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="all_tasks_complete" value="no">
                            <label class="form-check-label">No - some pending</label>
                        </div>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label required">Final Lead Status</label>
                        <select class="form-select" name="final_status" required>
                            <option value="">Select...</option>
                            <option value="closed_won">Closed Won - Successfully converted</option>
                            <option value="closed_lost">Closed Lost - Did not convert</option>
                        </select>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Conversion Summary</label>
                        <textarea class="form-control" name="conversion_summary" rows="4" placeholder="Overall summary of the lead-to-tenant conversion process..."></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label class="form-label">Lessons Learned</label>
                        <textarea class="form-control" name="lessons_learned" rows="3" placeholder="Any insights or improvements for future conversions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Close Lead Record</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!--end::Task Completion Modals-->