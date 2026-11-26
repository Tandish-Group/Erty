@extends('pages.business.layouts.app')

@section('content')
<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Agent tasks</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item">@businessCan('agent_management')<a href="/agents" class="text-muted text-hover-primary">Agents</a>@else<span class="text-muted">Agents</span>@endbusinessCan</li>
                <li class="breadcrumb-item text-dark">Tasks</li>
            </ul>
        </div>
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle"><span class="svg-icon svg-icon-1 mt-1"><i class="fas fa-bars"></i></span></div>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl crm-task-dashboard" id="kt_content_container">
        @include('pages.business.agents.partials.nav')
        <div class="crm-task-heading">
            <div>
                <span class="crm-task-eyebrow">AGENTS</span>
                <h2>Agent tasks</h2>
                <p>@if(($scope ?? 'mine') === 'all')Follow-ups and work in progress across every agent{{ $agentFilter ? ' (filtered to one agent)' : '' }}.@else Follow-ups and work in progress for the leads assigned to you{{ $agent ? ' ('.$agent->first_name.' '.$agent->last_name.')' : '' }}.@endif Tasks are created automatically as each lead moves through its phases.</p>
            </div>
        </div>

        <nav class="nav tenant-tabs crm-task-tabs mb-6" role="tablist" aria-label="Agent task views">
            <a class="nav-link active" id="tasks-board-tab" data-bs-toggle="tab" href="#tasks-board" role="tab" aria-controls="tasks-board" aria-selected="true">Task board</a>
            <a class="nav-link" id="tasks-workflow-tab" data-bs-toggle="tab" href="#tasks-workflow" role="tab" aria-controls="tasks-workflow" aria-selected="false">How tasks are assigned</a>
        </nav>

        <div class="tab-content">
        <div class="tab-pane fade show active" id="tasks-board" role="tabpanel">
        <div class="crm-task-metrics" aria-label="Task overview">
            <button type="button" class="crm-task-metric" data-task-status="">
                <span>All tasks</span><strong id="task-metric-total">{{ $metrics['total_tasks'] ?? 0 }}</strong><small>{{ $metrics['completed_tasks'] ?? 0 }} completed or skipped</small>
            </button>
            <button type="button" class="crm-task-metric" data-task-status="pending">
                <span>Pending</span><strong id="task-metric-pending">{{ $metrics['pending_tasks'] ?? 0 }}</strong><small>{{ $metrics['due_today'] ?? 0 }} due today</small>
            </button>
            <button type="button" class="crm-task-metric" data-task-status="in_progress">
                <span>In progress</span><strong id="task-metric-active">{{ $metrics['in_progress_tasks'] ?? 0 }}</strong><small>{{ $metrics['blocked_tasks'] ?? 0 }} blocked</small>
            </button>
            <button type="button" class="crm-task-metric is-attention" data-task-status="overdue">
                <span>Overdue</span><strong id="task-metric-overdue">{{ $metrics['overdue_tasks'] ?? 0 }}</strong><small>Past their response target</small>
            </button>
        </div>
        @if(($metrics['unassigned_leads'] ?? 0) > 0)
            <div class="alert alert-warning d-flex flex-wrap justify-content-between align-items-center gap-3" role="status" data-unassigned-leads>
                <span><strong>{{ $metrics['unassigned_leads'] }} {{ \Illuminate\Support\Str::plural('lead', $metrics['unassigned_leads']) }} waiting for an agent.</strong> No tasks start until a lead has an agent.</span>
                @businessCan('lead_management', 'view')<a class="btn btn-sm btn-light-warning" href="/customer_relationship/lead_management">Assign agents</a>@endbusinessCan
            </div>
        @endif

        <section class="crm-task-panel">
            <div class="crm-task-panel-heading">
                <div><h3>Lead workflows</h3><p>Expand a lead to see its tasks, due dates and next actions.</p></div>
                <div class="crm-task-filters">
                    @if(($canSeeAll ?? false) && $agent)
                    <select class="form-select" id="scope-filter" aria-label="Whose tasks to show">
                        <option value="all" @selected($scope === 'all')>All agents' tasks</option>
                        <option value="mine" @selected($scope === 'mine')>My tasks</option>
                    </select>
                    @endif
                    @if(($canSeeAll ?? false) && $scope === 'all' && $boardAgents->isNotEmpty())
                    <select class="form-select" id="agent-filter" aria-label="Filter by agent">
                        <option value="">Every agent</option>
                        @foreach($boardAgents as $boardAgent)<option value="{{ $boardAgent->id }}" @selected($agentFilter === $boardAgent->id)>{{ $boardAgent->full_name }}</option>@endforeach
                    </select>
                    @endif
                    <label class="crm-task-search"><i class="las la-search"></i><input type="search" id="task-search" placeholder="Search leads or tasks" aria-label="Search leads or tasks"></label>
                    <select class="form-select" id="status-filter" aria-label="Filter by task status">
                        <option value="">All statuses</option><option value="pending">Pending</option><option value="in_progress">In progress</option>
                        <option value="completed">Completed</option><option value="blocked">Blocked</option><option value="overdue">Overdue</option>
                    </select>
                    <select class="form-select" id="phase-filter" aria-label="Filter by workflow phase">
                        <option value="">All phases</option><option value="qualification">Qualification</option><option value="engagement">Engagement</option>
                        <option value="conversion">Conversion</option><option value="finalization">Finalization</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive crm-task-table-wrap">
                <table id="tasks-table" class="table align-middle" style="width:100%">
                    <thead><tr><th aria-label="Expand"></th><th>Lead</th><th>Current phase</th><th>Tasks complete</th><th>Progress</th><th>Attention</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        </div>

@php
    $stages = array_merge(['Lead captured (CRM)', 'Agent assigned'], array_column($workflow, 'label'), ['Tenant']);
    $taskStages = range(2, count($workflow) + 1);
    $within = fn ($h) => $h ? ($h >= 24 && $h % 24 === 0 ? ($h / 24).' '.\Illuminate\Support\Str::plural('day', $h / 24) : $h.' h') : null;
@endphp
        <div class="tab-pane fade" id="tasks-workflow" role="tabpanel" aria-labelledby="tasks-workflow-tab" data-task-workflow>
            <section class="crm-task-panel is-padded mb-6">
                <h3 class="crm-flow-title">The lead lifecycle</h3>
                <ol class="crm-flow-steps" aria-label="Lead lifecycle">
                    @foreach($stages as $n => $stage)
                        <li class="crm-flow-step {{ in_array($n, $taskStages, true) ? 'is-task-stage' : '' }}"><span class="crm-flow-step-no">{{ $n + 1 }}</span>{{ $stage }}</li>
                    @endforeach
                </ol>
                <p class="crm-flow-legend"><span class="crm-flow-legend-dot" aria-hidden="true"></span>Highlighted stages create tasks for the agent.</p>
                <ul class="crm-flow-rules">
                    <li><strong>Tasks belong to agents.</strong> A lead gets tasks only once it has an agent: the property's responsible agent, or one chosen on the lead.</li>
                    <li><strong>Each phase creates its own tasks.</strong> When a phase starts, its tasks are added with a response target counted from that moment.</li>
                    <li><strong>Moving on.</strong> The lead moves to the next phase when every task marked <em>Moves the lead on</em> is completed or skipped.</li>
                    <li><strong>Required tasks</strong> cannot be skipped. Other tasks can be skipped with a reason.</li>
                    <li><strong>Overdue</strong> means the response target has passed. Blocked tasks wait on another task first.</li>
                    <li><strong>Extra tasks.</strong> Agents can add their own follow-up tasks to a lead at any phase.</li>
                </ul>
            </section>
            <div class="row g-6">
                @foreach($workflow as $index => $phase)
                    <div class="col-xl-6">
                        <section class="crm-task-panel is-padded h-100 crm-phase-card" data-workflow-phase="{{ $phase['key'] }}" aria-labelledby="phase-{{ $phase['key'] }}-title">
                            <header class="crm-phase-head">
                                <span class="crm-phase-no" aria-hidden="true">{{ $index + 1 }}</span>
                                <h3 id="phase-{{ $phase['key'] }}-title">{{ $phase['label'] }}</h3>
                                <div class="crm-phase-counts">
                                    <span class="badge badge-light-primary">{{ $phase['open'] }} open</span>
                                    @if($phase['overdue'])<span class="badge badge-light-danger">{{ $phase['overdue'] }} overdue</span>@endif
                                </div>
                            </header>
                            <p class="crm-phase-starts"><strong>Starts:</strong> {{ $phase['starts'] }}</p>
                            @if($phase['templates']->isEmpty())
                                <p class="text-muted fs-7 mb-0">No tasks are created in this phase.</p>
                            @else
                            <ol class="crm-phase-tasks">
                                @foreach($phase['templates'] as $template)
                                    <li class="crm-phase-task">
                                        <span class="crm-phase-task-no" aria-hidden="true">{{ $template->sequence_order }}</span>
                                        <div class="crm-phase-task-body">
                                            <div class="crm-phase-task-title">{{ $template->title }}</div>
                                            @if($template->description)<div class="crm-phase-task-desc">{{ $template->description }}</div>@endif
                                        </div>
                                        <div class="crm-phase-task-meta">
                                            @if($within($template->sla_hours))<span class="crm-phase-sla" title="Respond within"><i class="far fa-clock" aria-hidden="true"></i><span class="visually-hidden">Respond within </span>{{ $within($template->sla_hours) }}</span>@endif
                                            <div class="crm-phase-flags">
                                                @if($template->is_required)<span class="badge badge-light-dark">Required</span>@endif
                                                @if($template->blocks_next_phase)<span class="badge badge-light-warning">Moves the lead on</span>@endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                            @endif
                        </section>
                    </div>
                @endforeach
            </div>
            <p class="text-muted fs-8 mt-4">These task steps are {{ config('app.name') }}'s standard lead workflow and apply to every business.</p>
        </div>
        </div>
    </div>
</div>
@endsection
