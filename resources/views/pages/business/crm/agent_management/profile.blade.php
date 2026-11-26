@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Agent Profile</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    <a href="/agents" class="text-muted text-hover-primary">Agents</a>
                </li>
                <li class="breadcrumb-item text-dark">{{ $agent->full_name }}</li>
            </ul>
        </div>
        <div class="d-flex d-lg-none align-items-center ms-n2 me-2">
            <div class="btn btn-icon btn-active-icon-primary" id="kt_aside_toggle">
                <span class="svg-icon svg-icon-1 mt-1">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M21 7H3C2.4 7 2 6.6 2 6V4C2 3.4 2.4 3 3 3H21C21.6 3 22 3.4 22 4V6C22 6.6 21.6 7 21 7Z" fill="black" />
                        <path opacity="0.3" d="M21 14H3C2.4 14 2 13.6 2 13V11C2 10.4 2.4 10 3 10H21C21.6 10 22 10.4 22 11V13C22 13.6 21.6 14 21 14ZM22 20V18C22 17.4 21.6 17 21 17H3C2.4 17 2 17.4 2 18V20C2 20.6 2.4 21 3 21H21C21.6 21 22 20.6 22 20Z" fill="black" />
                    </svg>
                </span>
            </div>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
    <div class="container-xxl agent-profile" id="kt_content_container">
        @include('pages.business.agents.partials.nav')

        @php
            $initials = strtoupper(mb_substr((string) $agent->first_name, 0, 1) . mb_substr((string) $agent->last_name, 0, 1));
            [$statusLabel, $statusClass] = match ($agent->status) {
                'active' => ['Active', 'is-active'], 'on_leave' => ['On leave', 'is-leave'], 'terminated' => ['Terminated', 'is-off'], default => ['Inactive', 'is-off'],
            };
            $typeLabel = ['agency' => 'Agency', 'contractor' => 'Contractor'][$agent->agent_type] ?? 'Employee';
            $humanize = fn ($value) => filled($value) ? ucfirst(str_replace('_', ' ', (string) $value)) : null;
            $ownCommissionOnly = \App\Services\Agents\AgentProfileService::ownCommissionOnly();
            $canSeeCommission = \App\Services\Agents\AgentProfileService::canSeeCommissionOf($agent);
            $activeTenancies = $tenancies->whereIn('status', ['Active', 'On Notice'])->count();
            $commissionTotal = (float) ($commission['total_commission'] ?? $commission['default_commission'] ?? 0);
            $specializations = is_array($agent->specializations) ? $agent->specializations : (json_decode((string) $agent->specializations, true) ?: []);
            $encryptedId = Crypt::encrypt($agent->id);
            $who = $agent->agent_type === 'agency' ? $agent->full_name : $agent->first_name;
        @endphp

        <!--begin::Agent header-->
        <section class="agent-header" data-agent-header>
            <div class="agent-header-main">
                <div class="agent-avatar" aria-hidden="true">@if($agent->profile_photo)<img src="{{ $agent->profile_photo }}" alt="">@else{{ $initials }}@endif</div>
                <div class="agent-identity">
                    <span class="eyebrow">{{ $typeLabel }}{{ $agent->role?->name ? ' · ' . $agent->role->name : '' }}</span>
                    <h1>{{ $agent->full_name }} <span class="agent-chip {{ $statusClass }}" data-agent-status><span class="dot" aria-hidden="true"></span>{{ $statusLabel }}</span>@if($agent->user_id)<span class="agent-chip" title="Signs in to {{ config('app.name') }}">Signs in</span>@endif</h1>
                    <ul class="agent-contact">
                        @if($agent->email)<li><a href="mailto:{{ $agent->email }}"><i class="bi bi-envelope" aria-hidden="true"></i>{{ $agent->email }}</a></li>@endif
                        @if($agent->phone)<li><a href="tel:{{ $agent->phone }}"><i class="bi bi-telephone" aria-hidden="true"></i>{{ $agent->phone }}</a></li>@endif
                        @if($agent->department)<li><i class="bi bi-diagram-3" aria-hidden="true"></i>{{ $agent->department->name }}</li>@endif
                        @if($agent->workLocation)<li><i class="bi bi-geo-alt" aria-hidden="true"></i>{{ $agent->workLocation->name }}</li>@endif
                        @if($agent->reportsTo)<li><i class="bi bi-people" aria-hidden="true"></i>Reports to&nbsp;<a href="{{ route('agent_management.profile', $agent->reportsTo->profile_route_token) }}">{{ $agent->reportsTo->full_name }}</a></li>@endif
                    </ul>
                </div>
                @if($canManageLogin && in_array($agent->status, ['active', 'on_leave'], true))
                <div class="agent-header-actions">
                    @if($agent->status === 'active')
                        <button type="button" class="btn btn-sm btn-light-warning" onclick="updateAgentStatus('{{ $encryptedId }}', 'on_leave')">Mark on leave</button>
                    @else
                        <button type="button" class="btn btn-sm btn-light-success" onclick="updateAgentStatus('{{ $encryptedId }}', 'active')">Back from leave</button>
                    @endif
                </div>
                @endif
            </div>
            <div class="agent-figures" data-agent-figures>
                <div><span>Properties managed</span><strong>{{ $responsibility['primary']->count() }}</strong><small>As primary manager</small></div>
                <div><span>Units as sub-agent</span><strong>{{ $responsibility['unit_count'] }}</strong><small>{{ $responsibility['delegated']->count() ? 'On ' . $responsibility['delegated']->count() . ' ' . \Illuminate\Support\Str::plural('property', $responsibility['delegated']->count()) : 'None delegated yet' }}</small></div>
                <div><span>Active tenancies</span><strong>{{ $activeTenancies }}</strong><small>Credited to {{ $who }}</small></div>
                <div><span>Open leads</span><strong>{{ $statistics['active_leads'] }}</strong><small>{{ $statistics['total_leads'] }} total · {{ $performanceMetrics['conversion_rate'] }}% converted</small></div>
                @if($canSeeCommission)
                <div><span>Commission</span><strong>KES {{ number_format($commissionTotal, 0) }}</strong><small>{{ $period['label'] }}</small></div>
                @endif
            </div>
        </section>
        <!--end::Agent header-->

        <nav class="nav agent-tabs" role="tablist" aria-label="Agent profile sections">
            <a class="nav-link active" data-bs-toggle="tab" href="#profile_overview" role="tab">Overview</a>
            <a class="nav-link" data-bs-toggle="tab" href="#profile_relationships" role="tab">Properties &amp; tenancies<span class="count">{{ $tenancies->count() }}</span></a>
            <a class="nav-link" data-bs-toggle="tab" href="#profile_leads" role="tab">Leads<span class="count">{{ $statistics['total_leads'] }}</span></a>
            <a class="nav-link" data-bs-toggle="tab" href="#profile_tasks" role="tab">Tasks<span class="count">{{ $statistics['pending_tasks'] }}</span></a>
            <a class="nav-link" data-bs-toggle="tab" href="#profile_performance" role="tab">Performance</a>
            @if($canSeeCommission)<a class="nav-link" data-bs-toggle="tab" href="#profile_commission" role="tab">Commission</a>@endif
            <a class="nav-link" data-bs-toggle="tab" href="#profile_activity" role="tab">Activity<span class="count">{{ count($activityTimeline) }}</span></a>
        </nav>

        <!--begin::Tab Content-->
        <div class="tab-content" id="profileTabContent">
            
            <!--begin::Overview Tab-->
            <div class="tab-pane fade show active" id="profile_overview" role="tabpanel">
                <div class="row g-5">
                    <div class="col-xl-7">
                        <section class="agent-panel" data-agent-responsibilities>
                            <h2>Responsibilities</h2>
                            <p class="lead">Properties {{ $who }} manages as primary manager, and units delegated to {{ $who }} on other properties. Commission follows these assignments.</p>
                            @foreach($responsibility['primary'] as $property)
                                <div class="agent-duty">
                                    <div>
                                        <a class="agent-duty-title" href="{{ route('complex.show', $property->slug) }}#assignments">{{ $property->title }}</a>
                                        <small>{{ collect([$property->residential_count ? $property->residential_count . ' residential' : null, $property->commercial_count ? $property->commercial_count . ' commercial' : null, $property->county])->filter()->implode(' · ') ?: 'No units yet' }}</small>
                                    </div>
                                    <span class="agent-role-tag">Primary manager</span>
                                </div>
                            @endforeach
                            @foreach($responsibility['delegated'] as $item)
                                <div class="agent-duty">
                                    <div>
                                        <a class="agent-duty-title" href="{{ route('complex.show', $item->complex->slug) }}#assignments">{{ $item->complex->title }}</a>
                                        <small>{{ $item->units->count() }} {{ \Illuminate\Support\Str::plural('unit', $item->units->count()) }} · {{ $item->complex->isCompanyManaged() ? 'company-managed' : 'primary manager ' . trim(($item->complex->assignedAgent->first_name ?? '') . ' ' . ($item->complex->assignedAgent->last_name ?? '')) }}</small>
                                        <div class="agent-unit-list">
                                            @foreach($item->units as $unit)<a href="{{ route('complex.show', $item->complex->slug) }}#{{ $unit->kind }}_units" title="{{ ucfirst($unit->kind) }} unit">{{ $unit->unit_number }}</a>@endforeach
                                        </div>
                                    </div>
                                    <span class="agent-role-tag is-sub">Sub-agent</span>
                                </div>
                            @endforeach
                            @if($responsibility['primary']->isEmpty() && $responsibility['delegated']->isEmpty())
                                <p class="agent-empty">No properties or units are assigned to {{ $who }} yet. Assign them from a property's Assignments tab.</p>
                            @endif
                        </section>
                    </div>
                    <div class="col-xl-5">
                        <section class="agent-panel" data-agent-tasks>
                            <div class="panel-head"><h2>Tasks</h2>@if(can_open('/agents/tasks'))<a href="/agents/tasks">Open tasks</a>@endif</div>
                            <p class="lead">Follow-ups on {{ $who }}'s leads.</p>
                            <div class="agent-task-stats">
                                <div><span>Total</span><strong>{{ $statistics['total_tasks'] }}</strong></div>
                                <div class="{{ $statistics['pending_tasks'] ? 'is-warn' : '' }}"><span>Pending</span><strong>{{ $statistics['pending_tasks'] }}</strong></div>
                                <div><span>Done</span><strong>{{ $statistics['completed_tasks'] }}</strong></div>
                                <div class="{{ $statistics['overdue_tasks'] ? 'is-bad' : '' }}"><span>Overdue</span><strong>{{ $statistics['overdue_tasks'] }}</strong></div>
                            </div>
                            <div class="agent-progress-label"><span>Completion</span><strong>{{ rtrim(rtrim(number_format((float) $performanceMetrics['task_completion_rate'], 1), '0'), '.') }}%</strong></div>
                            <div class="agent-progress" role="progressbar" aria-label="Task completion" aria-valuenow="{{ $performanceMetrics['task_completion_rate'] }}" aria-valuemin="0" aria-valuemax="100"><div style="width: {{ min(100, (float) $performanceMetrics['task_completion_rate']) }}%"></div></div>
                        </section>
                    </div>

                    <div class="col-xl-4">
                        <section class="agent-panel" data-agent-details>
                            <h2>{{ $agent->agent_type === 'agency' ? 'Company details' : 'Details' }}</h2>
                            <p class="lead">{{ $typeLabel }}{{ $agent->created_at ? ' · on file since ' . $agent->created_at->format('j M Y') : '' }}</p>
                            @php
                                $facts = $agent->agent_type === 'agency' ? [
                                    'Company' => $agent->company_name, 'Agency licence' => $agent->agency_license, 'Business registration' => $agent->business_registration_number,
                                    'Tax ID' => $agent->tax_id, 'Contact person' => trim(collect([$agent->contact_person_name, $agent->contact_person_phone])->filter()->implode(' · ')) ?: null,
                                    'Contact email' => $agent->contact_person_email, 'Company address' => $agent->company_address,
                                ] : ($agent->agent_type === 'contractor' ? [
                                    'Contract' => $humanize($agent->contract_type),
                                    'Contract period' => $agent->contract_start_date ? $agent->contract_start_date->format('j M Y') . ' – ' . ($agent->contract_end_date?->format('j M Y') ?? 'open-ended') : null,
                                    'Payment terms' => $agent->payment_terms, 'Role' => $agent->role?->name, 'Department' => $agent->department?->name,
                                ] : [
                                    'Employment' => $humanize($agent->employment_type), 'Started' => $agent->start_date?->format('j M Y'),
                                    'Role' => $agent->role?->name, 'Department' => $agent->department?->name,
                                    'Probation' => $agent->probation_period ? $agent->probation_period . ' months' : null,
                                    'Reporting manager' => $agent->reportingManager?->name,
                                ]);
                                $facts += ['Date of birth' => $agent->date_of_birth?->format('j M Y'), 'Gender' => $humanize($agent->gender), 'National ID' => $agent->national_id, 'Address' => $agent->address];
                                $facts = array_filter($facts, fn ($v) => filled($v));
                            @endphp
                            @if($facts || $specializations)
                            <dl class="agent-facts">
                                @foreach($facts as $label => $value)
                                    <div class="{{ in_array($label, ['Address', 'Company address', 'Contact person'], true) ? 'wide' : '' }}"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                                @endforeach
                                @if($specializations)
                                    <div class="wide"><dt>Specialisations</dt><dd>@foreach($specializations as $s)<span class="agent-chip me-1">{{ $humanize($s) }}</span>@endforeach</dd></div>
                                @endif
                            </dl>
                            @else
                                <p class="agent-empty">No further details recorded.</p>
                            @endif
                        </section>
                    </div>
                    <div class="col-xl-4 agent-side-cards">@include('pages.business.agents.partials.signin')</div>
                    <div class="col-xl-4 agent-side-cards">@include('pages.business.agents.partials.hierarchy')</div>
                </div>
            </div>
            <!--end::Overview Tab-->
            
            <!--begin::Leads Tab-->
            <div class="tab-pane fade" id="profile_leads" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex flex-row justify-content-between">
                        <div class="card-title">
                            <h3 class="fw-bolder">Assigned Leads</h3>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed fs-6 gy-5">
                                <thead>
                                    <tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase gs-0">
                                        <th>Lead Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Phase</th>
                                        <th>Assigned Date</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($leads as $lead)
                                    <tr>
                                        <td>
                                            {{ profile_link(lead_profile_url($lead), $lead->full_name, 'text-gray-800 text-hover-primary fw-bold') }}
                                            @if($lead->email)
                                            <div class="text-muted fs-7">{{ $lead->email }}</div>
                                            @endif
                                            @if($lead->complex)
                                                <div class="fs-7 mt-1"><span class="text-muted">Property:</span> <a href="{{ route('complex.show', $lead->complex->slug) }}">{{ $lead->complex->title }}</a></div>
                                            @endif
                                            @if($lead->convertedTenant?->tenantProfile?->tenant_ref)
                                                <div class="fs-7 mt-1"><span class="text-muted">Converted tenant:</span> <a href="{{ url('/tenant_management/tenant/'.$lead->convertedTenant->tenantProfile->tenant_ref.'/view') }}">{{ $lead->convertedTenant->tenantProfile->display_name }}</a></div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-light-{{ $lead->lead_type === 'Business' ? 'success' : 'primary' }}">
                                                {{ $lead->lead_type }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $lead->status_badge_class }}">
                                                {{ ucfirst($lead->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge {{ $lead->priority_badge_class }}">
                                                {{ ucfirst($lead->priority) }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-light-info">
                                                {{ ucfirst($lead->workflow_phase) }}
                                            </span>
                                        </td>
                                        <td>{{ $lead->created_at->format('M d, Y') }}</td>
                                        <td class="text-end">
                                            @if($url = lead_profile_url($lead))<a href="{{ $url }}" class="btn btn-sm btn-light btn-active-light-primary">View</a>@endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-10">
                                            <i class="las la-user-slash fs-3x text-muted"></i>
                                            <div class="text-muted mt-3">No leads assigned yet</div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Leads Tab-->

            <!--begin::Tenants and Properties Tab-->
            <div class="tab-pane fade" id="profile_relationships" role="tabpanel">
                <div class="row g-6">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><div class="card-title"><h3 class="fw-bolder">Assigned Tenancies</h3></div></div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table align-middle table-row-dashed fs-6 gy-4">
                                        <thead><tr class="text-start text-gray-400 fw-bolder fs-7 text-uppercase"><th>Tenant</th><th>Property &amp; unit</th><th>Tenancy</th><th>Status</th></tr></thead>
                                        <tbody>
                                            @forelse($tenancies as $tenancy)
                                                @php
                                                    $unit = $tenancy->residentialUnit ?: $tenancy->commercialUnit;
                                                    $unitType = $tenancy->residentialUnit ? 'Residential' : ($tenancy->commercialUnit ? 'Commercial' : null);
                                                @endphp
                                                <tr>
                                                    <td>
                                                        @if($tenancy->tenantProfile?->tenant_ref)
                                                            <a class="fw-bold text-gray-800 text-hover-primary" href="{{ url('/tenant_management/tenant/'.$tenancy->tenantProfile->tenant_ref.'/view') }}">{{ $tenancy->tenantProfile->display_name ?: 'Open tenant profile' }}</a>
                                                            <div class="text-muted fs-7">{{ $tenancy->tenantProfile->primary_email }}</div>
                                                        @else
                                                            <span class="text-muted">Tenant profile unavailable</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($tenancy->complex)
                                                            <a class="fw-semibold" href="{{ route('complex.show', $tenancy->complex->slug) }}">{{ $tenancy->complex->title }}</a>
                                                            @if($unit)
                                                                <div class="fs-7"><a href="{{ route('complex.show', $tenancy->complex->slug) }}#inventory-unit-{{ $unit->id }}">{{ $unitType }} unit {{ $unit->unit_number }}</a></div>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">Property unavailable</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($tenancy->tenancy_ref)
                                                            <a href="{{ url('/tenant_management/tenancy/'.$tenancy->tenancy_ref.'/view') }}">{{ $tenancy->tenancy_ref }}</a>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </td>
                                                    <td><span class="badge badge-light-primary">{{ $tenancy->status ?: 'Unknown' }}</span></td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="4" class="text-center text-muted py-8">No tenant agreements are assigned to this agent.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header"><div class="card-title"><h3 class="fw-bolder">Related Properties</h3></div></div>
                            <div class="card-body">
                                @forelse($properties as $property)
                                    <a href="{{ route('complex.show', $property->slug) }}" class="d-flex align-items-center justify-content-between border border-gray-200 rounded p-4 mb-3 text-hover-primary">
                                        <span><strong class="d-block text-gray-800">{{ $property->title }}</strong><small class="text-muted">{{ $property->type ?: 'Property' }}{{ $property->county ? ' · '.$property->county : '' }}</small></span>
                                        <span class="text-primary">View property <i class="las la-arrow-right"></i></span>
                                    </a>
                                @empty
                                    <div class="text-muted">No properties are linked to this agent yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!--end::Tenants and Properties Tab-->
            
            <!--begin::Tasks Tab-->
            <div class="tab-pane fade" id="profile_tasks" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex flex-row justify-content-between">
                        <div class="card-title">
                            <h3 class="fw-bolder">Agent Tasks by Lead</h3>
                        </div>
                        <div class="card-toolbar">
                            @if(can_open('/agents/tasks'))
                            <a href="/agents/tasks" class="btn btn-sm btn-light-primary">
                                <i class="las la-tasks fs-3"></i>
                                View All Tasks
                            </a>
                            @endif
                        </div>
                    </div>
                    <div class="card-body">
                        @php
                            $tasksByLead = $tasks->groupBy('lead_id');
                        @endphp
                        
                        @forelse($tasksByLead as $leadId => $leadTasks)
                            @php
                                $lead = $leadTasks->first()->lead;
                                $totalTasks = $leadTasks->count();
                                $completedTasks = $leadTasks->filter(function($task) {
                                    return in_array($task->status->value, ['completed', 'skipped']);
                                })->count();
                                $overdueTasks = $leadTasks->filter(function($task) {
                                    return $task->is_overdue && !in_array($task->status->value, ['completed', 'cancelled']);
                                })->count();
                                $progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
                            @endphp
                            
                            <div class="card border border-gray-300 mb-5">
                                <div class="card-header bg-light-primary  d-flex flex-row justify-content-between">
                                    <div class="card-title">
                                        <div class="d-flex align-items-center">
                                            <div class="symbol symbol-45px me-5">
                                                @php
                                                    $leadName = $lead->full_name ?? 'Lead';
                                                    $initials = collect(explode(' ', $leadName))->map(fn($word) => strtoupper(substr($word, 0, 1)))->take(2)->join('');
                                                @endphp
                                                <span class="symbol-label bg-light-info text-info fs-6 fw-bolder">
                                                    {{ $initials }}
                                                </span>
                                            </div>
                                            <div class="d-flex flex-column">
                                                <a href="/agents/lead-tasks/{{ encrypt($lead->id) }}" class="text-gray-800 text-hover-primary fs-5 fw-bold">
                                                    {{ $leadName }}
                                                </a>
                                                <span class="text-muted fs-7">
                                                    <span class="badge badge-light-info me-2">{{ ucfirst($lead->workflow_phase ?? 'N/A') }}</span>
                                                    {{ $totalTasks }} {{ Str::plural('task', $totalTasks) }} • {{ $completedTasks }} completed
                                                    @if($overdueTasks > 0)
                                                        • <span class="text-danger fw-bold">{{ $overdueTasks }} overdue</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-toolbar">
                                        <div class="d-flex align-items-center">
                                            <div class="me-5">
                                                <span class="text-muted fs-7 me-2">Progress:</span>
                                                <span class="fw-bold fs-6">{{ $progressPercentage }}%</span>
                                            </div>
                                            <div class="progress h-6px w-100px me-3">
                                                <div class="progress-bar bg-{{ $progressPercentage === 100 ? 'success' : ($progressPercentage > 50 ? 'primary' : 'warning') }}" 
                                                     role="progressbar" 
                                                     style="width: {{ $progressPercentage }}%"></div>
                                            </div>
                                            <a href="/agents/lead-tasks/{{ encrypt($lead->id) }}" 
                                               class="btn btn-sm btn-light-primary">
                                                <i class="las la-arrow-right fs-3"></i>
                                                View Tasks
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-row-bordered table-row-gray-100 align-middle gs-0 gy-3">
                                            <thead>
                                                <tr class="fw-bold text-muted">
                                                    <th class="min-w-200px">Task</th>
                                                    <th class="min-w-100px">Phase</th>
                                                    <th class="min-w-100px">Status</th>
                                                    <th class="min-w-120px">Due Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($leadTasks->take(5) as $task)
                                                <tr>
                                                    <td>
                                                        <div class="d-flex flex-column">
                                                            <span class="text-dark fw-bold text-hover-primary fs-6">{{ $task->title }}</span>
                                                            @if($task->is_required)
                                                            <span class="badge badge-light-danger badge-sm mt-1 align-self-start">Required</span>
                                                            @endif
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-light-primary">{{ ucfirst($task->phase) }}</span>
                                                    </td>
                                                    <td>
                                                        <span class="badge {{ $task->status->badgeClass() }}">
                                                            {{ $task->status->label() }}
                                                        </span>
                                                        @if($task->is_overdue)
                                                        <span class="badge badge-danger badge-sm ms-1">Overdue</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($task->sla_breach_at)
                                                            <span class="{{ $task->is_overdue ? 'text-danger fw-bold' : 'text-muted' }}">
                                                                {{ $task->sla_breach_at->format('M d, H:i') }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted">No deadline</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @if($leadTasks->count() > 5)
                                    <div class="text-center mt-3">
                                        <a href="/agents/lead-tasks/{{ encrypt($lead->id) }}" class="btn btn-sm btn-light-info">
                                            View all {{ $leadTasks->count() }} tasks
                                        </a>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-10">
                                <i class="las la-tasks fs-3x text-muted"></i>
                                <div class="text-muted mt-3">No tasks found</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
            <!--end::Tasks Tab-->
            
            <!--begin::Performance Tab-->
            <div class="tab-pane fade" id="profile_performance" role="tabpanel">
                <div class="row g-6">
                    <!--Performance Metrics-->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bolder">Performance Metrics</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-6">
                                    <div class="col-md-3">
                                        <div class="border border-dashed border-gray-300 rounded p-6 text-center">
                                            <i class="las la-chart-line fs-3x text-primary mb-3"></i>
                                            <div class="fs-2 fw-bolder text-gray-800">{{ $performanceMetrics['conversion_rate'] }}%</div>
                                            <div class="text-muted fs-6">Conversion Rate</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border border-dashed border-gray-300 rounded p-6 text-center">
                                            <i class="las la-check-double fs-3x text-success mb-3"></i>
                                            <div class="fs-2 fw-bolder text-gray-800">{{ $performanceMetrics['task_completion_rate'] }}%</div>
                                            <div class="text-muted fs-6">Task Completion</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border border-dashed border-gray-300 rounded p-6 text-center">
                                            <i class="las la-clock fs-3x text-warning mb-3"></i>
                                            <div class="fs-2 fw-bolder text-gray-800">{{ $performanceMetrics['avg_response_time'] }}</div>
                                            <div class="text-muted fs-6">Avg Response Time</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border border-dashed border-gray-300 rounded p-6 text-center">
                                            <i class="las la-star fs-3x text-info mb-3"></i>
                                            <div class="fs-2 fw-bolder text-gray-800">{{ $performanceMetrics['customer_satisfaction'] }}</div>
                                            <div class="text-muted fs-6">Customer Satisfaction</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!--Compensation Details (if applicable)-->
                    @if($agent->base_salary || $agent->commission_rate || $agent->hourly_rate || $agent->project_fee)
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <div class="card-title">
                                    <h3 class="fw-bolder">Compensation Details</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    @if($agent->agent_type === 'contractor')
                                        <!--Contractor Compensation-->
                                        @if($agent->hourly_rate)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Hourly Rate</div>
                                            <div class="fs-5 fw-bolder text-gray-800">${{ number_format($agent->hourly_rate, 2) }}/hr</div>
                                        </div>
                                        @endif
                                        
                                        @if($agent->project_fee)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Project Fee</div>
                                            <div class="fs-5 fw-bolder text-gray-800">${{ number_format($agent->project_fee, 2) }}</div>
                                        </div>
                                        @endif
                                        
                                        @if($agent->commission_rate)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Commission Rate</div>
                                            <div class="fs-5 fw-bolder text-gray-800">{{ $agent->commission_rate }}%</div>
                                        </div>
                                        @endif
                                        
                                        @if($agent->commission_type)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Commission Type</div>
                                            <div class="fs-5 fw-bolder text-gray-800">{{ ucfirst(str_replace('_', ' ', $agent->commission_type)) }}</div>
                                        </div>
                                        @endif
                                    @else
                                        <!--Employee/Agency Compensation-->
                                        @if($agent->base_salary)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Base Salary</div>
                                            <div class="fs-5 fw-bolder text-gray-800">${{ number_format($agent->base_salary, 2) }}</div>
                                        </div>
                                        @endif
                                        
                                        @if($agent->commission_rate)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Commission Rate</div>
                                            <div class="fs-5 fw-bolder text-gray-800">{{ $agent->commission_rate }}%</div>
                                        </div>
                                        @endif
                                        
                                        @if($agent->commission_type)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Commission Type</div>
                                            <div class="fs-5 fw-bolder text-gray-800">{{ ucfirst(str_replace('_', ' ', $agent->commission_type)) }}</div>
                                        </div>
                                        @endif
                                        
                                        @if($agent->bonus_rate)
                                        <div class="col-md-4 mb-7">
                                            <div class="fw-bolder text-gray-600 mb-2">Bonus Rate</div>
                                            <div class="fs-5 fw-bolder text-gray-800">{{ $agent->bonus_rate }}%</div>
                                        </div>
                                        @endif
                                        
                                        @if($agent->benefits && $agent->agent_type === 'employee')
                                        <div class="col-12 mt-5">
                                            <div class="fw-bolder text-gray-600 mb-3">Benefits & Allowances</div>
                                            <div>
                                                @foreach(json_decode($agent->benefits, true) ?? [] as $benefit)
                                                    <span class="badge badge-light-success me-2 mb-2">{{ ucfirst(str_replace('_', ' ', $benefit)) }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
            <!--end::Performance Tab-->

            <!--begin::Commission Tab-->
            @if($canSeeCommission)
            <div class="tab-pane fade" id="profile_commission" role="tabpanel">
                @include('pages.business.agents.partials.commission', ['title' => 'Commission for this agent', 'formHash' => 'profile_commission', 'statementUrl' => route('tenant_management.agent_commissions.statement', ['agentId' => $agent->id, 'period' => 'custom', 'from' => $period['from'], 'to' => $period['to']])])
            </div>
            @endif
            <!--end::Commission Tab-->
            
            <!--begin::Activity Tab-->
            <div class="tab-pane fade" id="profile_activity" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex flex-row justify-content-between">
                        <div class="card-title">
                            <h3 class="fw-bolder">Activity Timeline</h3>
                        </div>
                        <div class="card-toolbar">
                            <div class="d-flex align-items-center">
                                <span class="text-muted me-3">Filter:</span>
                                <select class="form-select form-select-sm form-select-solid w-150px" id="activity_filter">
                                    <option value="all">All Activities</option>
                                    <option value="leads">Lead Actions</option>
                                    <option value="tasks">Task Updates</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        @forelse($activityTimeline as $activity)
                            <div class="timeline-item activity-item" data-type="{{ $activity['type'] ?? 'general' }}">
                                <div class="timeline-line w-40px"></div>
                                
                                <div class="timeline-icon symbol symbol-circle symbol-40px mb-5">
                                    <div class="symbol-label bg-light-{{ $activity['color'] }}">
                                        <i class="fa {{ $activity['icon'] }} text-{{ $activity['color'] }} fs-2"></i>
                                    </div>
                                </div>
                                
                                <div class="timeline-content mb-10 mt-n1">
                                    <div class="overflow-auto pe-3">
                                        <div class="d-flex align-items-center justify-content-between mb-3">
                                            <div class="d-flex flex-column flex-grow-1">
                                                <a href="#" class="text-gray-800 text-hover-primary fw-bold fs-6">
                                                    {{ $activity['title'] }}
                                                </a>
                                                <span class="text-muted fw-semibold d-block mt-1">
                                                    {{ $activity['description'] }}
                                                </span>
                                            </div>
                                            <span class="badge badge-light-{{ $activity['color'] }} fs-8 fw-bold ms-3">
                                                {{ ucfirst($activity['type'] ?? 'Activity') }}
                                            </span>
                                        </div>
                                        
                                        <div class="d-flex align-items-center mt-2">
                                            <div class="d-flex align-items-center me-5">
                                                <i class="las la-clock fs-5 text-muted me-1"></i>
                                                <span class="text-muted fs-7">
                                                    {{ \Carbon\Carbon::parse($activity['date'])->diffForHumans() }}
                                                </span>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <i class="las la-calendar fs-5 text-muted me-1"></i>
                                                <span class="text-muted fs-7">
                                                    {{ \Carbon\Carbon::parse($activity['date'])->format('M d, Y \a\t h:i A') }}
                                                </span>
                                            </div>
                                        </div>
                                        
                                        @if(isset($activity['details']))
                                        <div class="bg-light-{{ $activity['color'] }} rounded p-4 mt-3">
                                            <div class="text-gray-700 fs-7">
                                                {!! $activity['details'] !!}
                                            </div>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center py-20">
                                <div class="mb-5">
                                    <i class="las la-history fs-5x text-muted opacity-50"></i>
                                </div>
                                <div class="fs-4 fw-bold text-gray-700 mb-2">No Activity Yet</div>
                                <div class="text-muted fs-6">
                                    Activities will appear here as the agent interacts with leads and tasks
                                </div>
                            </div>
                        @endforelse
                        
                        @if(count($activityTimeline) > 10)
                        <div class="text-center mt-5">
                            <button type="button" class="btn btn-light-primary btn-sm" id="load_more_activity">
                                <i class="las la-arrow-down me-1"></i>
                                Load More Activities
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            <!--end::Activity Tab-->
            
        </div>
        <!--end::Tab Content-->

    </div>
</div>

<script>
    // Open the tab named in the URL hash (e.g. after changing the commission period), and keep the hash in step.
    document.addEventListener('DOMContentLoaded', function () {
        const open = hash => {
            const link = hash && document.querySelector(`[data-bs-toggle="tab"][href="${hash}"]`);
            if (link && window.bootstrap) bootstrap.Tab.getOrCreateInstance(link).show();
        };
        open(location.hash);
        document.querySelectorAll('#profileTabContent').length && document.querySelectorAll('[data-bs-toggle="tab"][href^="#profile_"]').forEach(link => {
            link.addEventListener('shown.bs.tab', () => history.replaceState(null, '', location.pathname + location.search + link.getAttribute('href')));
        });
    });
</script>
@endsection
