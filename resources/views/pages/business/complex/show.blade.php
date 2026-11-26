@extends('pages.business.layouts.app')

@section('content')

<div id="kt_header" class="header property-page-header">
    <div class="container-xxl d-flex align-items-center justify-content-between gap-4" id="kt_header_container">
        <button type="button" class="btn btn-icon btn-light d-lg-none" id="kt_aside_toggle" aria-label="Open navigation">☰</button>
        <nav aria-label="Breadcrumb" class="property-breadcrumb"><a href="{{ route('complex.index') }}">← Property portfolio</a><span aria-hidden="true">/</span><span>Property details</span></nav>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<!--begin::Content-->
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
	<!--begin::Container-->
	<div class="container-xxl property-workspace" id="kt_content_container">
		@php
			$propertyType = strtolower((string) $complex->type);
			$setupReady = $inventoryReady;
			// Someone who reaches this property only through unit assignments: no property-wide setup links.
			$unitOnlyView = isset(app(\App\Services\Access\PropertyAccess::class)->unitScope()[$complex->id]);
			$hasApartments = \App\Services\Properties\ComplexInventoryService::hasApartments($complex);
			$hasHouses = \App\Services\Properties\ComplexInventoryService::hasHouses($complex);
			$hasResidential = $hasApartments || $hasHouses;
			$hasCommercial = \App\Services\Properties\ComplexInventoryService::hasCommercial($complex);
			$houseCount = $complex->complex_residential_units?->where('unit_category', 'house')->count() ?? 0;
			$apartmentCount = ($complex->complex_residential_units?->count() ?? 0) - $houseCount;
			$residentialCount = $apartmentCount + $houseCount;
			$commercialCount = $complex->commercialUnits?->count() ?? 0;
			$compositionLabel = \App\Services\Properties\ComplexInventoryService::compositionLabel($complex, $houseCount);
		@endphp

        @include('pages.business.complex.partials.property-summary')

        <nav class="property-resource-links" aria-label="Property records">
            <span>Property records</span>
            <a href="/plans_complex/{{ $complex->slug }}">Floor plans <span aria-hidden="true">↗</span></a>
            @businessCan('expense_management', 'view')<a href="{{ route('business.expenses.index', ['complex_id' => encrypt($complex->id)]) }}">Expenses <span aria-hidden="true">↗</span></a>@endbusinessCan
            @businessCan('listing_history', 'view')<a href="{{ route('history.index', ['complex_filter' => $complex->slug]) }}">History <span aria-hidden="true">↗</span></a>@endbusinessCan
        </nav>
        @php
            $propertyTabs = ['overview' => ['Overview', null]];
            $propertyTabs['assignments'] = ['Assignments', null];
            if ($hasApartments || $apartmentCount) $propertyTabs['residential_units'] = [$hasHouses ? 'Apartments' : 'Residential', $apartmentCount];
            if ($hasHouses) $propertyTabs['houses'] = ['Houses', $houseCount];
            if ($hasCommercial) $propertyTabs['commercial_units'] = ['Commercial', $commercialCount];
            $propertyTabs['amenities'] = ['Facilities', $complexAmenities->count()];
            $listingsModule = app(\App\Services\Access\ModuleService::class)->enabled(\App\Services\Access\ModuleService::LISTINGS);
            if ($listingsModule) $propertyTabs['listings'] = ['Listings', $listings->count()];
            $propertyRepairs = \App\Models\MaintenanceRequest::with(['assignee:id,first_name,last_name', 'residentialUnit:id,unit_number', 'commercialUnit:id,unit_number'])->where('managed_by', business_owner_id())->where('complex_id', $complex->id)
                ->orderByRaw("case when status in ('open','assigned','in_progress') then 0 when status = 'completed' then 1 else 2 end")->latest('reported_at')->limit(15)->get();
            $propertyTabs['maintenance'] = ['Maintenance', $propertyRepairs->filter->isOpen()->count()];
            $propertyTabs['settings'] = [$canEditInventory ? 'Edit property' : 'Property settings', null];
        @endphp
        <div class="property-tabs-shell">
            <nav aria-label="Property sections" class="property-tab-navigation">
                <ul class="nav nav-tabs d-none d-md-flex" role="tablist">
                    @foreach($propertyTabs as $tabId => [$tabTitle, $tabCount])
                        <li class="nav-item" role="presentation"><a class="nav-link {{ $loop->first ? 'active' : '' }}" id="property-tab-{{ $tabId }}" data-bs-toggle="tab" href="#{{ $tabId }}" role="tab" aria-controls="{{ $tabId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $tabTitle }}@if($tabCount !== null)<span class="property-tab-count">{{ $tabCount }}</span>@endif</a></li>
                    @endforeach
                </ul>
                <div class="property-mobile-tabs d-md-none"><label for="property-section">Property section</label><select id="property-section" data-property-tab-select class="form-select">@foreach($propertyTabs as $tabId => [$tabTitle, $tabCount])<option value="#{{ $tabId }}">{{ $tabTitle }}{{ $tabCount !== null ? ' ('.$tabCount.')' : '' }}</option>@endforeach</select></div>
            </nav>
            <div class="property-tab-body">
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="property-tab-overview">
                        @include('pages.business.complex.partials.property-overview')
                    </div>

                    <div class="tab-pane fade tenant-workspace" id="assignments" role="tabpanel" aria-labelledby="property-tab-assignments">
                        @include('pages.business.complex.partials.assignments')
                    </div>

					@if($listingsModule)
					<div class="tab-pane fade" id="listings" role="tabpanel" aria-labelledby="property-tab-listings">
                        <div class="property-section-heading"><div><h2>Property listings</h2><p>One listing per unit type{{ $listings->whereNotNull('commercial_unit_id')->isNotEmpty() ? ' and per commercial space' : '' }}. Rent, deposit, description and photos are added in Listings.</p></div>
                            @if(auth()->user()->hasBusinessPermission('listings_management', 'view'))<a href="{{ route('listings.index', ['complex_id' => $complex->id]) }}" class="btn btn-light-primary btn-sm">Open in Listings</a>@endif
                        </div>
                        @if($listings->isEmpty())
                            <div class="tenant-empty"><i class="fas fa-home" aria-hidden="true"></i><h3>No listings yet</h3><p>Listings are created as drafts when this property's setup is confirmed.</p></div>
                        @else
                            <div class="tenant-workspace">
                            @include('pages.business.listings.partials.table', [
                                'rows' => $listings,
                                'availability' => $listingAvailability,
                                'canEdit' => auth()->user()->hasBusinessPermission('listings_management', 'edit'),
                                'canPublish' => auth()->user()->hasBusinessPermission('listings_management', 'publish'),
                            ])
                            </div>
                        @endif
					</div>
					@endif

                    <div class="tab-pane fade" id="amenities" role="tabpanel" aria-labelledby="property-tab-amenities">
                        @include('pages.business.complex.partials.facilities')
                    </div>

					@if($hasCommercial)
                        <div class="tab-pane fade" id="commercial_units" role="tabpanel" aria-labelledby="property-tab-commercial_units">
                            @include('pages.business.complex.partials.inventory', ['kind' => 'commercial', 'inventory' => $inventories['commercial']])
                        </div>
                    @endif
                    @if(isset($propertyTabs['residential_units']))
                        <div class="tab-pane fade" id="residential_units" role="tabpanel" aria-labelledby="property-tab-residential_units">
                            @include('pages.business.complex.partials.inventory', ['kind' => 'residential', 'inventory' => $inventories['residential']])
                        </div>
                    @endif
                    @if($hasHouses)
                        <div class="tab-pane fade" id="houses" role="tabpanel" aria-labelledby="property-tab-houses">
                            @include('pages.business.complex.partials.inventory', ['kind' => 'houses', 'inventory' => $inventories['houses']])
                        </div>
                    @endif

                    <div class="tab-pane fade" id="maintenance" role="tabpanel" aria-labelledby="property-tab-maintenance" data-property-maintenance>
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-5">
                            <div><h2 class="mb-1">Maintenance</h2><p class="text-muted fs-7 mb-0">Open repairs first. Costs are booked as property expenses when a request is closed.</p></div>
                            <div class="d-flex gap-2">
                                <a class="btn btn-sm btn-light" href="{{ route('maintenance.index', ['show' => 'all', 'complex_id' => $complex->id]) }}">All requests</a>
                                @if(auth()->user()->hasBusinessPermission('complex_management', 'edit') || auth()->user()->hasBusinessPermission('tenant_management', 'create'))
                                    <a class="btn btn-sm btn-primary" href="{{ route('maintenance.create', ['complex_id' => $complex->id]) }}"><i class="fas fa-plus"></i> Log a request</a>
                                @endif
                            </div>
                        </div>
                        @if($propertyRepairs->isEmpty())
                            <p class="text-muted">No repairs logged for this property.</p>
                        @else
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle fs-7">
                                <thead><tr class="text-muted fw-semibold text-uppercase fs-8"><th>Request</th><th>Unit</th><th>Priority</th><th>Status</th><th>Assigned to</th><th>Reported</th><th></th></tr></thead>
                                <tbody>
                                @foreach($propertyRepairs as $r)
                                    <tr>
                                        <td class="fw-bold">{{ $r->title }}<div class="text-muted fs-8 fw-normal">{{ $r->reference }}</div></td>
                                        <td>{{ $r->unit_label ?? 'Common area' }}</td>
                                        <td>{{ \App\Models\MaintenanceRequest::PRIORITIES[$r->priority] ?? $r->priority }}</td>
                                        <td><span class="badge badge-light{{ $r->isOpen() ? '-warning' : '' }}">{{ \App\Models\MaintenanceRequest::STATUSES[$r->status] ?? $r->status }}</span></td>
                                        <td>{{ $r->assignee ? trim($r->assignee->first_name.' '.$r->assignee->last_name) : '—' }}</td>
                                        <td>{{ $r->reported_at?->format('j M Y') }}</td>
                                        <td class="text-end"><a class="btn btn-sm btn-light-primary" href="{{ route('maintenance.show', $r->reference) }}">Open</a></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>

		            <div class="tab-pane fade" id="settings" role="tabpanel" aria-labelledby="property-tab-settings">
                        <div data-property-editor>
                            @if($canEditInventory && request('editor') !== 'advanced')
                                <h2 class="mb-3">Edit property details</h2>
                                <p class="text-muted mb-5">Update the property’s contact details and map location. Saved changes appear in the overview immediately.</p>
                                @include('pages.business.complex.setup-details', ['step' => $setupSteps['identity'], 'inWorkspace' => true])
                            @elseif(!$canEditInventory)
                                <p class="text-muted">You have view-only access to this property.</p>
                            @endif
                        </div>
                        @if($canEditInventory)
                            @if(request('editor') === 'advanced')
                                <a class="btn btn-light mb-5" href="{{ route('complex.show', $complex->slug) }}#settings">← Back to property details</a>
                                @include('pages.business.complex.include.edit_complex')
                            @else
                                <a class="property-advanced-entry" href="{{ route('complex.show', [$complex->slug, 'editor' => 'advanced']) }}#settings"><span><strong>Classification, links & agent</strong><small>Manage the property classification, supporting links and the responsible agent.</small></span><span aria-hidden="true">→</span></a>
                            @endif
                        @endif
		            </div>
		        </div>
		    </div>
		</div>
	</div>
	<!--end::Container-->
</div>
<!--end::Content-->

@if($canEditInventory)
<div class="modal fade facility-modal" id="property-facility-modal" tabindex="-1" aria-labelledby="facility-modal-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><span class="facility-eyebrow">{{ $complex->title }}</span><h2 id="facility-modal-title">Add facility</h2></div><button type="button" class="btn btn-icon btn-sm btn-light" data-bs-dismiss="modal" aria-label="Close facility editor">×</button></div>
        <div class="modal-body">@include('pages.business.complex.partials.facility-form', ['inModal' => true])</div>
    </div></div>
</div>
@endif

@if($canAssignUnitAgents)
<div class="modal fade" id="unit-agent-modal" tabindex="-1" aria-labelledby="unit-agent-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form data-unit-agent-form data-url="{{ route('complex.unit_agents.update', $complex->slug) }}">
            <div class="modal-header"><div><span class="facility-eyebrow">{{ $complex->title }}</span><h2 class="fs-4 mb-0" id="unit-agent-title">Assign an agent to <span data-unit-agent-summary>units</span></h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="text-muted fs-7">From now on these units, their tenants and the commission on their rent go to this agent{{ !$complex->isCompanyManaged() && $complex->assignedAgent ? '; '.$complex->assignedAgent->full_name.' keeps oversight as primary manager' : '' }}. Rent already collected stays with whoever held the unit then. @unless(app(\App\Services\Agents\AgentAssignmentService::class)->isAdmin(auth()->user()) || $complex->isCompanyManaged())You can choose yourself or agents who report to you.@endunless</p>
                <label class="form-label" for="unit-agent-select">Agent</label>
                @include('pages.business.agents.partials.picker', ['name' => 'agent_id', 'id' => 'unit-agent-select', 'selected' => null, 'query' => ['signin' => 1, 'units_of' => $complex->slug], 'placeholder' => 'Search agents'])
                <div class="form-text">Clear the selection to unassign the units{{ !$complex->isCompanyManaged() && $complex->assignedAgent ? ' (the primary manager then answers for them)' : '' }}.</div>
                <div class="alert alert-danger d-none mt-3" role="alert" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-submit>Save</button></div>
        </form>
    </div></div>
</div>
<script>
    // Unit agents: select units in an inventory table, then assign or clear their agent.
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('unit-agent-modal');
        const form = modalEl.querySelector('[data-unit-agent-form]');
        let current = { kind: null, ids: [] };
        const refreshBar = (section) => {
            const bar = section.querySelector('[data-unit-agent-bar]');
            if (!bar) return;
            const ids = [...section.querySelectorAll('[data-unit-select]:checked')].map(b => Number(b.value));
            bar.querySelector('[data-unit-agent-count]').textContent = ids.length ? `${ids.length} selected` : 'Select units to assign an agent.';
            bar.querySelector('[data-unit-agent-open]').disabled = !ids.length;
        };
        document.addEventListener('change', event => {
            const section = event.target.closest('[data-inventory-results]');
            if (!section) return;
            if (event.target.matches('[data-unit-select-all]')) section.querySelectorAll('[data-unit-select]').forEach(b => { b.checked = event.target.checked; });
            if (event.target.matches('[data-unit-select], [data-unit-select-all]')) refreshBar(section);
        });
        document.addEventListener('click', event => {
            const opener = event.target.closest('[data-unit-agent-open]');
            if (!opener) return;
            const section = opener.closest('[data-inventory-results]');
            current = { kind: opener.closest('[data-unit-agent-bar]').dataset.kind, ids: [...section.querySelectorAll('[data-unit-select]:checked')].map(b => Number(b.value)) };
            modalEl.querySelector('[data-unit-agent-summary]').textContent = `${current.ids.length} ${current.kind} ${current.ids.length === 1 ? 'unit' : 'units'}`;
            form.querySelector('[data-form-error]').classList.add('d-none');
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submit = form.querySelector('[data-submit]');
            const errorBox = form.querySelector('[data-form-error]');
            submit.disabled = true;
            try {
                const agentId = form.querySelector('[name="agent_id"]').value;
                const response = await fetch(form.dataset.url, {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ kind: current.kind, unit_ids: current.ids, agent_id: agentId ? Number(agentId) : null }),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The agent could not be assigned.');
                try { sessionStorage.setItem('erty_flash', result.message); } catch (e) {}
                location.reload();
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('d-none');
                submit.disabled = false;
            }
        });
    });
</script>
@endif

@if($assignment['can_set_management'])
<div class="modal fade" id="property-management-modal" tabindex="-1" aria-labelledby="property-management-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form data-management-form data-url="{{ route('complex.management.update', $complex->slug) }}">
            <div class="modal-header"><div><span class="facility-eyebrow">{{ $complex->title }}</span><h2 class="fs-4 mb-0" id="property-management-title">Who manages this property</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="d-flex flex-column gap-3 mb-5" role="radiogroup" aria-label="Management">
                    <label class="form-check form-check-custom align-items-start gap-2">
                        <input class="form-check-input mt-1" type="radio" name="management_type" value="company" @checked($assignment['company'])>
                        <span class="form-check-label"><span class="fw-semibold d-block">The company ({{ $assignment['business_name'] }})</span><span class="text-muted fs-8">The team manages it together, each person according to their role. Nobody has to be reassigned when staff change.</span></span>
                    </label>
                    <label class="form-check form-check-custom align-items-start gap-2">
                        <input class="form-check-input mt-1" type="radio" name="management_type" value="individual" @checked(!$assignment['company'])>
                        <span class="form-check-label"><span class="fw-semibold d-block">One primary property manager</span><span class="text-muted fs-8">Oversees the whole property and delegates units to other agents.</span></span>
                    </label>
                </div>
                <div data-primary-picker @if($assignment['company']) hidden @endif>
                    <label class="form-label" for="management-agent">Primary property manager</label>
                    @include('pages.business.agents.partials.picker', ['name' => 'agent_id', 'id' => 'management-agent', 'selected' => $assignment['primary'], 'query' => ['signin' => 1], 'placeholder' => 'Search agents who sign in'])
                </div>
                <label class="form-label mt-4" for="management-reason">Reason (optional)</label>
                <input class="form-control" id="management-reason" name="reason" maxlength="255" placeholder="For example: Grace now leads this property">
                <p class="text-muted fs-8 mt-3 mb-0">Unit assignments stay as they are. Commission already earned is not moved; new rent is attributed under the new arrangement.</p>
                <div class="alert alert-danger d-none mt-3" role="alert" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-submit>Save</button></div>
        </form>
    </div></div>
</div>
@endif

<div class="modal fade" id="assignment-history-modal" tabindex="-1" aria-labelledby="assignment-history-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content">
        <div class="modal-header"><h2 class="fs-4 mb-0" id="assignment-history-title">Assignment history</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body" data-history-body><p class="text-muted mb-0">Loading…</p></div>
    </div></div>
</div>
<script>
    // Management type, and assignment history (property or unit).
    document.addEventListener('DOMContentLoaded', function () {
        const token = document.querySelector('meta[name="csrf-token"]').content;
        const form = document.querySelector('[data-management-form]');
        if (form) {
            const picker = form.querySelector('[data-primary-picker]');
            form.addEventListener('change', e => { if (e.target.name === 'management_type') picker.hidden = e.target.value !== 'individual'; });
            form.addEventListener('submit', async e => {
                e.preventDefault();
                const submit = form.querySelector('[data-submit]'), errorBox = form.querySelector('[data-form-error]');
                submit.disabled = true; errorBox.classList.add('d-none');
                const type = form.querySelector('[name="management_type"]:checked')?.value;
                const agent = form.querySelector('[name="agent_id"]')?.value;
                try {
                    const response = await fetch(form.dataset.url, { method: 'PUT', credentials: 'same-origin',
                        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ management_type: type, agent_id: type === 'individual' && agent ? Number(agent) : null, reason: form.querySelector('[name="reason"]').value || null }) });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The change could not be saved.');
                    try { sessionStorage.setItem('erty_flash', result.message); } catch (err) {}
                    location.hash = '#assignments'; location.reload();
                } catch (error) { errorBox.textContent = error.message; errorBox.classList.remove('d-none'); submit.disabled = false; }
            });
        }
        const modalEl = document.getElementById('assignment-history-modal');
        const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        document.addEventListener('click', async e => {
            const opener = e.target.closest('[data-history-open]');
            if (!opener) return;
            modalEl.querySelector('#assignment-history-title').textContent = opener.dataset.historyTitle || 'Assignment history';
            const body = modalEl.querySelector('[data-history-body]');
            body.innerHTML = '<p class="text-muted mb-0">Loading…</p>';
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
            try {
                const response = await fetch(opener.dataset.historyUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'History could not be loaded.');
                body.innerHTML = result.history.length ? '<div class="table-responsive"><table class="table table-row-dashed fs-7 mb-0"><thead><tr class="text-muted fw-semibold"><th>Responsible</th><th>Role</th><th>From</th><th>Until</th><th>Changed by</th></tr></thead><tbody>'
                    + result.history.map(h => `<tr><td class="fw-semibold">${esc(h.assignee)}</td><td>${esc(h.role)}</td><td>${esc(h.from)}</td><td>${h.to ? esc(h.to) : '<span class="badge badge-light-success">Current</span>'}</td><td>${esc(h.by || '—')}${h.reason ? '<div class="text-muted fs-8">' + esc(h.reason) + '</div>' : ''}</td></tr>`).join('')
                    + '</tbody></table></div>' : '<p class="text-muted mb-0">Never assigned to anyone.</p>';
            } catch (error) { body.innerHTML = '<div class="alert alert-danger mb-0">' + esc(error.message) + '</div>'; }
        });
    });
</script>

@if($canAssignManagers)
<div class="modal fade" id="property-managers-modal" tabindex="-1" aria-labelledby="property-managers-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form data-managers-form data-url="{{ route('complex.managers.update', $complex->slug) }}">
            <div class="modal-header"><div><span class="facility-eyebrow">{{ $complex->title }}</span><h2 class="fs-4 mb-0" id="property-managers-title">Team members managing this property</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="text-muted fs-7">Members whose role is limited to assigned properties only see the properties ticked for them. Everyone else keeps seeing the whole portfolio; this records who is responsible.</p>
                @forelse($assignableMembers as $member)
                    <label class="form-check form-check-custom d-flex align-items-start gap-2 mb-3">
                        <input class="form-check-input mt-1" type="checkbox" value="{{ $member->id }}" data-manager-option @checked($managers->contains('id', $member->id))>
                        <span class="form-check-label"><span class="fw-semibold d-block">{{ trim($member->first_name.' '.$member->last_name) }}</span>
                            <span class="text-muted fs-8">{{ $member->businessRole->name ?? 'No role' }} · {{ $member->businessRole?->limitedToAssignedProperties() ? 'assigned properties only' : 'all properties' }}</span></span>
                    </label>
                @empty
                    <p class="mb-0">There are no active team members yet. Invite them from <a href="{{ route('business.team.index') }}">Team &amp; roles</a>.</p>
                @endforelse
                <div class="alert alert-danger d-none mt-3" role="alert" data-form-error></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary" data-submit @disabled($assignableMembers->isEmpty())>Save</button></div>
        </form>
    </div></div>
</div>
<script>
    // Save the property's managers and refresh the "Managed by" list without reloading the workspace.
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-managers-form]');
        if (!form) return;
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const submit = form.querySelector('[data-submit]');
            const errorBox = form.querySelector('[data-form-error]');
            submit.disabled = true;
            errorBox.classList.add('d-none');
            try {
                const response = await fetch(form.dataset.url, {
                    method: 'PUT', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ user_ids: [...form.querySelectorAll('[data-manager-option]:checked')].map(box => Number(box.value)) }),
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'The managers could not be saved.');
                const list = document.querySelector('[data-managers-list]');
                list.replaceChildren();
                if (!result.managers.length) {
                    const empty = document.createElement('span'); empty.className = 'property-muted'; empty.textContent = 'No team members assigned'; list.appendChild(empty);
                }
                result.managers.forEach(manager => {
                    const badge = document.createElement('span'); badge.className = 'badge badge-light-primary me-1 mb-1'; badge.textContent = manager.name; list.appendChild(badge);
                });
                bootstrap.Modal.getInstance(document.getElementById('property-managers-modal'))?.hide();
                if (window.Swal) Swal.fire({ toast: true, position: 'top-end', icon: 'success', text: result.message, showConfirmButton: false, timer: 3500 });
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('d-none');
            } finally {
                submit.disabled = false;
            }
        });
    });
</script>
@endif

<script>
    // Pass complex slug to JavaScript
    window.complexSlug = '{{ $complex->slug }}';
</script>

@endsection
