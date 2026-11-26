@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header" data-kt-sticky="true" data-kt-sticky-name="header" data-kt-sticky-offset="{default: '200px', lg: '300px'}">
    <div class="container-xxl d-flex align-items-center justify-content-between" id="kt_header_container">
        <div class="page-title d-flex flex-column align-items-start justify-content-center flex-wrap me-lg-2 pb-2 pb-lg-0" data-kt-swapper="true" data-kt-swapper-mode="prepend" data-kt-swapper-parent="{default: '#kt_content_container', lg: '#kt_header_container'}">
            <h1 class="d-flex flex-column text-dark fw-bolder my-0 fs-1">Lead profile</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-base my-1">
                <li class="breadcrumb-item text-muted">
                    @businessCan('crm_management', 'view')<a href="/customer_relationship" class="text-muted text-hover-primary">CRM</a>@else<span class="text-muted">CRM</span>@endbusinessCan
                </li>
                <li class="breadcrumb-item text-muted">
                    <a href="{{ route('lead_management.index') }}" class="text-muted text-hover-primary">Leads</a>
                </li>
                <li class="breadcrumb-item text-dark">{{ $lead->full_name }}</li>
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
    <div class="container-xxl lead-profile-page" id="kt_content_container">
        
        @php
            $profile = $lead->profile;
            $isBusiness = $profile->lead_type === 'Business';
            $initials = collect(preg_split('/\s+/', trim($lead->full_name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
            $priorityTone = ['hot' => 'danger', 'warm' => 'warning', 'cold' => 'info'][$lead->priority] ?? 'info';
            $statusLabel = ucfirst(str_replace('_', ' ', $lead->status));
            $age = (int) $lead->created_at->copy()->startOfDay()->diffInDays(now()->startOfDay()); // calendar days
            $ageLabel = $age < 1 ? 'Today' : ($age === 1 ? '1 day' : $age . ' days');
            $score = max(0, min(100, (int) $lead->lead_score));
            $missing = $lead->missingForConversion();
            $field = fn ($label, $value, $wide = false) => ['label' => $label, 'value' => $value, 'wide' => $wide];
            $nice = fn ($v) => $v ? ucfirst(str_replace('_', ' ', $v)) : null;
            $isClosed = str_starts_with((string) $lead->status, 'closed');
            $statusTone = $lead->converted_tenant_id || $lead->converted_sale_id || $lead->status === 'closed_won' ? 'is-won' : ($lead->status === 'closed_lost' ? 'is-lost' : 'is-open');
            $priorityChip = ['hot' => 'is-hot', 'warm' => 'is-warm', 'cold' => 'is-cold'][$lead->priority] ?? 'is-cold';
        @endphp

        {{-- Lead header --}}
        <section class="lead-profile-hero mb-6" aria-labelledby="lead-name">
            <div class="lead-profile-hero-top">
                <div class="lead-profile-identity">
                    <span class="lead-profile-avatar is-{{ $priorityTone }}" aria-hidden="true">{{ $initials ?: '?' }}</span>
                    <div class="min-w-0">
                        <div class="lead-profile-name-row">
                            <h2 id="lead-name" class="lead-profile-name">{{ $lead->full_name }}</h2>
                            <span class="lead-chip {{ $statusTone }}" data-lead-status><span class="dot" aria-hidden="true"></span>{{ $statusLabel }}</span>
                            @unless($isClosed)<span class="lead-chip {{ $priorityChip }}">{{ ucfirst($lead->priority) }} priority</span>@endunless
                            <span class="lead-chip"><i class="bi bi-{{ $isBusiness ? 'building' : 'person' }}" aria-hidden="true"></i>{{ $isBusiness ? 'Business' : 'Individual' }}</span>
                        </div>
                        <ul class="lead-profile-facts">
                            @if($lead->primary_email)<li><i class="bi bi-envelope" aria-hidden="true"></i><a href="mailto:{{ $lead->primary_email }}">{{ $lead->primary_email }}</a></li>@endif
                            @if($lead->primary_phone)<li><i class="bi bi-telephone" aria-hidden="true"></i><a href="tel:{{ $lead->primary_phone }}">{{ $lead->primary_phone }}</a></li>@endif
                            <li><i class="bi bi-calendar3" aria-hidden="true"></i>Created {{ $lead->created_at->format('j M Y') }}</li>
                            <li data-lead-added-by><i class="bi bi-person-plus" aria-hidden="true"></i>Added by {{ $lead->creator ? trim($lead->creator->first_name.' '.$lead->creator->last_name) : 'not recorded' }}{{ $lead->source ? ' · '.ucfirst(str_replace('_', ' ', $lead->source)) : '' }}</li>
                        </ul>
                    </div>
                </div>
                <div class="lead-profile-actions">
                    @php
                        $salesOn = app(\App\Services\Access\ModuleService::class)->enabled('sales');
                        $buyer = $lead->isBuyer() && $salesOn;
                    @endphp
                    @if($lead->converted_sale_id)
                        <span class="lead-converted-note align-self-center" data-lead-converted><i class="bi bi-check2-circle" aria-hidden="true"></i>Bought a house{{ $lead->convertedSale ? ' · '.$lead->convertedSale->reference : '' }}</span>
                        @if($lead->convertedSale && $salesOn && auth()->user()->hasBusinessPermission('sales_management', 'view'))<a class="btn btn-sm btn-primary" href="{{ route('sales.show', $lead->convertedSale->reference) }}"><i class="bi bi-house-check" aria-hidden="true"></i>Open sale</a>@endif
                    @elseif($buyer)
                        @if(auth()->user()->hasBusinessPermission('sales_management', 'create'))
                            @if($lead->readyToConvert())
                                <a class="btn btn-sm btn-primary" href="{{ route('sales.create', ['lead' => $lead->id]) }}" data-record-sale><i class="bi bi-house-check" aria-hidden="true"></i>Record sale</a>
                            @else
                                <span class="d-inline-block" tabindex="0" title="First {{ implode(' and ', $missing) }}."><button type="button" class="btn btn-sm btn-light" disabled>Record sale</button></span>
                            @endif
                        @endif
                    @elseif($lead->converted_tenant_id)
                        <span class="lead-converted-note align-self-center" data-lead-converted><i class="bi bi-check2-circle" aria-hidden="true"></i>Converted to tenant{{ $lead->convertedTenant ? ' · '.$lead->convertedTenant->tenancy_ref : '' }}</span>
                        @if($lead->convertedTenant)@businessCan('tenant_management', 'view')<a class="btn btn-sm btn-primary" href="{{ url('/tenant_management/tenancy/'.$lead->convertedTenant->tenancy_ref.'/view') }}"><i class="bi bi-house-door" aria-hidden="true"></i>Open tenancy</a>@endbusinessCan @endif
                    @else
                        @businessCan('tenant_management', 'create')
                            @if($lead->readyToConvert())
                                <a class="btn btn-sm btn-primary" href="{{ route('tenant_management.onboarding', ['lead' => encrypt($lead->id)]) }}" data-convert-lead><i class="bi bi-plus-lg" aria-hidden="true"></i>Convert to tenant</a>
                            @else
                                <span class="d-inline-block" tabindex="0" title="First {{ implode(' and ', $missing) }}."><button type="button" class="btn btn-sm btn-light" disabled data-convert-lead-disabled>Convert to tenant</button></span>
                            @endif
                        @endbusinessCan
                    @endif
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#kt_modal_update_status"><i class="bi bi-arrow-repeat" aria-hidden="true"></i>Update status</button>
                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#kt_modal_add_interaction"><i class="bi bi-plus-circle" aria-hidden="true"></i>Add interaction</button>
                </div>
            </div>

            @if(!$lead->converted_tenant_id && !$lead->converted_sale_id && (!$lead->details_confirmed_at || !$lead->qualification_confirmed_at || $lead->readyToConvert()))
                @php
                    [$tone, $icon, $title, $text] = !$lead->details_confirmed_at
                        ? ['warning', 'exclamation-circle', 'Next: confirm the lead details', 'Check the contact details and requirements before the lead moves beyond New.']
                        : (!$lead->qualification_confirmed_at
                            ? ['primary', 'clipboard-check', 'Next: confirm the qualification', 'Confirm the budget, timing and fit. The lead can then be converted to a tenant.']
                            : ['success', 'check-circle', 'Ready to convert', ($lead->isBuyer() ? 'Details and qualification are confirmed. Record the sale when the buyer agrees a price.' : 'Details and qualification are confirmed. Convert the lead when the tenancy is agreed.')]);
                @endphp
                <div class="lead-profile-next is-{{ $tone }}" role="status" data-lead-next-step>
                    <i class="bi bi-{{ $icon }}" aria-hidden="true"></i>
                    <div class="flex-grow-1"><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
                    @if(!$lead->details_confirmed_at)
                        <button type="button" class="btn btn-sm btn-warning" id="confirm-lead-details">Confirm details</button>
                    @elseif(!$lead->qualification_confirmed_at)
                        <button type="button" class="btn btn-sm btn-primary" id="confirm-lead-qualification">Confirm qualification</button>
                    @endif
                </div>
            @endif

            <div class="lead-profile-metrics" aria-label="Lead summary">
                <div>
                    <span>Lead score</span>
                    <strong>{{ $score }}<small>/100</small></strong>
                    <div class="lead-profile-score" role="img" aria-label="Lead score {{ $score }} out of 100"><i style="width: {{ $score }}%"></i></div>
                </div>
                <div><span>Agent</span><strong class="fs-6" data-lead-agent>@if($lead->assignedAgent){{ profile_link(agent_profile_url($lead->assignedAgent), $lead->assignedAgent->full_name, 'lead-profile-link') }}@else Not assigned @endif</strong><small>{{ $lead->complex->title ?? 'No property chosen' }}</small></div>
                <div><span>Interactions</span><strong>{{ $engagementMetrics['total_interactions'] }}</strong><small>Last contact {{ strtolower($engagementMetrics['last_contact']) }}</small></div>
                <div><span>Lead age</span><strong>{{ $ageLabel }}</strong><small>Since {{ $lead->created_at->format('j M Y') }}</small></div>
            </div>

            @include('pages.business.crm.lead_management.partials.path-to-tenant')

            <nav class="nav lead-profile-tabs" role="tablist" aria-label="Lead sections">
                <a class="nav-link active" id="lead-tab-overview" data-bs-toggle="tab" href="#kt_lead_overview" role="tab" aria-controls="kt_lead_overview" aria-selected="true">Overview</a>
                <a class="nav-link" id="lead-tab-requirements" data-bs-toggle="tab" href="#kt_lead_requirements" role="tab" aria-controls="kt_lead_requirements" aria-selected="false">Requirements</a>
                <a class="nav-link" id="lead-tab-activity" data-bs-toggle="tab" href="#kt_lead_activity" role="tab" aria-controls="kt_lead_activity" aria-selected="false">Activity <span class="lead-profile-count">{{ count($activityTimeline) }}</span></a>
                <a class="nav-link" id="lead-tab-tasks" data-bs-toggle="tab" href="#kt_lead_tasks" role="tab" aria-controls="kt_lead_tasks" aria-selected="false">Tasks <span class="lead-profile-count">{{ $phaseProgress['completed'] }}/{{ $phaseProgress['total'] }}</span></a>
            </nav>
        </section>

        @php
            $contact = $isBusiness ? [
                $field('Company', $profile->company_name),
                $field('Registration number', $profile->registration_number),
                $field('Contact person', trim(($profile->contact_person ?? '').($profile->contact_title ? ' · '.$profile->contact_title : ''))),
                $field('Business email', $profile->business_email ? '<a href="mailto:'.e($profile->business_email).'">'.e($profile->business_email).'</a>' : null),
                $field('Business phone', $profile->business_phone ? '<a href="tel:'.e($profile->business_phone).'">'.e($profile->business_phone).'</a>' : null),
                $field('Industry', $profile->industry),
                $field('Company size', $profile->company_size),
                $field('Business address', $profile->business_address, true),
            ] : [
                $field('Full name', trim($profile->first_name.' '.$profile->last_name)),
                $field('Occupation', $profile->occupation),
                $field('Email', $profile->email ? '<a href="mailto:'.e($profile->email).'">'.e($profile->email).'</a>' : null),
                $field('Phone', $profile->phone ? '<a href="tel:'.e($profile->phone).'">'.e($profile->phone).'</a>' : null),
                $field('Date of birth', $profile->date_of_birth?->format('j M Y')),
                $field('Current address', $profile->current_address, true),
            ];
            $contact = array_merge($contact, [
                $field('Prefers', trim(collect([$nice($lead->contact_method), $nice($lead->contact_time)])->filter()->implode(' · '))),
                $field('Follow-up', $lead->follow_up_date?->format('j M Y, H:i')),
                $field('Last contacted', $engagementMetrics['last_contact']),
                $field('Contact attempts', (string) $engagementMetrics['contact_attempts']),
            ]);
            $details = [
                $field('Source', $nice($lead->source)),
                $field('Referral', $lead->referral_details),
                $field('Assigned agent', $lead->assignedAgent ? profile_link(agent_profile_url($lead->assignedAgent), $lead->assignedAgent->full_name)->toHtml().($lead->assignedAgent->email ? '<span class="lead-profile-sub"><a href="mailto:'.e($lead->assignedAgent->email).'">'.e($lead->assignedAgent->email).'</a></span>' : '') : '<span class="text-muted">Not assigned</span>'),
                $field('Property', $lead->complex ? '<a href="'.e(route('complex.show', $lead->complex->slug)).'">'.e($lead->complex->title).'</a>' : null),
                $field('Status', e($statusLabel).($lead->workflow_phase ? '<span class="lead-profile-sub">'.e($nice($lead->workflow_phase)).' phase</span>' : '')),
                $field('Notes', $lead->notes ? nl2br(e($lead->notes)) : null, true),
            ];
            $sqft = $lead->min_sq_area && $lead->max_sq_area ? number_format($lead->min_sq_area).' – '.number_format($lead->max_sq_area).' sq ft'
                : ($lead->min_sq_area ? 'From '.number_format($lead->min_sq_area).' sq ft' : ($lead->max_sq_area ? 'Up to '.number_format($lead->max_sq_area).' sq ft' : null));
            $needs = [
                ['bi-house', 'Property type', $lead->property_type],
                ['bi-tag', 'Purpose', $nice($lead->purpose)],
                ['bi-cash-stack', 'Budget', $lead->budget_range],
                ['bi-geo-alt', 'Location', $lead->location_preference],
                ['bi-hourglass-split', 'Timeline', $nice($lead->timeline)],
                ['bi-door-open', 'Bedrooms', $lead->bedrooms],
                ['bi-droplet', 'Bathrooms', $lead->bathrooms],
                ['bi-aspect-ratio', 'Floor area', $sqft],
                ['bi-briefcase', 'Business use', $lead->business_use],
            ];
            $render = fn ($rows) => collect($rows)->filter(fn ($r) => filled(strip_tags((string) $r['value'])) || str_contains((string) $r['value'], 'text-muted'));
        @endphp

        <div class="tab-content" id="leadProfileTabContent">
            {{-- Overview --}}
            <div class="tab-pane fade show active" id="kt_lead_overview" role="tabpanel" aria-labelledby="lead-tab-overview">
                <div class="row g-6">
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="lead-contact-title">
                            <h3 id="lead-contact-title">{{ $isBusiness ? 'Company & contact' : 'Contact' }}</h3>
                            <dl class="lead-profile-fields">
                                @foreach($render($contact) as $row)
                                    <div class="{{ $row['wide'] ? 'is-wide' : '' }}"><dt>{{ $row['label'] }}</dt><dd>{!! $row['value'] !!}</dd></div>
                                @endforeach
                            </dl>
                        </section>
                    </div>
                    <div class="col-xl-6">
                        <section class="lead-profile-card h-100" aria-labelledby="lead-details-title">
                            <h3 id="lead-details-title">Lead details</h3>
                            <dl class="lead-profile-fields">
                                @foreach($render($details) as $row)
                                    <div class="{{ $row['wide'] ? 'is-wide' : '' }}"><dt>{{ $row['label'] }}</dt><dd>{!! $row['value'] !!}</dd></div>
                                @endforeach
                            </dl>
                        </section>
                    </div>
                </div>
            </div>

            {{-- Requirements --}}
            <div class="tab-pane fade" id="kt_lead_requirements" role="tabpanel" aria-labelledby="lead-tab-requirements">
                <section class="lead-profile-card" aria-labelledby="lead-needs-title">
                    <h3 id="lead-needs-title">What they are looking for</h3>
                    <div class="lead-profile-needs">
                        @foreach(collect($needs)->filter(fn ($n) => filled($n[2])) as [$icon, $label, $value])
                            <div class="lead-profile-need"><i class="bi {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span><strong>{{ $value }}</strong></div>
                        @endforeach
                    </div>
                    @if($lead->special_requirements)
                        <div class="lead-profile-note"><span>Special requirements</span><p class="mb-0">{!! nl2br(e($lead->special_requirements)) !!}</p></div>
                    @endif
                </section>
            </div>

            {{-- Activity --}}
            <div class="tab-pane fade" id="kt_lead_activity" role="tabpanel" aria-labelledby="lead-tab-activity">
                <section class="lead-profile-card" aria-labelledby="lead-activity-title">
                    <h3 id="lead-activity-title">Activity <small>{{ count($activityTimeline) }} {{ \Illuminate\Support\Str::plural('entry', count($activityTimeline)) }}, newest first</small></h3>
                    @if(empty($activityTimeline))
                        <p class="lead-profile-empty">No activity recorded yet.</p>
                    @else
                    <ol class="lead-profile-timeline">
                        @foreach($activityTimeline as $activity)
                            @php
                                $extra = [];
                                if (isset($activity['details']) && is_array($activity['details'])) {
                                    if ($activity['type'] === 'task_completed' && isset($activity['details']['completion_data']) && is_array($activity['details']['completion_data'])) {
                                        $extra = $activity['details']['completion_data'];
                                    } elseif (!in_array($activity['type'], ['phase_advanced', 'task_auto_started', 'task_started', 'lead_created', 'converted_to_tenant', 'status_change'], true)) {
                                        $extra = collect($activity['details'])->except(['completion_data', 'timestamp', 'user_id', 'tenancy_id'])->all();
                                    }
                                }
                                $extra = collect($extra)->filter(fn ($v) => filled($v) && !is_array($v));
                            @endphp
                            <li class="lead-profile-event">
                                <span class="lead-profile-event-icon is-{{ $activity['color'] }}" aria-hidden="true"><i class="bi {{ $activity['icon'] }}"></i></span>
                                <div class="lead-profile-event-body">
                                    <div class="lead-profile-event-head">
                                        <strong>{{ \Illuminate\Support\Str::ucfirst(mb_strtolower($activity['title'])) }}</strong>
                                        <time datetime="{{ $activity['timestamp']->toIso8601String() }}" title="{{ $activity['timestamp']->format('j M Y, H:i') }}">{{ $activity['timestamp']->format('j M Y, H:i') }} · {{ $activity['timestamp']->diffForHumans() }}</time>
                                    </div>
                                    @if($activity['description'])<p>{{ $activity['description'] }}</p>@endif
                                    @if($activity['type'] === 'phase_advanced' && isset($activity['details']['new_phase']))
                                        <div class="lead-profile-event-phase"><span class="badge badge-light">{{ ucfirst($activity['details']['old_phase'] ?? '') }}</span><i class="bi bi-arrow-right" aria-hidden="true"></i><span class="badge badge-light-success">{{ ucfirst($activity['details']['new_phase']) }}</span></div>
                                    @endif
                                    @if($extra->isNotEmpty())
                                        <dl class="lead-profile-event-extra">
                                            @foreach($extra as $key => $value)<div><dt>{{ ucfirst(str_replace('_', ' ', $key)) }}</dt><dd>{{ is_bool($value) ? ($value ? 'Yes' : 'No') : ucfirst(str_replace('_', ' ', (string) $value)) }}</dd></div>@endforeach
                                        </dl>
                                    @endif
                                    @if(!empty($activity['user']))<div class="lead-profile-event-by"><i class="bi bi-person-circle" aria-hidden="true"></i>{{ $activity['user']->first_name }} {{ $activity['user']->last_name }}</div>@endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    @endif
                </section>
            </div>

            {{-- Tasks --}}
            <div class="tab-pane fade" id="kt_lead_tasks" role="tabpanel" aria-labelledby="lead-tab-tasks">
                <section class="lead-profile-card" aria-labelledby="lead-tasks-title">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
                        <h3 id="lead-tasks-title" class="mb-0">Agent tasks <small>{{ $phaseProgress['completed'] }} of {{ $phaseProgress['total'] }} done</small></h3>
                        @if(can_open('/agents/lead-tasks/x'))<a href="/agents/lead-tasks/{{ Crypt::encrypt($lead->id) }}" class="btn btn-sm btn-light-primary"><i class="bi bi-list-task" aria-hidden="true"></i>Work on tasks</a>@endif
                    </div>
                    @if($phaseProgress['total'] > 0)
                        <div class="lead-profile-progress">
                            <div class="progress h-8px"><div class="progress-bar lead-profile-bar" role="progressbar" style="width: {{ $phaseProgress['percentage'] }}%" aria-valuenow="{{ $phaseProgress['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Tasks done"></div></div>
                            <div class="lead-profile-progress-legend">
                                <span><i class="bi bi-check-circle-fill lead-legend-done" aria-hidden="true"></i>{{ $phaseProgress['completed'] }} done</span>
                                <span><i class="bi bi-clock-fill lead-legend-progress" aria-hidden="true"></i>{{ $phaseProgress['in_progress'] }} in progress</span>
                                <span><i class="bi bi-circle lead-legend-todo" aria-hidden="true"></i>{{ $phaseProgress['pending'] }} to do</span>
                                <span class="ms-auto fw-bold">{{ $phaseProgress['percentage'] }}%</span>
                            </div>
                        </div>
                    @endif
                    @php
                        $tasksByPhase = $lead->tasks->groupBy('phase');
                        $phaseNames = ['qualification' => 'Qualification & matching', 'engagement' => 'Viewing & engagement', 'conversion' => 'Conversion', 'finalization' => 'Finalization'];
                        $statusTone = ['completed' => 'is-won', 'skipped' => '', 'in_progress' => 'is-warm', 'blocked' => 'is-lost', 'pending' => 'is-open'];
                    @endphp
                    @forelse(collect($phaseNames)->filter(fn ($n, $k) => isset($tasksByPhase[$k])) as $phase => $phaseName)
                        @php
                            $phaseTasks = $tasksByPhase[$phase];
                            $phaseDone = $phaseTasks->filter(fn ($t) => in_array($t->status->value, ['completed', 'skipped'], true))->count();
                        @endphp
                        <div class="lead-profile-phase">
                            <div class="lead-profile-phase-head"><h4>{{ $phaseName }}</h4><span>{{ $phaseDone }} of {{ $phaseTasks->count() }} done</span></div>
                            <ol class="lead-profile-tasks">
                                @foreach($phaseTasks as $task)
                                    @php $st = $task->status->value; @endphp
                                    <li class="lead-profile-task is-{{ $st }}">
                                        <span class="lead-profile-task-icon" aria-hidden="true"><i class="bi bi-{{ $st === 'completed' ? 'check-lg' : ($st === 'skipped' ? 'skip-forward' : ($st === 'in_progress' ? 'clock' : 'circle')) }}"></i></span>
                                        <div class="lead-profile-task-body">
                                            <div class="lead-profile-task-title">{{ $task->title }}</div>
                                            @if($task->description)<div class="lead-profile-task-desc">{{ $task->description }}</div>@endif
                                            <div class="lead-profile-task-flags">
                                                @if($task->is_required)<span class="lead-chip">Required</span>@endif
                                                @if($task->blocks_next_phase)<span class="lead-chip is-cold" title="Completing this task moves the lead to its next phase"><i class="bi bi-arrow-right-circle" aria-hidden="true"></i>Moves the lead on</span>@endif
                                                @if($task->sla_breach_at && !in_array($st, ['completed', 'skipped'], true))
                                                    <span class="lead-profile-due {{ $task->isOverdue() ? 'is-overdue' : '' }}"><i class="far fa-clock" aria-hidden="true"></i>{{ $task->isOverdue() ? 'Overdue since' : 'Due' }} {{ $task->sla_breach_at->format('j M, H:i') }}</span>
                                                @elseif($task->completed_at ?? null)
                                                    <span class="lead-profile-due"><i class="far fa-check-circle" aria-hidden="true"></i>Done {{ \Carbon\Carbon::parse($task->completed_at)->format('j M, H:i') }}</span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="lead-chip {{ $statusTone[$st] ?? 'is-open' }} lead-profile-task-status">{{ ucfirst(str_replace('_', ' ', $st)) }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @empty
                        <p class="lead-profile-empty">No tasks yet. Tasks start once the lead has an agent.</p>
                    @endforelse
                </section>
            </div>
        </div>

    </div>
</div>

{{-- Modals --}}
@include('pages.business.crm.lead_management.include.profile_modals')

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    async function confirmLead(type) {
        const response = await fetch(@json(route('lead_management.confirm', ['id' => Crypt::encrypt($lead->id)])), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ confirmation_type: type }),
        });

        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload.message || 'Unable to confirm this lead.');
        }

        window.location.reload();
    }

    document.getElementById('confirm-lead-details')?.addEventListener('click', function () {
        confirmLead('details').catch(error => window.alert(error.message));
    });

    document.getElementById('confirm-lead-qualification')?.addEventListener('click', function () {
        confirmLead('qualification').catch(error => window.alert(error.message));
    });
});
</script>

@endsection
