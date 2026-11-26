@extends('pages.business.layouts.app')
@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'My profile'])
@php
    $avatarUrl = $user->avatar_url;
    $initials = mb_strtoupper(mb_substr($user->first_name ?? '', 0, 1).mb_substr($user->last_name ?? '', 0, 1)) ?: '?';
    $fullName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->email;
    $isOwner = $user->isBusinessOwner();
    $role = $user->businessRole;
    $roleName = $isOwner ? 'Account owner' : ($role->name ?? 'No role');
    $roleColor = $isOwner ? 'dark' : ($role->color ?? 'secondary');
    $limited = $user->limitedToAssignedProperties();
@endphp
<style>
    .profile-hero { display: flex; flex-wrap: wrap; align-items: center; gap: 24px; }
    .profile-hero__avatar { width: 96px; height: 96px; border-radius: 50%; overflow: hidden; flex: 0 0 96px; display: grid; place-items: center;
        background: rgba(4, 200, 200, .12); color: #029e9e; font-size: 2rem; font-weight: 700; box-shadow: 0 0 0 4px #fff, 0 0 0 5px rgba(4, 200, 200, .35); }
    .profile-hero__avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-hero__name { font-size: 1.6rem; font-weight: 700; margin: 0 0 4px; color: #0f2a2e; }
    .profile-hero__meta { display: flex; flex-wrap: wrap; gap: 6px 18px; color: #6b7c80; font-size: .9rem; }
    .profile-hero__actions { margin-left: auto; display: flex; flex-wrap: wrap; gap: 8px; }
    .profile-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin: 0; }
    .profile-facts dt { color: #6b7c80; font-size: .8rem; font-weight: 600; margin-bottom: 4px; }
    .profile-facts dd { margin: 0; font-weight: 600; color: #0f2a2e; }
    .profile-status { display: inline-flex; align-items: center; gap: 6px; }
    .profile-status::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #50cd89; }
    .profile-perm { display: flex; gap: 10px; padding: 10px 0; border-bottom: 1px dashed #e4e6ef; font-size: .9rem; }
    .profile-perm:last-child { border-bottom: 0; }
    .profile-perm strong { flex: 0 0 45%; color: #0f2a2e; }
    .profile-perm span { color: #6b7c80; }
    @media (max-width: 575px) { .profile-hero__actions { margin-left: 0; width: 100%; } .profile-perm { flex-direction: column; gap: 2px; } }
</style>
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
<div class="container-xxl" id="kt_content_container" data-profile-page>

    {{-- Who I am --}}
    <section class="tenant-panel tenant-panel--padded mb-6" data-profile-hero>
        <div class="profile-hero">
            <div class="profile-hero__avatar" aria-hidden="true">
                @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="">@else{{ $initials }}@endif
            </div>
            <div>
                <h1 class="profile-hero__name">{{ $fullName }}</h1>
                <div class="profile-hero__meta">
                    <span>{{ $user->email }}</span>
                    <span><span class="badge badge-light-{{ $roleColor }}">{{ $roleName }}</span>@if($isPlatformAdmin) <span class="badge badge-light-primary">Platform administrator</span>@endif</span>
                    <span>{{ $business->business_name ?? '' }}</span>
                </div>
            </div>
            <div class="profile-hero__actions">
                @if($isPlatformAdmin)<a class="btn btn-sm btn-light-primary" href="{{ route('platform.index') }}">Platform administration</a>@endif
                @if($canManageTeam)<a class="btn btn-sm btn-light" href="{{ route('business.team.index') }}">Team &amp; roles</a>@endif
                @if($canBusinessSettings)<a class="btn btn-sm btn-light" href="{{ url('/business_account') }}">Business settings</a>@endif
            </div>
        </div>
    </section>

    <div class="row g-6">
        <div class="col-xl-8">
            {{-- Personal details --}}
            <section class="tenant-panel tenant-panel--padded mb-6">
                <h2 class="fs-4 fw-bolder mb-1">Personal details</h2>
                <p class="text-muted fs-7 mb-6">How you appear to your team, and on leads and tasks assigned to you.</p>
                <form method="POST" action="{{ route('business.profile.update') }}" enctype="multipart/form-data" data-profile-form>
                    @csrf
                    @method('PUT')
                    @if($errors->any())
                        <div class="alert alert-danger" role="alert">@foreach($errors->all() as $m)<div>{{ $m }}</div>@endforeach</div>
                    @endif
                    <div class="d-flex flex-wrap align-items-center gap-5 mb-7">
                        <div class="profile-hero__avatar" data-avatar-preview>
                            @if($avatarUrl)<img src="{{ $avatarUrl }}" alt="Your photo">@else<span>{{ $initials }}</span>@endif
                        </div>
                        <div class="flex-grow-1" style="min-width: 220px">
                            <label class="form-label" for="profile-avatar">Photo</label>
                            <input class="form-control" type="file" id="profile-avatar" name="avatar" accept=".png,.jpg,.jpeg" aria-describedby="profile-avatar-help">
                            <div class="form-text" id="profile-avatar-help">A square PNG or JPG works best, up to 4 MB. You'll see a preview before you save.</div>
                            @if($avatarUrl)<label class="form-check form-check-sm mt-2"><input class="form-check-input" type="checkbox" name="avatar_remove" value="1" data-avatar-remove><span class="form-check-label fs-7">Remove my photo</span></label>@endif
                        </div>
                    </div>
                    <div class="row g-5">
                        <div class="col-md-6"><label class="form-label required" for="profile-first">First name</label><input class="form-control form-control-solid" id="profile-first" name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="100" autocomplete="given-name"></div>
                        <div class="col-md-6"><label class="form-label required" for="profile-last">Last name</label><input class="form-control form-control-solid" id="profile-last" name="last_name" value="{{ old('last_name', $user->last_name) }}" required maxlength="100" autocomplete="family-name"></div>
                        <div class="col-md-6"><label class="form-label required" for="profile-mobile">Phone</label><input class="form-control form-control-solid" id="profile-mobile" name="mobile" type="tel" value="{{ old('mobile', $user->mobile) }}" required maxlength="20" autocomplete="tel"></div>
                        <div class="col-md-6"><span class="form-label d-block">Sign-in email</span><div class="form-control form-control-solid text-muted" aria-readonly="true">{{ $user->email }}</div><div class="form-text">Change it under Sign-in below.</div></div>
                    </div>
                    <div class="d-flex justify-content-end mt-7"><button type="submit" class="btn btn-primary">Save my details</button></div>
                </form>
            </section>

            {{-- Security --}}
            <section class="tenant-panel tenant-panel--padded mb-6" data-profile-security>
                <h2 class="fs-4 fw-bolder mb-1">Sign-in &amp; security</h2>
                <p class="text-muted fs-7 mb-6">Every sign-in needs your password and a 6-digit code sent to your email.</p>
                <dl class="profile-facts">
                    <div><dt>Two-step sign-in</dt><dd><span class="profile-status">On, by email</span></dd></div>
                    <div><dt>Last sign-in</dt><dd>{{ $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->format('j M Y, H:i') : 'Not recorded' }}</dd></div>
                    <div><dt>Account created</dt><dd>{{ $user->created_at?->format('j M Y') ?? '—' }}</dd></div>
                </dl>
                <p class="text-muted fs-7 mt-5 mb-0">After {{ \App\Http\Controllers\Auth\OtpController::MAX_CODE_ATTEMPTS }} wrong codes in a row the account is locked until {{ $isOwner ? 'the platform administrator' : 'your account owner' }} restores it.</p>

                <div class="separator separator-dashed my-6"></div>
                <h3 class="fs-6 fw-bold mb-0">Email and password</h3>
                @include('pages.business.account.settings.sign_in', ['embedded' => true])
            </section>
        </div>

        <div class="col-xl-4">
            {{-- Access --}}
            <section class="tenant-panel tenant-panel--padded mb-6" data-my-access>
                <h2 class="fs-4 fw-bolder mb-5">My access</h2>
                <dl class="mb-0">
                    <dt class="text-muted fs-7 fw-semibold">Role</dt>
                    <dd class="mb-4">
                        <span class="badge badge-light-{{ $roleColor }}">{{ $roleName }}</span>
                        @if(!$isOwner && is_array($user->business_permissions))<span class="badge badge-light ms-1">Custom permissions</span>@endif
                        @if(!$isOwner && $role?->description)<div class="text-muted fs-7 mt-2">{{ $role->description }}</div>@endif
                        @if($isOwner)<div class="text-muted fs-7 mt-2">You can open every section, including modules, payment methods and team roles.</div>@endif
                    </dd>
                    <dt class="text-muted fs-7 fw-semibold">Properties</dt>
                    <dd class="mb-0" data-my-properties>
                        {{ !$isOwner && $limited ? 'Only properties assigned to you' : 'All properties' }}
                        @if($managed->isNotEmpty())
                            <ul class="list-unstyled mt-2 mb-0">
                                @foreach($managed as $complex)<li><a href="{{ route('complex.show', $complex->slug) }}">{{ $complex->title }}</a></li>@endforeach
                            </ul>
                        @elseif($limited)
                            <div class="text-warning fs-7 mt-1">No properties have been assigned to you yet. Ask an administrator to assign the ones you manage.</div>
                        @endif
                    </dd>
                </dl>
                @unless($isOwner)
                    <h3 class="fs-6 fw-bold mt-6 mb-2">In my menu</h3>
                    <p class="fs-7 mb-0">{{ implode(' · ', $menu) ?: 'Only my profile' }}</p>
                    <h3 class="fs-6 fw-bold mt-5 mb-2">What I can do</h3>
                    <div>
                        @forelse($sections as $section)
                            <div class="profile-perm"><strong>{{ $section['label'] }}</strong><span>@foreach($section['actions'] as $a)<span title="{{ $a['help'] }}">{{ $a['label'] }}</span>@if(!$loop->last), @endif @endforeach</span></div>
                        @empty
                            <p class="text-muted fs-7 mb-0">Your role has no section access yet.</p>
                        @endforelse
                    </div>
                @endunless
            </section>

            {{-- CRM agent --}}
            <section class="tenant-panel tenant-panel--padded" data-my-agent>
                <h2 class="fs-4 fw-bolder mb-4">CRM agent profile</h2>
                @if($agent)
                    <div class="d-flex align-items-center gap-2 mb-4">
                        <span class="badge badge-light-{{ $agent->is_active ? 'success' : 'secondary' }}">{{ $agent->is_active ? 'Active agent' : 'Inactive' }}</span>
                        <span class="text-muted fs-7">{{ ucfirst($agent->agent_type) }}</span>
                    </div>
                    <dl class="profile-facts mb-5" style="grid-template-columns: 1fr 1fr">
                        <div><dt>Commission</dt><dd>{{ rtrim(rtrim(number_format((float) $agent->commission_rate, 2), '0'), '.') }}%</dd></div>
                        <div><dt>Sales target</dt><dd>{{ number_format((float) $agent->sales_target) }}</dd></div>
                        <div><dt>Open leads</dt><dd>{{ $agentStats['open_leads'] }}</dd></div>
                        <div><dt>Open tasks</dt><dd>{{ $agentStats['open_tasks'] }}</dd></div>
                    </dl>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-primary" href="{{ route('business.my_agent') }}">My agent dashboard</a>
                        @businessCan('task_management', 'view')<a class="btn btn-sm btn-light-primary" href="{{ url('/agents/tasks') }}">My tasks</a>@endbusinessCan
                    </div>
                @else
                    <p class="text-muted fs-7">You don't work as a CRM agent, so leads and follow-up tasks can't be assigned to you.</p>
                    @if($canCreateAgent)
                        <form method="POST" action="{{ route('business.profile.agent') }}">@csrf<button type="submit" class="btn btn-sm btn-light-primary">Create my agent profile</button></form>
                    @else
                        <p class="text-muted fs-7 mb-0">An administrator can make you an agent from Team &amp; roles.</p>
                    @endif
                @endif
            </section>
        </div>
    </div>
</div>
</div>
<script>
    // Preview a chosen photo before saving; "Remove my photo" clears the preview.
    (function () {
        var input = document.getElementById('profile-avatar');
        var preview = document.querySelector('[data-avatar-preview]');
        var remove = document.querySelector('[data-avatar-remove]');
        var original = preview ? preview.innerHTML : '';
        if (!input || !preview) return;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file || !/^image\//.test(file.type)) { preview.innerHTML = original; return; }
            var url = URL.createObjectURL(file);
            preview.innerHTML = '<img alt="Your new photo (not saved yet)">';
            preview.firstChild.src = url;
            if (remove) remove.checked = false;
        });
        if (remove) remove.addEventListener('change', function () {
            if (remove.checked) { input.value = ''; preview.innerHTML = '<span>{{ $initials }}</span>'; } else { preview.innerHTML = original; }
        });
    })();
</script>
@endsection
