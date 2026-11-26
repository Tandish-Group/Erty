@if($canInviteLogin ?? false)
<!--begin::Modal - Invite Agent (agent who signs in as a team member)-->
<div class="modal fade" id="kt_modal_invite_agent" tabindex="-1" aria-hidden="true" aria-labelledby="kt_modal_invite_agent_title">
    <div class="modal-dialog modal-dialog-centered mw-750px">
        <div class="modal-content">
            <form class="form" action="#" id="kt_modal_invite_agent_form" novalidate>
                <div class="modal-header" id="kt_modal_invite_agent_header">
                    <h2 class="fw-bold" id="kt_modal_invite_agent_title">Invite an agent who signs in</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-8 px-lg-12">
                    <p class="text-muted fs-7 mb-6">They join the team with the role you choose, get an email link to set their password, and leads and tasks can be assigned to them. To record an external agent who won't sign in, use <a href="/agents/onboarding">Add agent</a>.</p>
                    <div class="row g-4">
                        <div class="col-md-6"><label class="required form-label" for="invite-agent-first">First name</label><input class="form-control form-control-solid" id="invite-agent-first" name="first_name" required maxlength="100"></div>
                        <div class="col-md-6"><label class="required form-label" for="invite-agent-last">Last name</label><input class="form-control form-control-solid" id="invite-agent-last" name="last_name" required maxlength="100"></div>
                        <div class="col-md-6"><label class="required form-label" for="invite-agent-email">Email</label><input type="email" class="form-control form-control-solid" id="invite-agent-email" name="email" required maxlength="150"></div>
                        <div class="col-md-6"><label class="required form-label" for="invite-agent-mobile">Phone</label><input type="tel" class="form-control form-control-solid" id="invite-agent-mobile" name="mobile" required maxlength="20" placeholder="+254 7…"></div>
                        <div class="col-md-6"><label class="required form-label" for="invite-agent-team-role">Team role (what they can open)</label>
                            <select class="form-select form-select-solid" id="invite-agent-team-role" name="business_role_id" required>
                                <option value="">Choose a role</option>
                                @foreach($teamRoles as $teamRole)<option value="{{ $teamRole->id }}" @selected($teamRole->name === 'Leasing / CRM Agent')>{{ $teamRole->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label" for="invite-agent-commission">Commission rate (%)</label><input type="number" class="form-control form-control-solid" id="invite-agent-commission" name="commission_rate" min="0" max="100" step="0.01" value="0"></div>
                        <div class="col-md-6"><label class="form-label" for="invite-agent-role">Agent role</label>
                            <select name="role" id="invite-agent-role" class="form-select form-select-solid">
                                <option value="">Not set</option>
                                @foreach($agentRoles as $agentRole)<option value="{{ $agentRole->id }}">{{ $agentRole->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label" for="invite-agent-department">Department</label>
                            <select name="department" id="invite-agent-department" class="form-select form-select-solid">
                                <option value="">Not set</option>
                                @foreach($agentDepartments as $agentDepartment)<option value="{{ $agentDepartment->id }}">{{ $agentDepartment->name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label" for="invite-agent-notes">Notes (optional)</label><textarea class="form-control form-control-solid" rows="2" id="invite-agent-notes" name="message" maxlength="1000"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer flex-center">
                    <button type="button" class="btn btn-light me-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="kt_modal_invite_agent_submit_button" class="btn btn-primary">
                        <span class="indicator-label">Send invitation</span>
                        <span class="indicator-progress">Please wait…
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!--end::Modal - Invite Agent-->
@endif
