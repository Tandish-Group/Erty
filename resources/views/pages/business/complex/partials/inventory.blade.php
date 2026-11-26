@php
    $houses = $kind === 'houses';
    $unitKind = $kind === 'commercial' ? 'commercial' : 'residential';
    $anchor = $houses ? 'houses' : $kind . '_units';
    $statusLabels = \App\Services\Properties\ComplexInventoryService::RESIDENTIAL_STATUS_LABELS;
    $canSell = $houses && app(\App\Services\Access\ModuleService::class)->enabled('sales') && auth()->user()->hasBusinessPermission('sales_management', 'create');
    $canSeeSales = $houses && app(\App\Services\Access\ModuleService::class)->enabled('sales') && auth()->user()->hasBusinessPermission('sales_management', 'view');
@endphp
<section id="{{ $kind }}-inventory-results" data-inventory-results aria-labelledby="{{ $kind }}-heading">
    <div class="property-inventory-heading">
        <div><h2 id="{{ $kind }}-heading">{{ $houses ? 'Houses' : ucfirst($kind) . ' inventory' }}</h2><p class="text-muted mb-0">{{ $houses ? $inventory['totals']->sum() . ' ' . \Illuminate\Support\Str::plural('house', $inventory['totals']->sum()) . '. Each is rented out, sold, or offered for either.' : $inventory['totals']->sum() . ' physical units. Search inventory and open the relevant unit or tenancy.' }}</p></div>
        <div class="property-inventory-actions">
        @if($canEditInventory)
            <a class="btn btn-primary align-self-start" href="{{ route('complex.setup', [$complex->slug, 'step' => $kind]) }}">{{ ['residential' => 'Generate / refine units', 'houses' => 'Add / refine houses'][$kind] ?? 'Prepare commercial inventory' }}</a>
        @endif
        @if($kind === 'commercial')
            <a class="btn btn-light-primary align-self-start" href="{{ route('commercial-units.index', ['complex_id' => $complex->id]) }}">Full inventory ↗</a>
        @else
            <a class="btn btn-light-primary align-self-start" href="{{ route('residential-units.index', ['complex_id' => $complex->id] + ($houses ? ['category' => 'house'] : [])) }}">Full inventory ↗</a>
        @endif
        </div>
    </div>
        <div data-readiness-warning @if($inventoryReady) hidden @endif class="alert alert-warning">Property setup needs review. Recorded availability below does not mean the unit is approved for leasing. <a href="{{ route('complex.setup', $complex->slug) }}">Continue setup review</a>.</div>
    <div class="d-flex flex-wrap gap-4 mb-5" aria-label="Recorded inventory status totals">
        @foreach($inventory['totals'] as $label => $total)<span class="border rounded px-4 py-3"><strong>{{ $total }}</strong> {{ $statusLabels[$label] ?? ucfirst($label) }}</span>@endforeach
    </div>
    <form data-live-filter data-live-target="#{{ $kind }}-inventory-results" method="GET" action="{{ route('complex.show', $complex->slug) }}#{{ $anchor }}" class="row g-3 align-items-end mb-5" role="search" aria-label="Search {{ $houses ? 'houses' : $kind . ' inventory' }}">
        <div class="col-md-5"><label class="form-label" for="{{ $kind }}-q">Find {{ $houses ? 'house' : 'unit' }} number</label><input class="form-control" id="{{ $kind }}-q" name="{{ $kind }}_q" value="{{ $inventory['search'] }}" placeholder="Search all {{ $houses ? 'houses' : 'units' }} in this property"></div>
        <div class="col-md-2"><label class="form-label" for="{{ $kind }}-status">Recorded status</label><select class="form-select" id="{{ $kind }}-status" name="{{ $kind }}_status"><option value="">All statuses</option>@foreach($inventory['totals'] as $label => $total)<option value="{{ $label }}" @selected($inventory['status'] === $label)>{{ $statusLabels[$label] ?? ucfirst($label) }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label" for="{{ $kind }}-agent">Agent</label><select class="form-select" id="{{ $kind }}-agent" name="{{ $kind }}_agent">
            <option value="">Anyone</option><option value="none" @selected(request($kind.'_agent') === 'none')>Unassigned</option>
            @foreach($assignment['agents'] as $row)<option value="{{ $row['agent']->id }}" @selected(request($kind.'_agent') === (string) $row['agent']->id)>{{ $row['agent']->full_name }}</option>@endforeach
        </select></div>
        <div class="col-md-3"><button class="btn btn-light-primary">Apply filters</button> <a data-inventory-link href="{{ route('complex.show', $complex->slug) }}#{{ $anchor }}" class="btn btn-light">Reset</a></div>
    </form>
    @if($canAssignUnitAgents ?? false)
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3" data-unit-agent-bar data-kind="{{ $unitKind }}">
        <span class="text-muted fs-7" data-unit-agent-count>Select units to assign an agent.</span>
        <button type="button" class="btn btn-sm btn-light-primary" data-unit-agent-open disabled>Assign agent to selected units</button>
    </div>
    @endif
    <div class="table-responsive">
        <table class="table table-row-bordered align-middle">
            <caption>{{ $inventory['units']->total() }} matching {{ \Illuminate\Support\Str::plural($houses ? 'house' : 'unit', $inventory['units']->total()) }} · 25 per page. Recorded status and active tenancy are shown separately to make discrepancies visible.</caption>
            <thead><tr>@if($canAssignUnitAgents ?? false)<th class="w-25px"><input class="form-check-input" type="checkbox" data-unit-select-all aria-label="Select all units on this page"></th>@endif<th>{{ $houses ? 'House / plot' : 'Unit / floor' }}</th><th>{{ ['residential' => 'Layout & area', 'houses' => 'Type, layout & offer'][$kind] ?? 'Area & subdivision limits' }}</th><th>Recorded status</th><th>{{ $houses ? 'Tenancy or sale' : 'Active tenancy' }}</th><th>Agent</th><th>Next action</th></tr></thead>
            <tbody>
                @forelse($inventory['units'] as $unit)
                    <tr id="inventory-unit-{{ $unit->id }}">
                        @if($canAssignUnitAgents ?? false)<td><input class="form-check-input" type="checkbox" data-unit-select value="{{ $unit->id }}" aria-label="Select {{ $houses ? 'house' : 'unit' }} {{ $unit->unit_number }}"></td>@endif
                        <td><strong>{{ $unit->unit_number ?: 'Number required' }}</strong><div class="text-muted">@if($houses){{ $unit->plot_number ? 'Plot ' . $unit->plot_number : 'Plot not recorded' }}@else Floor: {{ $kind === 'residential' ? ($unit->floor ?? 'Not recorded') : ($unit->effective_floor ?: 'Not recorded') }}@endif</div></td>
                        <td>
                            @if($houses)
                                <div>{{ $unit->layoutLabel() }} · {{ $unit->bathrooms }} {{ \Illuminate\Support\Str::plural('bath', $unit->bathrooms) }}</div>
                                <span class="text-muted">{{ number_format($unit->sq_ft ?? 0) }} sq ft{{ $unit->plotLabel() ? ' on ' . $unit->plotLabel() : '' }}</span>
                                <div class="mt-1"><span class="badge badge-light-{{ ['sale' => 'info', 'both' => 'primary'][$unit->offer] ?? 'secondary' }}">{{ config('property.offers')[$unit->offer ?? 'rent'] }}</span>@if($unit->forSale() && $unit->sale_price)<span class="text-muted fs-8 ms-2">{{ default_currency() }} {{ number_format($unit->sale_price) }}</span>@endif</div>
                            @elseif($kind === 'residential')
                                <div>{{ $unit->bedrooms }} bedrooms · {{ $unit->bathrooms }} bathrooms</div><span class="text-muted">{{ number_format($unit->sq_ft ?? 0, 2) }} sq ft</span>
                            @else
                                <div>{{ number_format($unit->unit_sq_ft, 2) }} sq ft total</div><div class="text-muted">Minimum {{ number_format($unit->min_sq_ft, 2) }} sq ft · {{ $unit->max_subdivisions }} subdivisions</div>
                                <div class="text-muted">{{ number_format(max(0, $unit->unit_sq_ft - $unit->activeTenants->sum(fn ($tenant) => $tenant->occupied_sq_area ?? $tenant->sq_area ?? 0)), 2) }} sq ft not allocated to active tenancies</div>
                            @endif
                        </td>
                        <td><span class="badge badge-light{{ ['sold' => '-success', 'under_offer' => '-warning'][$unit->status] ?? '' }}">{{ $statusLabels[$unit->status] ?? ucfirst($unit->status) }}</span></td>
                        <td>
                            @if($kind === 'commercial')
                                @forelse($unit->activeTenants as $tenancy)
                                    @include('pages.business.complex.partials.inventory-tenant', ['tenancy' => $tenancy])
                                @empty<span class="text-muted">No active tenancy</span>@endforelse
                            @elseif($unit->tenantManagement && $unit->tenantManagement->status === 'Active')
                                @include('pages.business.complex.partials.inventory-tenant', ['tenancy' => $unit->tenantManagement])
                            @elseif($houses && $unit->liveSale)
                                <div>@if($canSeeSales)<a href="{{ route('sales.show', $unit->liveSale->reference) }}">{{ $unit->liveSale->buyer_name }}</a>@else{{ $unit->liveSale->buyer_name }}@endif</div><span class="text-muted fs-8">Sale {{ $unit->liveSale->reference }} · {{ ucfirst($unit->liveSale->status) }}</span>
                            @elseif($houses && $unit->status === 'sold')<span class="text-muted">Sold</span>
                            @else<span class="text-muted">No active tenancy</span>@endif
                        </td>
                        <td class="fs-7" data-unit-agent>
                            @if($unit->assignedAgent)
                                {{ $unit->assignedAgent->full_name }}
                                @if(!$complex->isCompanyManaged() && (int) $unit->assigned_agent_id === (int) $complex->assigned_agent_id)<span class="badge badge-light-primary ms-1">Primary</span>@else<span class="badge badge-light ms-1">Sub-agent</span>@endif
                            @elseif(!$complex->isCompanyManaged() && $complex->assignedAgent)<span class="text-muted">{{ $complex->assignedAgent->full_name }} (primary, not delegated)</span>
                            @else<span class="badge badge-light-warning">Unassigned</span>@endif
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 fs-8 align-baseline" data-history-open data-history-url="{{ route('complex.assignment_history', [$complex->slug, $unitKind, $unit->id]) }}" data-history-title="{{ $houses ? 'House' : 'Unit' }} {{ $unit->unit_number ?: '#'.$unit->id }}: assignment history">History</button>
                        </td>
                        <td>
                            @if($kind === 'commercial' && $canEditInventory)
                                <a href="{{ route('commercial-units.edit', encrypt($unit->id)) }}">Edit space</a>
                            @else
                                @unless($unitOnlyView ?? false)<a href="{{ route('complex.setup', [$complex->slug, 'step' => $kind, 'unit' => $unit->id]) }}#inventory-unit-{{ $unit->id }}">{{ $canEditInventory ? ($houses ? 'Refine this house' : 'Refine this unit') : 'View setup review' }}</a>@endunless
                                @if($canSell && $unit->forSale() && $unit->status === 'available' && !($unit->tenantManagement && $unit->tenantManagement->status === 'Active'))<a class="d-block mt-1" href="{{ route('sales.create', ['unit' => $unit->id]) }}">Start a sale</a>@endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ ($canAssignUnitAgents ?? false) ? 7 : 6 }}" class="py-8">{{ $inventory['totals']->sum() ? 'No units match these filters. Reset filters to see the full inventory.' : 'No units have been recorded. Start with inventory preparation above; creating a specification alone is not a leasable unit.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $inventory['units']->links('pagination::bootstrap-5') }}
</section>
