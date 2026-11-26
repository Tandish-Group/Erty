@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'Maintenance request'])
@php
    $m = $maintenance;
    $S = \App\Models\MaintenanceRequest::STATUSES;
    $statusBadge = ['open' => 'badge-light-warning', 'assigned' => 'badge-light-primary', 'in_progress' => 'badge-light-info', 'completed' => 'badge-light-success', 'closed' => 'badge-light-dark', 'cancelled' => 'badge-light'];
    $priorityBadge = ['urgent' => 'badge-danger', 'high' => 'badge-light-danger', 'normal' => 'badge-light-primary', 'low' => 'badge-light'];
    $profile = $m->tenancy?->tenantProfile;
    $tenantName = $profile ? ($profile->tenant_type === 'Business' ? ($profile->business_name ?: $profile->tenant_name) : $profile->tenant_name) : null;
    $cur = $m->tenancy?->currency ?: default_currency();
    $money = fn ($v) => money($v, $cur);
    $person = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : '—';
    $canBill = auth()->user()->hasBusinessPermission('tenant_management', 'billing');
@endphp
<div class="content d-flex flex-column flex-column-fluid tenant-workspace" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Maintenance · {{ $m->reference }}</span>
                <h1>{{ $m->title }}</h1>
                <p>
                    <span class="badge {{ $statusBadge[$m->status] ?? 'badge-light' }}" data-maintenance-status="{{ $m->status }}">{{ $S[$m->status] ?? $m->status }}</span>
                    <span class="badge {{ $priorityBadge[$m->priority] ?? 'badge-light' }}">{{ \App\Models\MaintenanceRequest::PRIORITIES[$m->priority] ?? $m->priority }} priority</span>
                    <span class="ms-2">{{ \App\Models\MaintenanceRequest::CATEGORIES[$m->category] ?? $m->category }} · {{ $m->complex?->title }}@if($m->unit_label) · {{ $m->unit_label }}@endif</span>
                </p>
            </div>
            <div class="tenant-heading-actions"><a href="{{ route('maintenance.index') }}" class="btn btn-light">← All requests</a></div>
        </header>

        @if(session('success'))<div class="alert alert-light-success fs-7" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger fs-7" role="alert">{{ $errors->first() }}</div>@endif

        <div class="row g-7">
            <div class="col-xl-7">
                {{-- What to do next --}}
                @if($canWork || $canManage)
                <section class="tenant-panel pt-6 mb-7" data-maintenance-actions>
                    <h2 class="fs-5 fw-bolder mb-4">Next step</h2>
                    @if($m->isOpen())
                        @if($canManage)
                        <form method="post" action="{{ route('maintenance.act', $m->reference) }}" class="row g-3 align-items-end mb-5">
                            @csrf <input type="hidden" name="action" value="assign">
                            <div class="col-md-5"><label class="form-label" for="mr_assign">{{ $m->assigned_user_id ? 'Reassign to' : 'Assign to' }}</label>
                                <select class="form-select" id="mr_assign" name="assigned_user_id" required><option value="">Choose…</option>@foreach($assignees as $a)<option value="{{ $a['id'] }}" @selected($m->assigned_user_id == $a['id'])>{{ $a['name'] }}{{ $a['is_agent'] ? ' (agent)' : '' }}</option>@endforeach</select></div>
                            <div class="col-md-5"><label class="form-label" for="mr_assign_note">Instructions (optional)</label><input type="text" class="form-control" id="mr_assign_note" name="note" maxlength="1000"></div>
                            <div class="col-md-2"><button class="btn btn-light-primary w-100" type="submit">Assign</button></div>
                        </form>
                        @endif
                        @if($canWork)
                            @if($m->status !== 'in_progress')
                            <form method="post" action="{{ route('maintenance.act', $m->reference) }}" class="d-flex gap-3 align-items-end mb-5">
                                @csrf <input type="hidden" name="action" value="start">
                                <div class="flex-grow-1"><label class="form-label" for="mr_start_note">Starting work (optional note)</label><input type="text" class="form-control" id="mr_start_note" name="note" maxlength="1000" placeholder="e.g. Plumber booked for Tuesday"></div>
                                <button class="btn btn-light-primary" type="submit">Mark in progress</button>
                            </form>
                            @endif
                            <form method="post" action="{{ route('maintenance.act', $m->reference) }}" class="border rounded p-5" data-maintenance-complete>
                                @csrf <input type="hidden" name="action" value="complete">
                                <h3 class="fs-6 fw-bolder mb-3">Mark as completed</h3>
                                <div class="row g-3">
                                    <div class="col-12"><label class="form-label required" for="mr_resolution">What was done</label><textarea class="form-control" id="mr_resolution" name="resolution" rows="2" maxlength="2000" required></textarea></div>
                                    <div class="col-md-5"><label class="form-label" for="mr_cost">Cost ({{ $cur }})</label><input type="number" step="0.01" min="0" class="form-control" id="mr_cost" name="cost"></div>
                                    <div class="col-md-7"><span class="form-label d-block">Who pays</span>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cost_borne_by" value="landlord" checked> <span class="form-check-label">Landlord (property expense)</span></label>
                                        <label class="form-check form-check-inline"><input class="form-check-input" type="radio" name="cost_borne_by" value="tenant" @disabled(!$m->tenancy_id)> <span class="form-check-label">Tenant (charged to their account)</span></label>
                                    </div>
                                </div>
                                <div class="text-end mt-4"><button class="btn btn-success" type="submit">Mark completed</button></div>
                            </form>
                        @endif
                    @elseif($m->status === 'completed')
                        <p class="fs-7 mb-4">Completed {{ $m->completed_at?->format('j M Y') }}: {{ $m->resolution }}@if($m->cost > 0) — cost <strong>{{ $money($m->cost) }}</strong>, {{ $m->cost_borne_by === 'tenant' ? 'to be charged to the tenant' : 'paid by the landlord' }}@endif.</p>
                        @if($canManage)
                            @php $needsBilling = $m->cost_borne_by === 'tenant' && $m->cost > 0 && !$canBill; @endphp
                            <form method="post" action="{{ route('maintenance.act', $m->reference) }}" class="d-flex gap-3 align-items-end mb-3">
                                @csrf <input type="hidden" name="action" value="close">
                                <div class="flex-grow-1"><label class="form-label" for="mr_close_note">Sign-off note (optional)</label><input type="text" class="form-control" id="mr_close_note" name="note" maxlength="1000"></div>
                                <button class="btn btn-primary" type="submit" @disabled($needsBilling)>Close request</button>
                            </form>
                            <p class="text-muted fs-8 mb-0">
                                @if($m->cost > 0)Closing books {{ $money($m->cost) }} as a draft property expense for finance to approve and pay.@endif
                                @if($m->cost_borne_by === 'tenant' && $m->cost > 0) It also adds a {{ $money($m->cost) }} charge to {{ $tenantName ?? 'the tenant' }}'s account.@endif
                                @if($needsBilling)<span class="text-danger d-block">Charging the tenant needs billing permission; ask someone with it to close this request.</span>@endif
                            </p>
                        @else
                            <p class="text-muted fs-7 mb-0">Waiting for a property manager to sign it off.</p>
                        @endif
                    @else
                        <p class="text-muted fs-7 mb-0">This request is {{ strtolower($S[$m->status]) }}.</p>
                    @endif

                    @if($canManage && in_array($m->status, ['completed', 'cancelled'], true))
                        <form method="post" action="{{ route('maintenance.act', $m->reference) }}" class="d-flex gap-3 align-items-end mt-5">
                            @csrf <input type="hidden" name="action" value="reopen">
                            <div class="flex-grow-1"><label class="form-label" for="mr_reopen">Reopen because</label><input type="text" class="form-control" id="mr_reopen" name="reason" maxlength="500" required></div>
                            <button class="btn btn-light" type="submit">Reopen</button>
                        </form>
                    @endif
                    @if($canManage && $m->isOpen())
                        <form method="post" action="{{ route('maintenance.act', $m->reference) }}" class="d-flex gap-3 align-items-end mt-5">
                            @csrf <input type="hidden" name="action" value="cancel">
                            <div class="flex-grow-1"><label class="form-label" for="mr_cancel">Cancel because</label><input type="text" class="form-control" id="mr_cancel" name="reason" maxlength="500" required placeholder="e.g. Duplicate of another request"></div>
                            <button class="btn btn-light-danger" type="submit">Cancel request</button>
                        </form>
                    @endif
                </section>
                @endif

                {{-- Timeline --}}
                <section class="tenant-panel pt-6" data-maintenance-timeline>
                    <h2 class="fs-5 fw-bolder mb-4">Progress</h2>
                    @if($canWork)
                    <form method="post" action="{{ route('maintenance.act', $m->reference) }}" class="d-flex gap-3 align-items-end mb-6">
                        @csrf <input type="hidden" name="action" value="note">
                        <div class="flex-grow-1"><label class="form-label" for="mr_note">Add a progress note</label><input type="text" class="form-control" id="mr_note" name="note" maxlength="2000" required placeholder="e.g. Parts ordered, fitting Thursday"></div>
                        <button class="btn btn-light-primary" type="submit">Add note</button>
                    </form>
                    @endif
                    <ul class="list-unstyled mb-0">
                        @foreach($m->notes as $n)
                            <li class="d-flex gap-4 pb-5 mb-5 border-bottom border-gray-200" data-maintenance-note>
                                <div class="text-muted fs-8 text-nowrap" style="min-width:110px">{{ $n->created_at->setTimezone(config('app.timezone'))->format('j M Y, H:i') }}</div>
                                <div class="fs-7">
                                    @if($n->status_to)<span class="badge {{ $statusBadge[$n->status_to] ?? 'badge-light' }} me-2">{{ $S[$n->status_to] ?? $n->status_to }}</span>@endif
                                    {{ $n->note }}
                                    <div class="text-muted fs-8 mt-1">{{ $person($n->user) }}</div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>

            <div class="col-xl-5">
                <section class="tenant-panel pt-6 mb-7">
                    <h2 class="fs-5 fw-bolder mb-4">Details</h2>
                    <dl class="row fs-7 mb-0">
                        <dt class="col-5 text-muted fw-semibold">Property</dt><dd class="col-7">{{ $m->complex?->title }}</dd>
                        <dt class="col-5 text-muted fw-semibold">Unit</dt><dd class="col-7">{{ $m->unit_label ?? 'Common area' }}</dd>
                        <dt class="col-5 text-muted fw-semibold">Tenant</dt><dd class="col-7">@if($m->tenancy){!! profile_link(tenancy_profile_url($m->tenancy), $tenantName ?? $m->tenancy->tenancy_ref) !!}@else — @endif</dd>
                        <dt class="col-5 text-muted fw-semibold">Reported</dt><dd class="col-7">{{ $m->reported_at?->format('j M Y, H:i') }}@if($m->reported_by)<div class="text-muted fs-8">by {{ $m->reported_by }}{{ $m->reported_via ? ' · '.$m->reported_via : '' }}</div>@endif</dd>
                        <dt class="col-5 text-muted fw-semibold">Logged by</dt><dd class="col-7">{{ $person($m->logger) }}</dd>
                        <dt class="col-5 text-muted fw-semibold">Assigned to</dt><dd class="col-7">{{ $person($m->assignee) }}@if($m->assigned_at)<div class="text-muted fs-8">since {{ $m->assigned_at->format('j M Y') }}</div>@endif</dd>
                        @if($m->description)<dt class="col-12 text-muted fw-semibold mt-3">Details</dt><dd class="col-12">{{ $m->description }}</dd>@endif
                    </dl>
                </section>
                @if($m->cost !== null || $m->expense || $m->invoice)
                <section class="tenant-panel pt-6" data-maintenance-cost>
                    <h2 class="fs-5 fw-bolder mb-4">Cost</h2>
                    <dl class="row fs-7 mb-0">
                        <dt class="col-5 text-muted fw-semibold">Cost</dt><dd class="col-7">{{ $m->cost !== null ? $money($m->cost) : '—' }}</dd>
                        <dt class="col-5 text-muted fw-semibold">Paid by</dt><dd class="col-7">{{ $m->cost_borne_by === 'tenant' ? 'Tenant' : ($m->cost > 0 ? 'Landlord' : '—') }}</dd>
                        <dt class="col-5 text-muted fw-semibold">Property expense</dt><dd class="col-7">@if($m->expense)@if(auth()->user()->hasBusinessPermission('expense_management', 'view'))<a href="{{ route('business.expenses.workflow', $m->expense->id) }}">#{{ $m->expense->id }}</a>@else #{{ $m->expense->id }}@endif <span class="badge badge-light">{{ ucfirst($m->expense->workflow_status ?? 'recorded') }}</span>@else{{ $m->cost > 0 ? 'Booked when the request is closed' : '—' }}@endif</dd>
                        <dt class="col-5 text-muted fw-semibold">Tenant charge</dt><dd class="col-7">@if($m->invoice){{ $m->invoice->invoice_number }} · {{ $m->invoice->display_status }}@else{{ $m->cost_borne_by === 'tenant' ? 'Added when the request is closed' : '—' }}@endif</dd>
                    </dl>
                </section>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
