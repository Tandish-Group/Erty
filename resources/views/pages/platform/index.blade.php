@extends('pages.business.layouts.app')

@section('content')
@include('pages.platform.partials.header')
@php
    $statusBadge = ['active' => ['badge-light-success', 'Can sign in'], 'locked' => ['badge-light-warning', 'Locked out'], 'invited' => ['badge-light-info', 'Invited']];
    $canTeam = auth()->user()->hasBusinessPermission('subaccount_management', 'view') || auth()->user()->hasBusinessPermission('role_management', 'view');
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container" data-platform-overview>
        @include('pages.platform.partials.nav', [
            'title' => e($business->business_name ?: config('app.name')) . ' team',
            'intro' => 'Everyone who works here, what they can open and whether they can sign in. Invite people and change their roles in Team & roles; make someone a support agent to let them answer support tickets.',
            'actions' => $canTeam ? '<a href="' . e(route('business.team.index')) . '" class="btn btn-primary">Team &amp; roles</a>' : null,
        ])

        <div class="tenant-metrics mb-7">
            <div><span>Staff</span><strong>{{ number_format($counts['staff']) }}</strong><small>Besides the owner</small></div>
            <div><span>Locked out</span><strong class="{{ $counts['locked'] ? 'text-warning' : '' }}">{{ number_format($counts['locked']) }}</strong><small>{{ $counts['locked'] ? 'Need their access restored' : 'Everyone can sign in' }}</small></div>
            <div><span>Invitations</span><strong>{{ number_format($counts['invited']) }}</strong><small>Invited, not joined yet</small></div>
            <div><span>CRM agents</span><strong>{{ number_format($counts['agents']) }}</strong><small>Can be given leads and tasks</small></div>
        </div>

        <div class="row g-6">
            <div class="col-xl-8">
                <section class="tenant-panel tenant-panel--padded" data-platform-people>
                    <form method="get" class="platform-filters mb-5" role="search">
                        <label for="people_search" class="visually-hidden">Search people</label>
                        <input id="people_search" type="search" name="q" value="{{ $search }}" class="form-control form-control-solid" placeholder="Name or email">
                        <label for="people_role" class="visually-hidden">Role</label>
                        <select id="people_role" name="role" class="form-select form-select-solid" onchange="this.form.submit()">
                            <option value="">All roles</option>
                            @foreach($roles as $role)<option value="{{ $role->id }}" @selected($roleFilter === $role->id)>{{ $role->name }}</option>@endforeach
                        </select>
                        <label for="people_status" class="visually-hidden">Status</label>
                        <select id="people_status" name="status" class="form-select form-select-solid" onchange="this.form.submit()">
                            <option value="">Anyone</option>
                            <option value="active" @selected($statusFilter === 'active')>Can sign in</option>
                            <option value="locked" @selected($statusFilter === 'locked')>Locked out</option>
                            <option value="invited" @selected($statusFilter === 'invited')>Invited</option>
                        </select>
                        <button class="btn btn-light-primary">Search</button>
                        @if($filtered)<a href="{{ route('platform.index') }}" class="btn btn-light">Clear</a>@endif
                    </form>

                    @if($people->isEmpty())
                        <div class="tenant-empty"><i class="fas fa-user-friends" aria-hidden="true"></i><h3>Nobody matches</h3><p>Try another name, role or status.</p></div>
                    @else
                    <div class="tenant-table-scroll" role="region" aria-label="Staff" tabindex="0">
                        <table class="tenant-table">
                            <thead><tr><th>Person</th><th class="d-none d-sm-table-cell">Role</th><th class="d-none d-md-table-cell">Properties</th><th class="d-none d-lg-table-cell">Last sign-in</th><th>Status</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
                            <tbody>
                            @foreach($people as $person)
                                @php
                                    [$tone, $label] = $statusBadge[$statusOf($person)];
                                    $isOwner = (int) $person->id === (int) $ownerId;
                                    $isMe = (int) $person->id === (int) auth()->id();
                                    $limited = !$isOwner && $person->businessRole?->property_scope === 'assigned';
                                @endphp
                                <tr data-person-row="{{ $person->id }}">
                                    <td>
                                        <strong>{{ trim($person->first_name . ' ' . $person->last_name) ?: '—' }}@if($isMe) <span class="text-muted fw-normal">(you)</span>@endif</strong>
                                        <small>{{ $person->email }}</small>
                                        <small class="d-sm-none mt-1">{{ $isOwner ? 'Owner' : ($person->businessRole->name ?? 'No role') }}</small>
                                    </td>
                                    <td class="d-none d-sm-table-cell">
                                        @if($isOwner)<span class="badge badge-light-dark">Owner</span>
                                        @elseif($person->businessRole)<span class="badge badge-light-{{ $person->businessRole->color ?? 'secondary' }}">{{ $person->businessRole->name }}</span>
                                        @else<span class="text-muted">No role</span>@endif
                                        @if($person->agentProfile?->is_active)<span class="badge badge-light ms-1" title="Also works as a CRM agent">Agent</span>@endif
                                        @if($person->roles->contains('name', 'admin'))<span class="badge badge-light-danger ms-1" title="Platform administrator: also answers support tickets">Admin</span>
                                        @elseif($person->roles->contains('name', 'support'))<span class="badge badge-light-info ms-1" title="Answers support tickets in the support inbox">Support</span>@endif
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        @if($limited)<span class="{{ $person->managed_complexes_count ? '' : 'text-warning' }}">{{ $person->managed_complexes_count }} assigned</span>
                                        @else<span class="text-muted">All</span>@endif
                                    </td>
                                    <td class="d-none d-lg-table-cell text-nowrap">{{ $person->last_login_at ? \Carbon\Carbon::parse($person->last_login_at)->diffForHumans() : 'Never' }}</td>
                                    <td class="text-nowrap"><span class="badge {{ $tone }}">{{ $label }}</span></td>
                                    <td class="text-end text-nowrap">
                                        @if($statusOf($person) === 'locked')
                                            <form method="post" action="{{ route('platform.people.restore', $person->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light-primary">Restore access</button></form>
                                        @endif
                                        @php $isAdminPerson = $person->roles->contains('name', 'admin'); $answers = $person->roles->contains('name', 'support'); @endphp
                                        @if(($canTeam && !$isOwner) || !$isAdminPerson)
                                            <div class="dropdown d-inline-block">
                                                <button type="button" class="btn btn-sm btn-icon btn-light" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ trim($person->first_name . ' ' . $person->last_name) ?: $person->email }}"><i class="fas fa-ellipsis-h" aria-hidden="true"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    @if($canTeam && !$isOwner)<li><a class="dropdown-item" href="{{ route('business.team.index') }}#member-{{ $person->id }}">Edit role and properties</a></li>@endif
                                                    @unless($isAdminPerson)
                                                        <li>
                                                            <form method="post" action="{{ route('platform.people.support', $person->id) }}">
                                                                @csrf<input type="hidden" name="enabled" value="{{ $answers ? 0 : 1 }}">
                                                                <button class="dropdown-item {{ $answers ? 'text-danger' : '' }}">{{ $answers ? 'Stop answering support tickets' : 'Make support agent (answers support tickets)' }}</button>
                                                            </form>
                                                        </li>
                                                    @endunless
                                                </ul>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted fs-8 mt-4 mb-0">{{ $people->count() }} of {{ $counts['staff'] + 1 }} people shown.</p>
                    @endif
                </section>
            </div>

            <div class="col-xl-4 d-flex flex-column gap-6">
                <section class="tenant-panel tenant-panel--padded" data-platform-roles>
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
                        <div>
                            <h2 class="fs-4 fw-bolder mb-1">Roles</h2>
                            <p class="text-muted fs-7 mb-0">How many people hold each role.</p>
                        </div>
                        @if($missingStarterRoles)
                            <form method="post" action="{{ route('platform.roles') }}">@csrf<button class="btn btn-sm btn-light-primary text-nowrap" title="Not added yet: {{ implode(', ', $missingStarterRoles) }}">Add {{ count($missingStarterRoles) }} standard</button></form>
                        @endif
                    </div>
                    <ul class="platform-list">
                        @forelse($roles as $role)
                            <li>
                                <a class="platform-row-link" href="{{ route('platform.index', ['role' => $role->id]) }}">
                                    <strong><span class="bullet bullet-dot bg-{{ $role->color ?? 'secondary' }} me-2"></span>{{ $role->name }}</strong>
                                    <small>{{ $role->property_scope === 'assigned' ? 'Assigned properties only' : 'All properties' }}@unless($role->is_active) · switched off @endunless</small>
                                </a>
                                <span class="badge badge-light">{{ $role->members }}</span>
                            </li>
                        @empty
                            <li class="text-muted">No roles yet.</li>
                        @endforelse
                    </ul>
                </section>

                <section class="tenant-panel tenant-panel--padded" data-platform-modules>
                    <h2 class="fs-4 fw-bolder mb-1">Modules</h2>
                    <p class="text-muted fs-7 mb-2">Optional parts of {{ config('app.name') }}. Switching one off keeps its records.</p>
                    @foreach($modules as $module)
                        <form method="post" action="{{ route('platform.modules') }}" class="platform-switch" data-module-switch>
                            @csrf
                            <input type="hidden" name="module" value="{{ $module['key'] }}">
                            <input type="hidden" name="enabled" value="{{ $module['enabled'] ? 0 : 1 }}">
                            <label for="module_switch_{{ $module['key'] }}">
                                <span class="fw-bold d-block">{{ $module['label'] }}</span>
                                <span class="text-muted fs-7">{{ $module['description'] }}</span>
                            </label>
                            <div class="form-check form-switch form-check-custom form-check-solid flex-shrink-0 align-items-center gap-2">
                                <span class="fs-7 fw-bold {{ $module['enabled'] ? 'text-success' : 'text-muted' }}" aria-hidden="true">{{ $module['enabled'] ? 'On' : 'Off' }}</span>
                                <input class="form-check-input" type="checkbox" role="switch" id="module_switch_{{ $module['key'] }}" @checked($module['enabled'])
                                       data-confirm="Switch {{ $module['label'] }} {{ $module['enabled'] ? 'off' : 'on' }}?">
                            </div>
                            <noscript><button class="btn btn-sm btn-light">{{ $module['enabled'] ? 'Switch off' : 'Switch on' }}</button></noscript>
                        </form>
                    @endforeach
                </section>

                <section class="tenant-panel tenant-panel--padded">
                    <h2 class="fs-5 fw-bolder mb-3">At a glance</h2>
                    <dl class="row mb-0 fs-7">
                        <dt class="col-7 text-muted fw-semibold mb-2">Properties</dt><dd class="col-5 text-end fw-bold mb-2">{{ number_format($glance['properties']) }}</dd>
                        <dt class="col-7 text-muted fw-semibold mb-2">Units</dt><dd class="col-5 text-end fw-bold mb-2">{{ number_format($glance['units']) }}</dd>
                        <dt class="col-7 text-muted fw-semibold mb-2">Active tenancies</dt><dd class="col-5 text-end fw-bold mb-2">{{ number_format($glance['tenancies']) }}</dd>
                        <dt class="col-7 text-muted fw-semibold">Listings live</dt><dd class="col-5 text-end fw-bold mb-0">{{ number_format($glance['listings']) }}</dd>
                    </dl>
                </section>
            </div>
        </div>
    </div>
</div>
<script>
    // Module switches: confirm, then save; put the switch back if cancelled.
    document.querySelectorAll('[data-module-switch] input[role="switch"]').forEach(function (input) {
        input.addEventListener('change', function () {
            if (window.confirm(input.dataset.confirm)) { input.disabled = true; input.form.submit(); }
            else { input.checked = !input.checked; }
        });
    });
</script>
@endsection
