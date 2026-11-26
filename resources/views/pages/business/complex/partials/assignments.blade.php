{{-- Who manages this property: company or primary manager, the agents on its units, unassigned units and recent changes. --}}
@php
    $a = $assignment;
    $roleLabel = ['primary' => 'Primary manager', 'sub_agent' => 'Sub-agent', 'company' => 'Company'];
    $kinds = array_filter(['residential' => $hasResidential, 'commercial' => $hasCommercial]);
@endphp
<div class="property-section-heading">
    <div><h2>Assignments</h2><p>Who is responsible for this property and its units. Commission follows who held a unit when the rent was collected.</p></div>
    @if($a['can_set_management'])<button type="button" class="btn btn-light-primary btn-sm" data-bs-toggle="modal" data-bs-target="#property-management-modal">Change management</button>@endif
</div>

<div class="row g-6 mb-6" data-assignment-summary>
    <div class="col-lg-5">
        <section class="tenant-panel tenant-panel--padded h-100">
            <h3 class="fs-6 fw-bold text-muted text-uppercase mb-3" style="letter-spacing:.05em">Responsible</h3>
            @if($a['company'])
                <div class="fs-4 fw-bolder">{{ $a['business_name'] }}</div>
                <p class="text-muted fs-7 mt-1 mb-0">Managed by the company. Everyone on the team works on it according to their role; units can still be given to individual agents.</p>
            @else
                <div class="fs-4 fw-bolder">{{ profile_link(agent_profile_url($a['primary']), $a['primary']->full_name) }}</div>
                <p class="text-muted fs-7 mt-1 mb-0">Primary property manager. Oversees every unit and can delegate units to agents.</p>
            @endif
            <div class="separator separator-dashed my-4"></div>
            @if($unitOnlyView ?? false)
            @php $myUnitCounts = collect(app(\App\Services\Access\PropertyAccess::class)->unitScope()[$complex->id] ?? [])->map(fn ($ids) => count($ids)); @endphp
            <dl class="row mb-0 fs-7" data-my-units>
                @foreach($kinds as $kind => $_)
                    <dt class="col-8 text-muted fw-semibold mb-2">Your {{ $kind }} units (sub-agent)</dt>
                    <dd class="col-4 text-end fw-bold mb-2"><a href="#{{ $kind }}_units" data-property-tab>{{ $myUnitCounts[$kind] ?? 0 }}</a></dd>
                @endforeach
            </dl>
            <p class="text-muted fs-8 mt-2 mb-0">You see the units you are responsible for here; the primary manager handles the rest of the property.</p>
            @else
            <dl class="row mb-0 fs-7">
                @foreach($kinds as $kind => $_)
                    <dt class="col-8 text-muted fw-semibold mb-2">Unassigned {{ $kind }} units</dt>
                    <dd class="col-4 text-end fw-bold mb-2">
                        @if($a['unassigned'][$kind])<a href="{{ route('complex.show', [$complex->slug, $kind.'_agent' => 'none']) }}#{{ $kind }}_units">{{ $a['unassigned'][$kind] }}</a>@else 0 @endif
                    </dd>
                @endforeach
            </dl>
            @endif
        </section>
    </div>
    <div class="col-lg-7">
        <section class="tenant-panel tenant-panel--padded h-100">
            <h3 class="fs-6 fw-bold text-muted text-uppercase mb-3" style="letter-spacing:.05em">Agents on this property</h3>
            @if($a['agents']->isEmpty())
                <p class="text-muted mb-0">No agents are assigned to this property or its units yet.@if($canAssignUnitAgents) Select units in the {{ implode(' or ', array_map('ucfirst', array_keys($kinds))) }} tab to assign them.@endif</p>
            @else
            <div class="tenant-table-scroll" role="region" aria-label="Agents on this property" tabindex="0">
                <table class="tenant-table" style="min-width:0">
                    <thead><tr><th>Agent</th><th>Role</th>@foreach($kinds as $kind => $_)<th class="text-end">{{ ucfirst($kind) }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($a['agents'] as $row)
                        <tr>
                            <td><strong>{{ profile_link(agent_profile_url($row['agent']), $row['agent']->full_name) }}</strong>@unless($row['agent']->is_active)<small>Inactive</small>@endunless</td>
                            <td><span class="badge {{ $row['role'] === 'primary' ? 'badge-light-primary' : 'badge-light' }}">{{ $roleLabel[$row['role']] }}</span></td>
                            @foreach($kinds as $kind => $_)
                                <td class="text-end">@if($row[$kind])<a href="{{ route('complex.show', [$complex->slug, $kind.'_agent' => $row['agent']->id]) }}#{{ $kind }}_units">{{ $row[$kind] }}</a>@else <span class="text-muted">—</span>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>
    </div>
</div>

<section class="tenant-panel tenant-panel--padded" data-assignment-history>
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h3 class="fs-5 fw-bolder mb-0">Recent changes</h3>
        <button type="button" class="btn btn-sm btn-light" data-history-open data-history-url="{{ route('complex.assignment_history', [$complex->slug, 'property']) }}" data-history-title="Who managed {{ $complex->title }}">Property history</button>
    </div>
    @if($a['history']->isEmpty())
        <p class="text-muted mb-0">No assignment changes recorded yet.</p>
    @else
    <ul class="list-unstyled mb-0 fs-7">
        @foreach($a['history'] as $h)
            @php
                $what = $h->scope === 'property' ? 'Property' : (ucfirst(str_replace('_unit', '', $h->scope)) . ' unit ' . ($a['unit_numbers'][$h->scope][$h->unit_id] ?? '#'.$h->unit_id));
                $who = $h->assignee_type === 'company' ? 'the company' : ($h->agent ? trim($h->agent->first_name.' '.$h->agent->last_name) : 'a removed agent');
            @endphp
            <li class="d-flex flex-wrap justify-content-between gap-2 py-2 @unless($loop->last) border-bottom @endunless">
                <span><strong>{{ $what }}</strong> → {{ $who }} <span class="text-muted">({{ $roleLabel[$h->role] ?? $h->role }})</span>@if($h->ends_at) <span class="badge badge-light ms-1">ended {{ $h->ends_at->format('j M Y') }}</span>@endif</span>
                <span class="text-muted">{{ $h->starts_at->format('j M Y, H:i') }}@if($h->assignedBy) · {{ trim($h->assignedBy->first_name.' '.$h->assignedBy->last_name) }}@endif @if($h->reason) · {{ $h->reason }}@endif</span>
            </li>
        @endforeach
    </ul>
    @endif
</section>
