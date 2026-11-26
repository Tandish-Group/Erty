@extends('pages.business.layouts.app')

@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'CRM'])
@php
    use App\Services\Crm\LeadPipeline;
    $agentName = fn ($a) => $a ? (trim($a->first_name . ' ' . $a->last_name) ?: $a->company_name) : null;
    $purposes = ['rent' => 'Rent', 'buy' => 'Buy', 'lease' => 'Lease'];
    $lookingFor = function ($lead) use ($purposes) {
        $want = array_filter([
            $purposes[$lead->purpose] ?? null,
            $lead->bedrooms ? $lead->bedrooms . ' bed' : null,
            $lead->property_type,
            $lead->location_preference ? 'in ' . $lead->location_preference : null,
        ]);
        return $want ? implode(' · ', $want) : 'Need not recorded yet';
    };
    $budget = fn ($lead) => $lead->budget_max ? ($lead->budget_min ? money($lead->budget_min, null, 0) . ' – ' : 'Up to ') . money($lead->budget_max, null, 0) : null;
    $tools = array_filter([
        ['/customer_relationship/lead_management', 'fa-user-plus', 'Leads', 'Every lead, with filters and search'],
        ['/agents/tasks', 'fa-tasks', 'Follow-up tasks', 'The tasks agents work for each lead'],
        ['/customer_relationship/document-center', 'fa-folder-open', 'Document centre', 'Files across tenancies, agents, expenses and more'],
        ['/customer_relationship/reports', 'fa-chart-line', 'CRM reports', 'Sources, conversion and agent follow-up'],
    ], fn ($t) => can_open($t[0]));
    $canLeads = can_open('/customer_relationship/lead_management');
    $maxSource = max(1, (int) $sources->max('count'));
@endphp

<div class="content d-flex flex-column flex-column-fluid tenant-workspace crm-home" id="kt_content">
    <div class="container-xxl" id="kt_content_container">
        <header class="tenant-page-heading">
            <div>
                <span class="tenant-eyebrow">Customer relationships</span>
                <h1>Leads &amp; pipeline</h1>
                <p>Where every prospect stands, what has gone quiet and which follow-ups are late. A lead moves through four phases and becomes a tenant or a buyer.</p>
            </div>
            <div class="tenant-heading-actions">
                @if($canLeads)<a class="btn btn-light" href="/customer_relationship/lead_management"><i class="fas fa-list me-1" aria-hidden="true"></i>All leads</a>@endif
                @businessCan('lead_management', 'create')<a class="btn btn-primary" href="/customer_relationship/lead_management/onboarding/lead"><i class="fas fa-plus me-1" aria-hidden="true"></i>Capture a lead</a>@endbusinessCan
            </div>
        </header>

        <div class="tenant-metrics report-metrics mb-6" style="--report-cols: 4">
            <div>
                <span>Open leads</span>
                <strong>{{ number_format($metrics['open']) }}</strong>
                <small>{{ $metrics['hot'] }} hot · {{ $metrics['newWeek'] }} new this week</small>
            </div>
            <div>
                <span>Need attention</span>
                <strong class="{{ $metrics['attention'] ? 'crm-figure-warn' : '' }}">{{ number_format($metrics['attention']) }}</strong>
                <small>Not contacted for {{ $staleDays }} days, or follow-up date passed</small>
            </div>
            <div>
                <span>Follow-ups overdue</span>
                <strong class="{{ $metrics['overdue'] ? 'crm-figure-warn' : '' }}">{{ number_format($metrics['overdue']) }}</strong>
                <small>Tasks past their due date or response deadline</small>
            </div>
            <div>
                <span>Converted, last 30 days</span>
                <strong>{{ number_format($metrics['converted']) }}</strong>
                <small>{{ $metrics['tenants'] }} {{ \Illuminate\Support\Str::plural('tenant', $metrics['tenants']) }} · {{ $metrics['buyers'] }} {{ \Illuminate\Support\Str::plural('buyer', $metrics['buyers']) }} · {{ $metrics['received'] }} {{ \Illuminate\Support\Str::plural('lead', $metrics['received']) }} received</small>
            </div>
        </div>

        <section class="tenant-panel tenant-panel--padded mb-6" aria-labelledby="crm-pipeline-title">
            <div class="crm-panel-head">
                <div><h2 id="crm-pipeline-title" class="report-section-title">Pipeline today</h2><p>Open leads in each phase. A lead in one phase for over {{ $staleDays }} days is counted as stuck.</p></div>
            </div>
            <ol class="crm-phases">
                @foreach($phases as $phase)
                    <li class="crm-phase">
                        <span class="crm-phase-step">{{ $phase['step'] }}</span>
                        <div class="crm-phase-body">
                            <h3>{{ $phase['label'] }}</h3>
                            <p>{{ $phase['about'] }}</p>
                        </div>
                        <div class="crm-phase-figures">
                            <strong>{{ $phase['count'] }}</strong><span>{{ \Illuminate\Support\Str::plural('lead', $phase['count']) }}</span>
                            @if($phase['stuck'])<em class="crm-chip crm-chip--warn">{{ $phase['stuck'] }} stuck</em>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="crm-grid mb-6">
            <section class="tenant-panel tenant-panel--padded" aria-labelledby="crm-attention-title">
                <div class="crm-panel-head">
                    <div><h2 id="crm-attention-title" class="report-section-title">Leads needing attention</h2><p>Quiet the longest first.</p></div>
                    @if($metrics['attention'] > $attention->count() && can_open('/customer_relationship/reports'))<a class="btn btn-sm btn-light" href="/customer_relationship/reports">All {{ $metrics['attention'] }}</a>@endif
                </div>
                @forelse($attention as $row)
                    @php $lead = $row['lead']; $url = lead_profile_url($lead); @endphp
                    <div class="crm-row">
                        <span class="crm-avatar crm-avatar--{{ $lead->priority ?: 'none' }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($lead->full_name, 0, 1)) }}</span>
                        <div class="crm-row-main">
                            @if($url)<a href="{{ $url }}" class="crm-row-title">{{ $lead->full_name }}</a>@else<span class="crm-row-title">{{ $lead->full_name }}</span>@endif
                            <small>{{ $row['reason'] }} · {{ $agentName($lead->assignedAgent) ?? 'No agent' }}</small>
                        </div>
                        <span class="crm-chip crm-chip--{{ $lead->priority ?: 'none' }}">{{ LeadPipeline::priorityLabel($lead->priority) }}</span>
                    </div>
                @empty
                    <div class="crm-clear"><i class="fas fa-check-circle" aria-hidden="true"></i>Every open lead has been followed up.</div>
                @endforelse
            </section>

            <section class="tenant-panel tenant-panel--padded" aria-labelledby="crm-overdue-title">
                <div class="crm-panel-head">
                    <div><h2 id="crm-overdue-title" class="report-section-title">Overdue follow-ups</h2><p>Oldest first.</p></div>
                    @if(can_open('/agents/tasks'))<a class="btn btn-sm btn-light" href="/agents/tasks">All tasks</a>@endif
                </div>
                @forelse($overdue as $row)
                    @php $task = $row['task']; $leadUrl = lead_profile_url($task->lead); $taskUrl = can_open('/agents/tasks/' . $task->id) ? '/agents/tasks/' . $task->id : null; @endphp
                    <div class="crm-row">
                        <span class="crm-days" title="Days overdue">{{ $row['days'] ?? '—' }}<small>{{ \Illuminate\Support\Str::plural('day', (int) $row['days']) }}</small></span>
                        <div class="crm-row-main">
                            @if($taskUrl)<a href="{{ $taskUrl }}" class="crm-row-title">{{ $task->title }}</a>@else<span class="crm-row-title">{{ $task->title }}</span>@endif
                            <small>
                                @if($leadUrl)<a href="{{ $leadUrl }}">{{ $task->lead?->full_name }}</a>@else{{ $task->lead?->full_name }}@endif
                                · {{ $agentName($task->assignedAgent) ?? 'No agent' }}
                            </small>
                        </div>
                    </div>
                @empty
                    <div class="crm-clear"><i class="fas fa-check-circle" aria-hidden="true"></i>No follow-ups are overdue.</div>
                @endforelse
            </section>
        </div>

        <div class="crm-grid crm-grid--wide mb-6">
            <section class="tenant-panel tenant-panel--padded" aria-labelledby="crm-recent-title">
                <div class="crm-panel-head">
                    <div><h2 id="crm-recent-title" class="report-section-title">Newest open leads</h2><p>What they are looking for and who is working them.</p></div>
                    @if($canLeads)<a class="btn btn-sm btn-light" href="/customer_relationship/lead_management">All leads</a>@endif
                </div>
                @forelse($recent as $lead)
                    @php $url = lead_profile_url($lead); @endphp
                    <div class="crm-row">
                        <span class="crm-avatar crm-avatar--{{ $lead->priority ?: 'none' }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($lead->full_name, 0, 1)) }}</span>
                        <div class="crm-row-main">
                            @if($url)<a href="{{ $url }}" class="crm-row-title">{{ $lead->full_name }}</a>@else<span class="crm-row-title">{{ $lead->full_name }}</span>@endif
                            <small>{{ $lookingFor($lead) }}@if($budget($lead)) · {{ $budget($lead) }}@endif</small>
                            <small>{{ ucwords(str_replace('_', ' ', $lead->source ?: 'Source not recorded')) }} · {{ $agentName($lead->assignedAgent) ?? 'No agent' }}@if($lead->complex) · {{ $lead->complex->title }}@endif</small>
                        </div>
                        <div class="crm-row-side">
                            <span class="crm-chip crm-chip--{{ $lead->priority ?: 'none' }}">{{ LeadPipeline::priorityLabel($lead->priority) }}</span>
                            <small>{{ $lead->created_at->diffForHumans() }}</small>
                        </div>
                    </div>
                @empty
                    <div class="tenant-empty py-8"><i class="fas fa-user-plus" aria-hidden="true"></i><h3>No open leads</h3>
                        <p>Capture a lead when someone asks about a property, and Erty sets up the follow-up tasks for their agent.</p>
                        @businessCan('lead_management', 'create')<a class="btn btn-primary" href="/customer_relationship/lead_management/onboarding/lead">Capture a lead</a>@endbusinessCan
                    </div>
                @endforelse
            </section>

            <section class="tenant-panel tenant-panel--padded" aria-labelledby="crm-sources-title">
                <div class="crm-panel-head"><div><h2 id="crm-sources-title" class="report-section-title">Where leads came from</h2><p>Leads received in the last 30 days.</p></div></div>
                @forelse($sources as $source)
                    <div class="crm-bar">
                        <div class="crm-bar-label"><span>{{ $source['label'] }}</span><strong>{{ $source['count'] }}</strong></div>
                        <div class="crm-bar-track"><span style="width: {{ round($source['count'] / $maxSource * 100) }}%"></span></div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No leads in the last 30 days.</p>
                @endforelse
            </section>
        </div>

        @if($tools)
            <nav class="report-cards" aria-label="CRM tools">
                @foreach($tools as [$href, $icon, $title, $about])
                    <a href="{{ $href }}" class="report-card">
                        <i class="fas {{ $icon }}" aria-hidden="true"></i>
                        <span><strong>{{ $title }}</strong><small>{{ $about }}</small></span>
                        <i class="fas fa-chevron-right report-card-arrow" aria-hidden="true"></i>
                    </a>
                @endforeach
            </nav>
        @endif
    </div>
</div>
@endsection
