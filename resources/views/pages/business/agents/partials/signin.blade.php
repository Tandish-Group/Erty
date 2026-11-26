{{-- Sign-in link between this agent and a team member (agent_management.edit). --}}
@php
    $status = $login ? ($login->is_suspended ? ['Suspended', 'danger'] : (($login->invited_at && !$login->invitation_accepted_at && !$login->last_login_at) ? ['Invited', 'warning'] : ['Active', 'success'])) : null;
@endphp
<div class="card border-0 shadow-sm mb-6" data-agent-signin data-token="{{ $agent->profile_route_token }}">
    <div class="card-header border-0 pt-6"><h2 class="card-title fs-4 fw-bold">Sign-in</h2></div>
    <div class="card-body pt-2">
        @if($login)
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-semibold">{{ trim($login->first_name.' '.$login->last_name) }} <span class="text-muted fs-7">{{ $login->email }}</span></div>
                    <div class="fs-7 mt-1">
                        @if($login->is_sub_account)<span class="badge badge-light-{{ $login->businessRole->color ?? 'secondary' }}">{{ $login->businessRole->name ?? 'No role' }}</span>@else<span class="badge badge-light-dark">Account owner</span>@endif
                        <span class="badge badge-light-{{ $status[1] }}">{{ $status[0] }}</span>
                    </div>
                    <div class="text-muted fs-8 mt-2">This agent signs in to {{ config('app.name') }}. Leads and tasks assigned here appear under their My tasks and My agent dashboard.</div>
                </div>
                <div class="d-flex gap-2">
                    @if($login->is_sub_account)
                        @businessCan('subaccount_management', 'view')<a class="btn btn-sm btn-light" href="{{ route('business.team.index') }}">Open in Team &amp; roles</a>@endbusinessCan
                    @endif
                    @if($canManageLogin && (int) $login->id !== (int) auth()->id())<button type="button" class="btn btn-sm btn-light-danger" data-signin-action="unlink">Unlink</button>@endif
                </div>
            </div>
        @elseif($canManageLogin)
            <p class="text-muted fs-7">This is an external agent without a sign-in. Link them to an existing team member, or invite them to sign in.</p>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label fs-7" for="signin-member">Link to a team member</label>
                    <select class="form-select" id="signin-member" data-signin-member data-url="{{ route('agent_management.link_candidates', $agent->profile_route_token) }}" data-placeholder="Search team members without an agent profile"></select>
                    <button type="button" class="btn btn-sm btn-light-primary mt-2" data-signin-action="link">Link</button>
                </div>
                @if($canInviteLogin)
                <div class="col-md-6">
                    <label class="form-label fs-7" for="signin-role">Invite to sign in as</label>
                    <select class="form-select" id="signin-role" data-signin-role>
                        @foreach($teamRoles as $teamRole)<option value="{{ $teamRole->id }}" @selected($teamRole->name === 'Leasing / CRM Agent')>{{ $teamRole->name }}</option>@endforeach
                    </select>
                    <div class="form-text">An invitation goes to {{ $agent->agent_type === 'agency' ? ($agent->contact_person_email ?: $agent->email) : $agent->email }}.</div>
                    <button type="button" class="btn btn-sm btn-primary mt-2" data-signin-action="invite">Send invitation</button>
                </div>
                @endif
            </div>
            <div class="alert alert-danger d-none mt-4 mb-0" role="alert" data-signin-error></div>
        @else
            <p class="text-muted fs-7 mb-0">External agent without a sign-in.</p>
        @endif
    </div>
</div>
<script>
    // Link / unlink / invite this agent's sign-in, then reload the profile.
    document.addEventListener('DOMContentLoaded', function () {
        const card = document.querySelector('[data-agent-signin]');
        if (!card) return;
        const base = '/agents/profile/' + encodeURIComponent(card.dataset.token);
        const member = card.querySelector('[data-signin-member]');
        if (member && window.jQuery && jQuery.fn.select2) {
            jQuery(member).select2({ width: '100%', placeholder: member.dataset.placeholder, allowClear: true,
                ajax: { url: member.dataset.url, dataType: 'json', delay: 250, data: p => ({ q: p.term || '' }), processResults: d => ({ results: d.results || [] }) } });
        }
        const errorBox = card.querySelector('[data-signin-error]');
        card.addEventListener('click', async function (event) {
            const button = event.target.closest('[data-signin-action]');
            if (!button) return;
            const action = button.dataset.signinAction;
            let body = {};
            if (action === 'link') {
                if (!member?.value) { errorBox.textContent = 'Choose the team member to link.'; errorBox.classList.remove('d-none'); return; }
                body = { user_id: Number(member.value) };
            } else if (action === 'invite') {
                body = { business_role_id: Number(card.querySelector('[data-signin-role]').value) };
            } else if (action === 'unlink' && window.Swal) {
                const answer = await Swal.fire({ text: 'Unlink this sign-in? The agent record and its history stay; the person keeps their team access.', icon: 'warning', showCancelButton: true, confirmButtonText: 'Unlink' });
                if (!answer.isConfirmed) return;
            }
            button.disabled = true;
            errorBox?.classList.add('d-none');
            try {
                const response = await fetch(`${base}/${action === 'invite' ? 'invite-login' : action}`, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(body),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'That did not work.');
                if (window.Swal) await Swal.fire({ text: result.message, icon: 'success' });
                location.reload();
            } catch (error) {
                if (errorBox) { errorBox.textContent = error.message; errorBox.classList.remove('d-none'); } else if (window.Swal) Swal.fire({ text: error.message, icon: 'error' });
                button.disabled = false;
            }
        });
    });
</script>
