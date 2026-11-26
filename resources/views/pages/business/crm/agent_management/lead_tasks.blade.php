@extends('pages.business.layouts.app')

@section('content')

@php
    // Completion forms in include/lead_tasks_modals, by task title (tasks without a form complete with a note).
    $modalIds = [
        'Acknowledge Lead Assignment' => 'modal_acknowledge_assignment',
        'Review Lead Details' => 'modal_review_details',
        'Verify Availability' => 'modal_verify_availability',
        'Initial Contact Attempt' => 'modal_initial_contact',
        'Update Lead Status' => 'modal_update_status',
        'Initiate Tenant Onboarding' => 'modal_initiate_onboarding',
        'Verify Tenant Documentation' => 'modal_verify_documents',
        'Activate Tenant Record' => 'modal_activate_tenant',
        'Close Lead Record' => 'modal_close_lead',
    ];
    $phaseNames = ['qualification' => 'Qualification & matching', 'engagement' => 'Viewing & engagement', 'conversion' => 'Conversion', 'finalization' => 'Finalization'];
    $nice = fn ($v) => $v ? ucfirst(str_replace('_', ' ', $v)) : null;
    $initials = collect(preg_split('/\s+/', trim(preg_replace('/\(.*?\)/', '', $lead->full_name))))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $priorityTone = ['hot' => 'danger', 'warm' => 'warning', 'cold' => 'info'][$lead->priority] ?? 'info';
    $statusTone = $lead->converted_tenant_id || $lead->status === 'closed_won' ? 'is-won' : ($lead->status === 'closed_lost' ? 'is-lost' : 'is-open');
    $taskTone = ['completed' => 'is-won', 'skipped' => '', 'in_progress' => 'is-warm', 'blocked' => 'is-lost', 'pending' => 'is-open', 'cancelled' => ''];
    $open = $statistics['pending_tasks'] + $statistics['in_progress_tasks'];
    $leadUrl = lead_profile_url($lead);
    $canComplete = auth()->user()->hasBusinessPermission('task_management', 'complete');
@endphp

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Lead tasks</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">@if(can_open('/agents/tasks'))<a href="/agents/tasks" class="text-muted text-hover-primary">Tasks</a>@else Tasks @endif</li>
                @if($agent)<li class="breadcrumb-item text-muted"><a href="{{ route('agent_management.profile', $agent->profile_route_token) }}" class="text-muted text-hover-primary">{{ $agent->full_name }}</a></li>@endif
                <li class="breadcrumb-item text-dark">{{ $lead->full_name }}</li>
            </ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl lead-profile-page lead-tasks-page" id="kt_content_container">

        {{-- Lead and progress --}}
        <section class="lead-profile-hero pb-6 mb-6" aria-labelledby="lead-tasks-name" data-lead-tasks-header>
            <div class="lead-profile-hero-top">
                <div class="lead-profile-identity">
                    <span class="lead-profile-avatar is-{{ $priorityTone }}" aria-hidden="true">{{ $initials ?: '?' }}</span>
                    <div class="min-w-0">
                        <div class="lead-profile-name-row">
                            <h2 id="lead-tasks-name" class="lead-profile-name">{{ profile_link($leadUrl, $lead->full_name, 'text-reset') }}</h2>
                            <span class="lead-chip {{ $statusTone }}"><span class="dot" aria-hidden="true"></span>{{ $nice($lead->status) }}</span>
                            @if($lead->workflow_phase)<span class="lead-chip">{{ $phaseNames[$lead->workflow_phase] ?? $nice($lead->workflow_phase) }} phase</span>@endif
                        </div>
                        <ul class="lead-profile-facts">
                            @if($lead->primary_email)<li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:{{ $lead->primary_email }}">{{ $lead->primary_email }}</a></li>@endif
                            @if($lead->primary_phone)<li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:{{ $lead->primary_phone }}">{{ $lead->primary_phone }}</a></li>@endif
                            @if($agent)<li><i class="bi bi-person-badge" aria-hidden="true"></i>Agent&nbsp;{{ profile_link(agent_profile_url($agent), $agent->full_name) }}</li>@endif
                            @if($lead->complex)<li><i class="bi bi-building" aria-hidden="true"></i><a href="{{ route('complex.show', $lead->complex->slug) }}">{{ $lead->complex->title }}</a></li>@endif
                        </ul>
                    </div>
                </div>
                <div class="lead-profile-actions">
                    @if($leadUrl)<a class="btn btn-sm btn-light" href="{{ $leadUrl }}"><i class="bi bi-person-lines-fill" aria-hidden="true"></i>Lead profile</a>@endif
                    @if(can_open('/agents/tasks'))<a class="btn btn-sm btn-light" href="/agents/tasks"><i class="bi bi-list-task" aria-hidden="true"></i>All tasks</a>@endif
                </div>
            </div>

            <div class="lead-profile-metrics" aria-label="Task summary">
                <div>
                    <span>Progress</span>
                    <strong>{{ $progressPercentage }}<small>%</small></strong>
                    <div class="lead-profile-score" role="img" aria-label="{{ $statistics['completed_tasks'] }} of {{ $statistics['total_tasks'] }} tasks done"><i style="width: {{ $progressPercentage }}%"></i></div>
                </div>
                <div><span>Done</span><strong>{{ $statistics['completed_tasks'] }}<small> / {{ $statistics['total_tasks'] }}</small></strong><small>Completed or skipped</small></div>
                <div><span>Still to do</span><strong>{{ $open }}</strong><small>{{ $statistics['in_progress_tasks'] }} in progress{{ $statistics['blocked_tasks'] ? ' · ' . $statistics['blocked_tasks'] . ' blocked' : '' }}</small></div>
                <div class="{{ $statistics['overdue_tasks'] ? 'is-alert' : '' }}"><span>Overdue</span><strong>{{ $statistics['overdue_tasks'] }}</strong><small>{{ $statistics['overdue_tasks'] ? 'Past their due time' : 'Nothing late' }}</small></div>
            </div>
        </section>

        {{-- Tasks by phase --}}
        @forelse(collect($phaseNames)->filter(fn ($n, $k) => isset($tasksByPhase[$k]) && $tasksByPhase[$k]->isNotEmpty()) as $phase => $phaseName)
            @php
                $phaseTasks = $tasksByPhase[$phase];
                $phaseDone = $phaseTasks->filter(fn ($t) => in_array($t->status->value, ['completed', 'skipped'], true))->count();
                $phasePct = $phaseTasks->count() ? round($phaseDone / $phaseTasks->count() * 100) : 0;
                $isCurrent = $lead->workflow_phase === $phase;
            @endphp
            <section class="lead-profile-card mb-6 {{ $isCurrent ? 'is-current-phase' : '' }}" aria-labelledby="phase-{{ $phase }}" data-lead-task-phase="{{ $phase }}">
                <div class="lead-tasks-phase-head">
                    <div>
                        <h3 id="phase-{{ $phase }}">{{ $phaseName }}@if($isCurrent)<span class="lead-chip is-open ms-2">Current phase</span>@endif</h3>
                        <span>{{ $phaseDone }} of {{ $phaseTasks->count() }} done</span>
                    </div>
                    <div class="lead-tasks-phase-bar" role="img" aria-label="{{ $phasePct }}% done"><i style="width: {{ $phasePct }}%"></i></div>
                </div>
                <ol class="lead-profile-tasks">
                    @foreach($phaseTasks as $task)
                        @php
                            $st = $task->status->value;
                            $isOpen = in_array($st, ['pending', 'in_progress'], true);
                            $modal = $modalIds[$task->title] ?? null;
                        @endphp
                        <li class="lead-profile-task is-{{ $st }}" id="task-{{ $task->id }}" data-lead-task="{{ $task->id }}">
                            <span class="lead-profile-task-icon" aria-hidden="true"><i class="bi bi-{{ $st === 'completed' ? 'check-lg' : ($st === 'skipped' ? 'skip-forward' : ($st === 'in_progress' ? 'clock' : ($st === 'blocked' ? 'slash-circle' : 'circle'))) }}"></i></span>
                            <div class="lead-profile-task-body">
                                <div class="lead-profile-task-title">{{ $task->title }}</div>
                                @if($task->description)<div class="lead-profile-task-desc">{{ $task->description }}</div>@endif
                                <div class="lead-profile-task-flags">
                                    <span class="lead-chip {{ $taskTone[$st] ?? 'is-open' }}">{{ $task->status->label() }}</span>
                                    @if($task->is_required)<span class="lead-chip">Required</span>@endif
                                    @if($task->blocks_next_phase)<span class="lead-chip is-cold" title="Completing this task moves the lead to its next phase"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i>Moves the lead on</span>@endif
                                    @if($task->task_type->value !== 'system_assigned')<span class="lead-chip">{{ $task->task_type->label() }}</span>@endif
                                    @if($isOpen && $task->sla_breach_at)
                                        <span class="lead-profile-due {{ $task->isOverdue() ? 'is-overdue' : '' }}"><i class="bi bi-clock" aria-hidden="true"></i>{{ $task->isOverdue() ? 'Overdue since' : 'Due' }} {{ $task->sla_breach_at->format('j M, H:i') }}</span>
                                    @elseif($task->completed_at)
                                        <span class="lead-profile-due"><i class="bi bi-check2-circle" aria-hidden="true"></i>{{ $st === 'skipped' ? 'Skipped' : 'Done' }} {{ \Carbon\Carbon::parse($task->completed_at)->format('j M, H:i') }}{{ $task->completedBy ? ' by ' . trim($task->completedBy->first_name . ' ' . $task->completedBy->last_name) : '' }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="lead-tasks-actions">
                                @if($st === 'pending')
                                    @if($canComplete)<button type="button" class="btn btn-sm btn-light" data-lead-task-start="{{ $task->id }}">Start</button>@endif
                                @endif
                                @if($isOpen && $modal && $canComplete)
                                    <button type="button" class="btn btn-sm btn-primary complete-task-btn" data-task-id="{{ $task->id }}" data-task-title="{{ $task->title }}" data-bs-toggle="modal" data-bs-target="#{{ $modal }}">Complete</button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @empty
            <section class="lead-profile-card"><p class="lead-profile-empty mb-0">No tasks yet. Tasks start once the lead has an agent.</p></section>
        @endforelse

    </div>
</div>

@include('pages.business.crm.agent_management.include.lead_tasks_modals')

<script>
    // Start a task (pending → in progress), then show the updated list.
    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-lead-task-start]');
        if (!button) return;
        button.disabled = true;
        const response = await fetch('/agents/tasks/' + encodeURIComponent(button.dataset.leadTaskStart) + '/start', {
            method: 'POST', credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        });
        if (response.ok) { location.reload(); return; }
        button.disabled = false;
        const result = await response.json().catch(() => ({}));
        (window.Swal ? Swal.fire({ icon: 'error', text: result.message || 'Could not start the task.' }) : alert(result.message || 'Could not start the task.'));
    });
</script>

@endsection
