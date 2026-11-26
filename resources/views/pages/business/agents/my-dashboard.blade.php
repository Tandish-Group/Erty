@extends('pages.business.layouts.app')
@section('content')
@include('pages.business.tenant.partials.header', ['title' => 'My agent dashboard'])

<div class="content d-flex flex-column flex-column-fluid tenant-workspace my-agent-page" id="kt_content">
<div class="container-xxl" id="kt_content_container" data-my-agent-page>
@if(!$agent)
    <header class="tenant-page-heading">
        <div><span class="tenant-eyebrow">My work</span><h1>My agent dashboard</h1><p>Your leads, tasks, tenancies and commission, once you work as an agent.</p></div>
    </header>
    <section class="tenant-panel">
        <div class="tenant-empty">
            <i class="fas fa-user-tie" aria-hidden="true"></i>
            <h3>You don't work as an agent yet</h3>
            <p>Agents have leads and follow-up tasks assigned to them and earn commission on the rent collected from the tenancies they place.</p>
            @if($canCreateAgent)
                <form method="POST" action="{{ route('business.profile.agent') }}">@csrf<button type="submit" class="btn btn-primary">Create my agent profile</button></form>
            @else
                <p class="mb-0">Ask an administrator to make you an agent from Team &amp; roles.</p>
            @endif
        </div>
    </section>
@else
    @php
        $money = fn ($v) => money($v);
        $typeLabels = ['sales_value' => 'Share of collections', 'net_profit' => 'Share of collections after property expenses', 'flat_rate' => 'Flat amount per tenant', 'tiered' => 'Tiered by collections'];
        $due = $statement['due'];
        $earned = $commission['total_commission'] ?? $commission['default_commission'];
        $paidOut = max(0, ($statement['figures']['total_commission'] ?? 0) - $due['commission'] - $due['override'] - ($due['sales'] ?? 0));
        $leadName = fn ($lead) => trim(($lead->profile->first_name ?? '') . ' ' . ($lead->profile->last_name ?? '')) ?: 'Lead #' . $lead->id;
        $myRepairs = \App\Models\MaintenanceRequest::with(['complex:id,title', 'residentialUnit:id,unit_number', 'commercialUnit:id,unit_number'])
            ->where('managed_by', business_owner_id())->where('assigned_user_id', auth()->id())->whereIn('status', \App\Models\MaintenanceRequest::OPEN_STATUSES)
            ->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")->oldest('reported_at')->take(8)->get();
    @endphp

    <header class="tenant-page-heading">
        <div>
            <span class="tenant-eyebrow">My work</span>
            <h1>{{ $agent->full_name }}</h1>
            <p>
                <span class="tenant-badge {{ $agent->is_active ? 'is-active' : '' }}">{{ $agent->is_active ? 'Active agent' : 'Inactive' }}</span>
                {{ ucfirst($agent->agent_type) }}@if($agent->role) · {{ $agent->role->name }}@endif @if($agent->department) · {{ $agent->department->name }}@endif
                @if($agent->reportsTo) · reports to {{ $agent->reportsTo->full_name }}@endif
            </p>
        </div>
        <div class="tenant-heading-actions">
            @businessCan('task_management', 'view')<a class="btn btn-primary" href="{{ url('/agents/tasks') }}">Open my tasks</a>@endbusinessCan
            <a class="btn btn-light" href="{{ route('business.profile.show') }}">My profile</a>
        </div>
    </header>

    <form method="GET" class="my-agent-period" data-commission-form>
        <span>Commission for</span>
        <label class="visually-hidden" for="commission-period">Period</label>
        <select class="form-select form-select-sm" id="commission-period" name="period" onchange="const r = this.form.querySelector('[data-custom-range]'); r.hidden = this.value !== 'custom'; if (this.value !== 'custom') this.form.submit();">
            @foreach(['this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'this_year' => 'This year', 'custom' => 'Custom range'] as $value => $label)
                <option value="{{ $value }}" @selected($period['key'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="text-muted" data-commission-period>{{ $period['label'] }}</span>
        <span class="my-agent-range" data-custom-range @if($period['key'] !== 'custom') hidden @endif>
            <label class="visually-hidden" for="commission-from">From</label><input type="date" class="form-control form-control-sm" id="commission-from" name="from" value="{{ $period['from'] }}">
            <label class="visually-hidden" for="commission-to">To</label><input type="date" class="form-control form-control-sm" id="commission-to" name="to" value="{{ $period['to'] }}">
            <button class="btn btn-sm btn-light-primary" type="submit">Show</button>
        </span>
    </form>

    <div class="tenant-metrics my-agent-metrics" aria-label="Commission for the period">
        <div><span>Commission earned</span><strong data-commission-amount>{{ $money($earned) }}</strong><small>@if(($commission['override_commission'] ?? 0) > 0){{ $money($commission['default_commission']) }} own + {{ $money($commission['override_commission']) }} team override @else On rent collected in the period @endif</small></div>
        <div><span>Already paid out</span><strong>{{ $money($paidOut) }}</strong><small>@if($due['clawback'] > 0)<span class="text-danger">−{{ $money($due['clawback']) }} clawed back</span>@else From this period's commission @endif</small></div>
        <div class="is-highlight"><span>Due to you now</span><strong data-statement-due>{{ $money($due['total']) }}</strong><small>@if(($commission['pending_commission'] ?? 0) > 0){{ $money($commission['pending_commission']) }} more waiting for approval @else Paid out by an administrator @endif</small></div>
        <div><span>Rent collected</span><strong>{{ $money($commission['total_payments']) }}</strong><small>{{ number_format($commission['tenant_count']) }} paying {{ \Illuminate\Support\Str::plural('tenancy', $commission['tenant_count']) }} credited to you</small></div>
    </div>
    @if($targetProgress !== null)
        <div class="my-agent-target" aria-label="Collections against your target">
            <div><span>Collections against your target of {{ $money($agent->sales_target) }}</span><strong>{{ $targetProgress }}%</strong></div>
            <div class="progress h-6px"><div class="progress-bar" role="progressbar" style="width: {{ $targetProgress }}%" aria-valuenow="{{ $targetProgress }}" aria-valuemin="0" aria-valuemax="100"></div></div>
        </div>
    @endif

    <div class="my-agent-layout">
        <div class="my-agent-main">
            <section class="tenant-panel tenant-panel--padded" data-my-agent-tasks aria-labelledby="my-tasks-heading">
                <div class="my-agent-section-heading"><h2 id="my-tasks-heading">Up next</h2>@businessCan('task_management', 'view')<a href="{{ url('/agents/tasks') }}">All my tasks</a>@endbusinessCan</div>
                @forelse($tasks as $task)
                    @php $late = $task->sla_breach_at && $task->sla_breach_at->isPast(); @endphp
                    <div class="my-agent-item">
                        <span class="my-agent-dot {{ $late ? 'is-late' : '' }}" aria-hidden="true"></span>
                        <div><strong>{{ $task->title }}</strong><small>{{ trim(($task->lead->profile->first_name ?? '') . ' ' . ($task->lead->profile->last_name ?? '')) ?: 'Lead task' }}</small></div>
                        <em class="{{ $late ? 'text-danger' : '' }}">{{ $task->sla_breach_at ? ($late ? 'Overdue · ' : 'Due ') . $task->sla_breach_at->diffForHumans() : 'No due date' }}</em>
                    </div>
                @empty
                    <p class="my-agent-empty">Nothing waiting for you.</p>
                @endforelse
            </section>

            @if($myRepairs->isNotEmpty())
            <section class="tenant-panel tenant-panel--padded" data-my-agent-maintenance aria-labelledby="my-repairs-heading">
                <div class="my-agent-section-heading"><h2 id="my-repairs-heading">Repairs assigned to me</h2></div>
                @foreach($myRepairs as $r)
                    <a class="my-agent-item" href="{{ route('maintenance.show', $r->reference) }}">
                        <span class="my-agent-dot {{ in_array($r->priority, ['urgent', 'high'], true) ? 'is-late' : '' }}" aria-hidden="true"></span>
                        <div><strong>{{ $r->title }}</strong><small>{{ $r->complex?->title }}{{ $r->unit_label ? ' · ' . $r->unit_label : '' }} · {{ \App\Models\MaintenanceRequest::STATUSES[$r->status] }}</small></div>
                        <em class="{{ in_array($r->priority, ['urgent', 'high'], true) ? 'text-danger' : '' }}">{{ \App\Models\MaintenanceRequest::PRIORITIES[$r->priority] }} · {{ $r->reported_at?->diffForHumans() }}</em>
                    </a>
                @endforeach
            </section>
            @endif

            <section class="tenant-panel tenant-panel--padded" data-my-leads aria-labelledby="my-leads-heading">
                <div class="my-agent-section-heading"><h2 id="my-leads-heading">My leads</h2><span class="tenant-badge">{{ $statistics['active_leads'] }} active</span></div>
                @if($leads->isEmpty())
                    <p class="my-agent-empty">No leads are assigned to you yet.</p>
                @else
                <div class="tenant-table-scroll">
                    <table class="table ws-table">
                        <thead><tr><th>Lead</th><th>Property</th><th>Status</th><th>Added</th></tr></thead>
                        <tbody>
                        @foreach($leads as $lead)
                            <tr>
                                <td><strong>@businessCan('lead_management', 'view')<a href="{{ route('lead_management.show', Crypt::encrypt($lead->id)) }}">{{ $leadName($lead) }}</a>@else{{ $leadName($lead) }}@endbusinessCan</strong></td>
                                <td>@if($lead->complex)@if($canOpenProperty($lead->complex_id))<a href="{{ route('complex.show', $lead->complex->slug) }}">{{ $lead->complex->title }}</a>@else{{ $lead->complex->title }}@endif @else<span class="text-muted">—</span>@endif</td>
                                <td><span class="tenant-badge">{{ ucfirst(str_replace('_', ' ', $lead->status)) }}</span></td>
                                <td class="text-nowrap">{{ $lead->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </section>

            <section class="tenant-panel tenant-panel--padded" data-my-tenancies aria-labelledby="my-tenancies-heading">
                <div class="my-agent-section-heading"><h2 id="my-tenancies-heading">Tenancies credited to me</h2></div>
                @if($tenancies->isEmpty())
                    <p class="my-agent-empty">No tenancies are credited to you yet. Tenancies you place, or on units assigned to you, appear here.</p>
                @else
                <div class="tenant-table-scroll">
                    <table class="table ws-table">
                        <thead><tr><th>Tenant</th><th>Property</th><th>Unit</th><th class="text-end">Rent</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach($tenancies as $tenancy)
                            <tr>
                                <td><strong>{{ profile_link(tenant_profile_url($tenancy->tenantProfile), $tenancy->tenantProfile->display_name ?? '—') }}</strong></td>
                                <td>@if($tenancy->complex)@if($canOpenProperty($tenancy->complex_id))<a href="{{ route('complex.show', $tenancy->complex->slug) }}">{{ $tenancy->complex->title }}</a>@else{{ $tenancy->complex->title }}@endif @else{{ $tenancy->complex_name ?: '—' }}@endif</td>
                                <td>{{ profile_link(tenancy_profile_url($tenancy), $tenancy->specific_unit_number ?: ($tenancy->unit_number ?: $tenancy->tenancy_ref)) }}</td>
                                <td class="text-end text-nowrap">{{ money($tenancy->monthly_payment, $tenancy->currency) }}</td>
                                <td><span class="tenant-badge {{ $tenancy->status === 'Active' ? 'is-active' : '' }}">{{ $tenancy->status ?: '—' }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </section>

            <section class="my-agent-statement" aria-label="Commission statement">
                @include('pages.business.agents.partials.statement', ['statement' => $statement, 'title' => 'My commission statement', 'compact' => true])
                @include('pages.business.agents.partials.commission', ['title' => 'My commission', 'compact' => true])
            </section>
        </div>

        <aside class="my-agent-side">
            <section class="tenant-panel tenant-panel--padded" data-my-agent-stats aria-labelledby="my-work-heading">
                <div class="my-agent-section-heading"><h2 id="my-work-heading">My work</h2></div>
                <dl class="my-agent-facts">
                    <div><dt>Leads assigned</dt><dd>{{ $statistics['total_leads'] }}</dd></div>
                    <div><dt>Active leads</dt><dd>{{ $statistics['active_leads'] }}</dd></div>
                    <div><dt>Converted</dt><dd>{{ $statistics['converted_leads'] }} <small>({{ $performance['conversion_rate'] }}%)</small></dd></div>
                    <div><dt>Open tasks</dt><dd>{{ $statistics['pending_tasks'] }}</dd></div>
                    <div><dt>Overdue tasks</dt><dd class="{{ $statistics['overdue_tasks'] ? 'text-danger' : '' }}">{{ $statistics['overdue_tasks'] }}</dd></div>
                    <div><dt>Average response</dt><dd>{{ $performance['avg_response_time'] }}</dd></div>
                </dl>
            </section>

            <section class="tenant-panel tenant-panel--padded" data-my-assignments aria-labelledby="my-assignments-heading">
                <div class="my-agent-section-heading"><h2 id="my-assignments-heading">My properties &amp; units</h2></div>
                @if($leadProperties->isEmpty() && $residentialUnits->isEmpty() && $commercialUnits->isEmpty())
                    <p class="my-agent-empty">No properties or units are assigned to you yet.</p>
                @endif
                @if($leadProperties->isNotEmpty())
                    <h3 class="my-agent-subheading">Lead agent for</h3>
                    <ul class="my-agent-list">
                        @foreach($leadProperties as $property)
                            <li>
                                <span>@if($canOpenProperty($property->id))<a href="{{ route('complex.show', $property->slug) }}">{{ $property->title }}</a>@else{{ $property->title }}@endif</span>
                                @if($canOpenProperty($property->id))<a class="fs-8" href="{{ route('complex.show', $property->slug) }}#residential_units">Assign units</a>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @foreach([($residentialUnits->contains(fn ($u) => ($u->unit_category ?? null) === 'house') ? 'Homes' : 'Apartments') => $residentialUnits, 'Commercial spaces' => $commercialUnits] as $heading => $units)
                    @if($units->isNotEmpty())
                        <h3 class="my-agent-subheading">{{ $heading }}</h3>
                        <ul class="my-agent-list">
                            @foreach($units->groupBy('complex_id') as $group)
                                <li class="d-block"><span class="text-muted">{{ $group->first()->complex->title ?? 'Property' }}:</span> {{ $group->map(fn ($u) => $u->unit_number . ' (' . strtolower($u->status) . ')')->implode(', ') }}</li>
                            @endforeach
                        </ul>
                    @endif
                @endforeach
                @if($agent->subAgents->isNotEmpty())
                    <h3 class="my-agent-subheading">My team</h3>
                    <p class="mb-1 fs-7">{{ $agent->subAgents->map->full_name->implode(', ') }}</p>
                    <p class="text-muted fs-8 mb-0">You earn {{ rtrim(rtrim(number_format((float) $agent->override_rate, 2), '0'), '.') ?: 0 }}% override on their tenancies in properties you lead.</p>
                @endif
            </section>

            <section class="tenant-panel tenant-panel--padded" data-my-agent-terms aria-labelledby="my-terms-heading">
                <div class="my-agent-section-heading"><h2 id="my-terms-heading">My terms</h2></div>
                <dl class="my-agent-facts">
                    <div><dt>Commission</dt><dd>{{ $agent->commission_type ? ($typeLabels[$agent->commission_type] ?? $agent->commission_type) : 'Not set' }}</dd></div>
                    <div><dt>Rate</dt><dd>{{ $agent->commission_type === 'flat_rate' ? $money($agent->commission_rate) . ' per tenant' : rtrim(rtrim(number_format((float) $agent->commission_rate, 2), '0'), '.') . '%' }}</dd></div>
                    <div><dt>Sales target</dt><dd>{{ (float) $agent->sales_target > 0 ? $money($agent->sales_target) : 'None' }}</dd></div>
                    @if((float) $agent->base_salary > 0)<div><dt>Base salary</dt><dd>{{ $money($agent->base_salary) }}</dd></div>@endif
                </dl>
                @if($agent->commissionTiers->isNotEmpty())
                    <h3 class="my-agent-subheading">Commission tiers</h3>
                    <ul class="my-agent-list">
                        @foreach($agent->commissionTiers as $tier)
                            <li><span>{{ $tier->label ?: 'Tier' }}: {{ $money($tier->min_value) }} – {{ $tier->max_value !== null ? $money($tier->max_value) : 'and above' }}</span><strong>{{ rtrim(rtrim(number_format((float) $tier->rate, 2), '0'), '.') }}%</strong></li>
                        @endforeach
                    </ul>
                @endif
                <p class="my-agent-note">Your terms are set by an administrator. Ask them if something looks wrong.</p>
            </section>
        </aside>
    </div>
@endif
</div>
</div>
@endsection
