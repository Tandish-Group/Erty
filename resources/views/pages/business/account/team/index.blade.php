@extends('pages.business.layouts.app')
@section('content')
@php
    $statusOf = function ($member) {
        if ($member->is_suspended) return ['Suspended', 'danger'];
        if ($member->invited_at && !$member->invitation_accepted_at && !$member->last_login_at) return ['Invited', 'warning'];
        return ['Active', 'success'];
    };
    $sections = app(\App\Services\Access\PermissionCatalogue::class)->sections(auth()->user());
    $menuOf = fn ($permissions) => app(\App\Services\Access\PermissionCatalogue::class)->menuFor((array) $permissions);
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-icon btn-light d-lg-none" id="kt_aside_toggle" type="button" aria-label="Open navigation">☰</button>
            <span class="fw-bold fs-4">Team &amp; roles</span>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl team-page" id="kt_content_container" data-team-page>
    <header class="team-head">
        <div>
            @businessCan('account_settings', 'view')<a href="{{ url('/business_account') }}" class="team-back"><i class="bi bi-arrow-left" aria-hidden="true"></i> Business settings</a>@endbusinessCan
            <h1>Team &amp; roles</h1>
            <p>People who work in {{ $owner->business->business_name ?? 'this business' }}. A role decides which sections each person can open and what they can do there; limited roles only see the properties and units assigned to each person.</p>
        </div>
        @if($can['invite'])<button type="button" class="btn btn-primary" data-open-member><i class="bi bi-person-plus me-2" aria-hidden="true"></i>Invite team member</button>@endif
    </header>

    <div class="team-stats" data-team-stats>
        <div><span>People</span><strong>{{ $stats['people'] }}</strong><small>including you</small></div>
        <div><span>Active</span><strong>{{ $stats['active'] }}</strong><small>signed in at least once</small></div>
        <div class="{{ $stats['invited'] ? 'is-warn' : '' }}"><span>Invited</span><strong>{{ $stats['invited'] }}</strong><small>{{ $stats['invited'] ? 'waiting to set a password' : 'nobody waiting' }}</small></div>
        <div class="{{ $stats['suspended'] ? 'is-bad' : '' }}"><span>Suspended</span><strong>{{ $stats['suspended'] }}</strong><small>cannot sign in</small></div>
        <div><span>Limited to assignments</span><strong>{{ $stats['limited'] }}</strong><small>{{ $stats['agents'] }} {{ \Illuminate\Support\Str::plural('agent', $stats['agents']) }} who sign in</small></div>
    </div>

    <ul class="nav team-tabs" role="tablist">
        <li class="nav-item" role="presentation"><a class="nav-link active" data-bs-toggle="tab" href="#team-members" role="tab">Team members <span class="count">{{ $members->count() + 1 }}</span></a></li>
        @if($can['viewRoles'])<li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#team-roles" role="tab">Roles <span class="count">{{ $roles->count() }}</span></a></li>@endif
    </ul>

    @php
        $initials = fn ($u) => mb_strtoupper(mb_substr((string) $u->first_name, 0, 1) . mb_substr((string) $u->last_name, 0, 1)) ?: '?';
        $departments = $members->map(fn ($m) => $m->businessRole?->department)->filter()->unique()->sort()->values();
    @endphp
    <div class="tab-content">
        <div class="tab-pane fade show active" id="team-members" role="tabpanel">
            <section class="team-panel">
                <div class="team-toolbar" role="search">
                    <div class="team-search"><i class="bi bi-search" aria-hidden="true"></i><input type="search" class="form-control form-control-sm" placeholder="Search name, email or role" aria-label="Search team members" data-team-search></div>
                    <select class="form-select form-select-sm" aria-label="Department" data-team-filter="department">
                        <option value="">All departments</option>
                        @foreach($departments as $d)<option value="{{ $d }}">{{ $d }}</option>@endforeach
                    </select>
                    <select class="form-select form-select-sm" aria-label="Status" data-team-filter="status">
                        <option value="">Any status</option>
                        @foreach(['Active', 'Invited', 'Suspended'] as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach
                    </select>
                    <span class="team-count" data-team-count aria-live="polite">{{ $members->count() + 1 }} people</span>
                </div>
                <div class="table-responsive">
                    <table class="table team-table align-middle mb-0" data-members-table>
                        <thead><tr><th>Person</th><th>Department · role</th><th>Access</th><th>Status</th><th>Last sign-in</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                        <tbody>
                            <tr data-member-row="owner" data-search="{{ mb_strtolower(trim($owner->first_name.' '.$owner->last_name).' '.$owner->email.' account owner') }}" data-department="" data-status="Active">
                                <td><div class="team-person"><span class="team-avatar is-dark" aria-hidden="true">{{ $initials($owner) }}</span><div><div class="team-name">{{ trim($owner->first_name.' '.$owner->last_name) }} @if($owner->id === auth()->id())<span class="team-you">you</span>@endif</div><div class="team-email">{{ $owner->email }}</div></div></div></td>
                                <td><span class="team-role is-dark">Account owner</span></td>
                                <td><span class="team-access-all">Everything</span></td>
                                <td><span class="team-status is-success">Active</span></td>
                                <td class="team-muted">{{ $owner->last_login_at?->diffForHumans() ?? '—' }}</td>
                                <td></td>
                            </tr>
                            @foreach($members as $member)
                                @php
                                    [$status, $tone] = $statusOf($member);
                                    $isSelf = $member->id === auth()->id();
                                    $role = $member->businessRole;
                                    $r = $reach[$member->id] ?? null;
                                @endphp
                                <tr data-member-row="{{ $member->id }}" id="member-{{ $member->id }}" data-search="{{ mb_strtolower(trim($member->first_name.' '.$member->last_name).' '.$member->email.' '.($role->name ?? '').' '.($role->department ?? '')) }}" data-department="{{ $role->department ?? '' }}" data-status="{{ $status }}">
                                    <td><div class="team-person"><span class="team-avatar is-{{ $role->color ?? 'secondary' }}" aria-hidden="true">{{ $initials($member) }}</span><div><div class="team-name">{{ trim($member->first_name.' '.$member->last_name) }} @if($isSelf)<span class="team-you">you</span>@endif</div><div class="team-email">{{ $member->email }}</div></div></div></td>
                                    <td>
                                        @if($role?->department)<div class="team-dept">{{ $role->department }}</div>@endif
                                        <span class="team-role is-{{ $role->color ?? 'secondary' }}">{{ $role->name ?? 'No role' }}</span>
                                        @if(is_array($member->business_permissions))<span class="team-tag" title="This person has custom permissions instead of the role's">Custom</span>@endif
                                        @if($member->agentProfile && $member->agentProfile->is_active)<span class="team-tag is-agent" data-agent-badge title="Also works as an agent">Agent</span>@endif
                                    </td>
                                    <td data-member-properties>
                                        @if(!$r)
                                            <span class="team-access-all">All properties</span>
                                        @elseif(!$r['full'] && !$r['units'])
                                            <span class="team-access-none" title="Assign properties, or units through an agent profile">Nothing assigned yet</span>
                                        @else
                                            <div class="team-access">
                                                @foreach(array_slice($r['full'], 0, 2) as $title)<span>{{ $title }}</span>@endforeach
                                                @if(count($r['full']) > 2)<span class="team-more">+{{ count($r['full']) - 2 }} more</span>@endif
                                                @foreach($r['units'] as $title => $n)<span class="is-units">{{ $n }} {{ \Illuminate\Support\Str::plural('unit', $n) }} at {{ $title }}</span>@endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td><span class="team-status is-{{ $tone }}" data-member-status>{{ $status }}</span></td>
                                    <td class="team-muted">{{ $member->last_login_at?->diffForHumans() ?? ($member->invited_at ? 'Invited '.$member->invited_at->diffForHumans() : '—') }}</td>
                                    <td class="text-end text-nowrap">
                                        @businessCan('activity_log', 'view')<a class="btn btn-sm btn-icon btn-light" href="{{ route('activity.index', ['user' => $member->id]) }}" title="What {{ $member->first_name }} opened and changed" aria-label="Activity of {{ $member->first_name }}"><i class="fas fa-history" aria-hidden="true"></i></a>@endbusinessCan
                                        @unless($isSelf)
                                            @if($can['editMembers'])
                                                <button type="button" class="btn btn-sm btn-light" data-open-member="{{ $member->id }}" data-member="{{ json_encode(['id' => $member->id, 'first_name' => $member->first_name, 'last_name' => $member->last_name, 'email' => $member->email, 'mobile' => $member->mobile, 'business_role_id' => $member->business_role_id, 'permissions' => $member->business_permissions, 'complexes' => $assigned[$member->id] ?? [], 'agent' => $member->agentProfile ? ['id' => $member->agentProfile->id, 'name' => $member->agentProfile->full_name, 'active' => (bool) $member->agentProfile->is_active] : null, 'suggested_agent' => ($sa = $suggestedAgents[mb_strtolower($member->email)] ?? null) ? ['id' => $sa->id, 'name' => trim($sa->first_name.' '.$sa->last_name).' ('.$sa->email.')'] : null]) }}">Edit</button>
                                            @endif
                                            @if($can['editMembers'] || ($can['deleteMembers'] && $status === 'Invited'))
                                            <div class="dropdown d-inline-block">
                                                <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-toggle="dropdown" aria-expanded="false" aria-label="More actions for {{ $member->first_name }}"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    @if($can['editMembers'] && $status === 'Invited' && $can['invite'])<li><button type="button" class="dropdown-item" data-member-action="resend" data-id="{{ $member->id }}"><i class="bi bi-envelope me-2" aria-hidden="true"></i>Resend invite</button></li>@endif
                                                    @if($can['editMembers'])
                                                        @if($member->is_suspended)
                                                            <li><button type="button" class="dropdown-item" data-member-action="reactivate" data-id="{{ $member->id }}"><i class="bi bi-play-circle me-2" aria-hidden="true"></i>Reactivate</button></li>
                                                        @else
                                                            <li><button type="button" class="dropdown-item text-warning" data-member-action="suspend" data-id="{{ $member->id }}" data-name="{{ $member->first_name }}"><i class="bi bi-pause-circle me-2" aria-hidden="true"></i>Suspend</button></li>
                                                        @endif
                                                    @endif
                                                    @if($can['deleteMembers'] && $status === 'Invited')<li><hr class="dropdown-divider"></li><li><button type="button" class="dropdown-item text-danger" data-member-action="destroy" data-id="{{ $member->id }}" data-name="{{ $member->first_name }}"><i class="bi bi-trash me-2" aria-hidden="true"></i>Remove invitation</button></li>@endif
                                                </ul>
                                            </div>
                                            @endif
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="team-empty" data-team-empty hidden><td colspan="6">Nobody matches. Change the search or filters.</td></tr>
                        </tbody>
                    </table>
                </div>
                @if($members->isEmpty())<p class="team-muted px-6 py-5 mb-0">No team members yet. Invite someone and choose the role that matches their work.</p>@endif
            </section>
        </div>

        @if($can['viewRoles'])
        <div class="tab-pane fade" id="team-roles" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <p class="text-muted mb-0">Changing a role changes access for everyone who has it, from their next page load.</p>
                @if($can['createRoles'])<button type="button" class="btn btn-light-primary" data-open-role>New role</button>@endif
            </div>
            @php
                $departmentOrder = array_flip(\App\Services\Access\StarterRoles::DEPARTMENTS);
                $byDepartment = $roles->groupBy(fn ($r) => $r->department ?: 'Other')
                    ->sortBy(fn ($group, $name) => $departmentOrder[$name] ?? 50 + ord(mb_substr($name, 0, 1)));
            @endphp
            @foreach($byDepartment as $departmentName => $departmentRoles)
            <section class="mb-8" data-role-department="{{ $departmentName }}">
                <h2 class="fs-6 text-uppercase text-muted fw-bold mb-4" style="letter-spacing:.06em">{{ $departmentName }} <span class="badge badge-light ms-1">{{ $departmentRoles->count() }}</span></h2>
                <div class="row g-5">
                @foreach($departmentRoles->sortBy('name') as $role)
                    <div class="col-md-6 col-xl-4">
                        <div class="card h-100 border-0 shadow-sm" data-role-card="{{ $role->id }}">
                            <div class="card-body p-6 d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3 class="fs-5 mb-0"><span class="badge badge-light-{{ $role->color }} fs-6">{{ $role->name }}</span></h3>
                                    @unless($role->is_active)<span class="badge badge-light-secondary">Inactive</span>@endunless
                                </div>
                                <p class="text-muted fs-7 flex-grow-1">{{ $role->description }}</p>
                                <div class="mb-2">@if($role->limitedToAssignedProperties())<span class="badge badge-light-warning" data-role-scope>Assigned properties only</span>@else<span class="badge badge-light" data-role-scope>All properties</span>@endif</div>
                                <div class="fs-7 mb-4"><strong>{{ $role->assigned_users_count }}</strong> {{ \Illuminate\Support\Str::plural('person', $role->assigned_users_count) }} · {{ collect($role->permissions ?? [])->flatten()->count() }} permissions</div>
                                <div class="fs-8 text-muted mb-4" data-role-menu><i class="fas fa-bars me-1" aria-hidden="true"></i>{{ implode(' · ', $menuOf($role->permissions)) ?: 'Only their profile' }}</div>
                                <div class="d-flex flex-wrap gap-2">
                                    @if($can['editRoles'])
                                        <button type="button" class="btn btn-sm btn-light" data-open-role="{{ $role->id }}" data-role="{{ json_encode(['id' => $role->id, 'name' => $role->name, 'description' => $role->description, 'color' => $role->color, 'permissions' => $role->permissions ?? [], 'property_scope' => $role->property_scope ?? 'all', 'department' => $role->department]) }}">Edit permissions</button>
                                        <button type="button" class="btn btn-sm btn-light" data-role-action="toggle" data-id="{{ $role->id }}">{{ $role->is_active ? 'Deactivate' : 'Activate' }}</button>
                                    @endif
                                    @if($can['deleteRoles'] && $role->assigned_users_count === 0)<button type="button" class="btn btn-sm btn-light-danger" data-role-action="delete" data-id="{{ $role->id }}" data-name="{{ $role->name }}">Delete</button>@endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>
            </section>
            @endforeach
        </div>
        @endif
    </div>
</div>
</div>

{{-- Permission matrix, shared by the member and role dialogs: sections follow the left menu, every tick box says what it allows --}}
<template id="permission-matrix-template">
    <div class="perm-preview" data-menu-preview-box>
        <i class="fas fa-bars" aria-hidden="true"></i>
        <div><strong>In their menu:</strong> <span data-menu-preview>nothing yet</span><small>Each person also only sees the properties their role and assignments allow.</small></div>
    </div>
    <div class="perm-matrix" style="max-height: 52vh; overflow-y: auto">
        @foreach($sections as $section)
            <section class="perm-section" data-perm-section="{{ $section['key'] }}" data-shows='@json($section['shows'])'>
                <header>
                    <i class="fas {{ $section['icon'] }}" aria-hidden="true"></i>
                    <strong>{{ $section['label'] }}</strong>
                    <small>{{ $section['where'] }}</small>
                    <span class="perm-menu-badge" data-section-badge>Not in menu</span>
                </header>
                @foreach($section['groups'] as $group)
                    <div class="perm-group" data-matrix-group="{{ $group['group'] }}">
                        <div class="perm-group-head"><span>{{ $group['label'] }}</span><small>{{ $group['description'] }}</small></div>
                        <div class="perm-actions">
                            @foreach($group['actions'] as $action)
                                @php $denied = $actorPermissions !== null && !in_array($action['key'], $actorPermissions[$group['group']] ?? [], true); @endphp
                                <label class="perm-action {{ $denied ? 'is-denied' : '' }}">
                                    <input class="form-check-input" type="checkbox" data-permission="{{ $group['group'] }}" value="{{ $action['key'] }}"
                                        @if($action['key'] !== 'view' && collect($group['actions'])->contains('key', 'view')) data-needs-view @endif
                                        @if($denied) disabled title="You don't hold this permission yourself, so you can't give it" @endif>
                                    <span><strong>{{ $action['label'] }}</strong><small>{{ $action['help'] }}@if($denied) <em>(you don't hold this yourself)</em>@endif</small></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>
        @endforeach
    </div>
</template>

<div class="modal fade" id="member-modal" tabindex="-1" aria-hidden="true" aria-labelledby="member-modal-title">
    <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
        <form data-member-form novalidate>
            <div class="modal-header"><h2 class="modal-title fs-4" id="member-modal-title">Invite team member</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id">
                <div class="row g-4 mb-4">
                    <div class="col-md-4"><label class="form-label required" for="member-first">First name</label><input class="form-control" id="member-first" name="first_name" required maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label required" for="member-last">Last name</label><input class="form-control" id="member-last" name="last_name" required maxlength="100"></div>
                    <div class="col-md-4"><label class="form-label required" for="member-email">Email</label><input class="form-control" id="member-email" name="email" type="email" required maxlength="255"><div class="form-text" data-email-help>They'll get a link to set their password.</div></div>
                    <div class="col-md-4"><label class="form-label required" for="member-mobile">Phone</label><input class="form-control" id="member-mobile" name="mobile" type="tel" required maxlength="20" placeholder="+254 7…"></div>
                    <div class="col-md-4"><label class="form-label required" for="member-role">Role</label>
                        <select class="form-select" id="member-role" name="business_role_id" required>
                            <option value="">Choose a role</option>
                            @foreach($roles->where('is_active', true)->groupBy(fn ($r) => $r->department ?: 'Other')->sortKeys() as $departmentName => $departmentRoles)
                                <optgroup label="{{ $departmentName }}">@foreach($departmentRoles->sortBy('name') as $role)<option value="{{ $role->id }}" data-scope="{{ $role->property_scope ?? 'all' }}">{{ $role->name }}</option>@endforeach</optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end"><label class="form-check form-switch form-check-custom mb-2"><input class="form-check-input" type="checkbox" name="custom_permissions" value="1" data-custom-toggle><span class="form-check-label">Give this person custom permissions instead of the role's</span></label></div>
                </div>
                @if($can['assignProperties'] || $can['manageAgents'])
                <div class="row g-4 mb-4">
                    @if($can['assignProperties'])
                    <div class="col-md-8" data-properties-field>
                        <div class="d-flex justify-content-between align-items-baseline">
                            <label class="form-label mb-1" for="member-properties">Properties they manage <span class="text-muted fw-normal" data-properties-count></span></label>
                            <span class="fs-8 text-muted" data-scope-help></span>
                        </div>
                        @if(!$hasProperties)
                            <p class="text-muted fs-7 mb-0">There are no properties to assign yet.</p>
                        @else
                            <select class="form-select" id="member-properties" multiple data-properties-select data-url="{{ route('business.team.properties') }}" data-placeholder="Search properties by name, reference or county"></select>
                            <div class="form-text">Type to search. To give someone every property, use a role with <em>All properties</em> access instead of selecting them one by one.</div>
                        @endif
                    </div>
                    @endif
                    @if($can['manageAgents'])
                    <div class="col-md-4" data-agent-field>
                        <label class="form-label mb-1" for="member-agent-mode">CRM agent profile</label>
                        <div class="fs-7 mb-2" data-agent-current hidden></div>
                        <select class="form-select" id="member-agent-mode" data-agent-mode></select>
                        <div class="mt-3" data-agent-link hidden>
                            <label class="form-label fs-7 mb-1" for="member-agent-search">Agent to link</label>
                            <select class="form-select" id="member-agent-search" data-agent-search data-url="{{ route('agents.search') }}" data-placeholder="Search agents without a sign-in"></select>
                        </div>
                        <div class="form-text">Agents can have leads and follow-up tasks assigned to them, see them under My tasks, and see their commission on My agent dashboard.</div>
                    </div>
                    @endif
                </div>
                @endif
                <div data-member-matrix hidden></div>
                <div class="alert alert-danger d-none mt-4" role="alert" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-submit>Send invitation</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="role-modal" tabindex="-1" aria-hidden="true" aria-labelledby="role-modal-title">
    <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content">
        <form data-role-form novalidate>
            <div class="modal-header"><h2 class="modal-title fs-4" id="role-modal-title">New role</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id">
                <div class="row g-4 mb-4">
                    <div class="col-md-4"><label class="form-label required" for="role-name">Role name</label><input class="form-control" id="role-name" name="name" required maxlength="255"></div>
                    <div class="col-md-3"><label class="form-label" for="role-color">Colour</label>
                        <select class="form-select" id="role-color" name="color">@foreach(['primary' => 'Blue', 'success' => 'Green', 'warning' => 'Amber', 'danger' => 'Red', 'info' => 'Teal', 'dark' => 'Grey'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-5" data-template-field><label class="form-label" for="role-template">Start from a template</label>
                        <select class="form-select" id="role-template"><option value="">Blank role</option>@foreach($templates as $template)<option value="{{ $template['key'] }}">{{ $template['name'] }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-4"><label class="form-label" for="role-department">Department</label>
                        <input class="form-control" id="role-department" name="department" list="role-departments" maxlength="60" placeholder="e.g. Finance">
                        <datalist id="role-departments">@foreach(collect(\App\Services\Access\StarterRoles::DEPARTMENTS)->merge($roles->pluck('department'))->filter()->unique() as $d)<option value="{{ $d }}">@endforeach</datalist>
                        <div class="form-text">Groups roles on this page. Leave blank to pick one from the role name.</div>
                    </div>
                    <div class="col-md-8"><label class="form-label required" for="role-description">What this role does</label><input class="form-control" id="role-description" name="description" required maxlength="1000"></div>
                    <div class="col-md-4"><label class="form-label" for="role-scope">Property access</label>
                        <select class="form-select" id="role-scope" name="property_scope">
                            <option value="all" @if($actorLimited) disabled @endif>All properties</option>
                            <option value="assigned">Only properties assigned to the member</option>
                        </select>
                        <div class="form-text">Limited members see only their properties and the units, tenants, charges, expenses, history and leads that belong to them.</div>
                    </div>
                </div>
                <div data-role-matrix></div>
                <div class="alert alert-danger d-none mt-4" role="alert" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-submit>Save role</button></div>
        </form>
    </div></div>
</div>

<script src="{{ app('asset_versioned')('js/pages/business/team.js') }}"></script>
@endsection
