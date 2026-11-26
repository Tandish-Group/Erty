{{-- Report filters (GET). $filters, $properties, optional $agents, $usesPeriod, $action. --}}
@php use App\Services\Reports\ReportFilters; $agents = $agents ?? collect(); @endphp
<form method="get" action="{{ $action }}" class="tenant-panel tenant-panel--padded report-filters mb-6" data-report-filters>
    @if($usesPeriod)
        <div>
            <label for="rf_period" class="form-label">Period</label>
            <select id="rf_period" name="period" class="form-select form-select-sm" data-report-period>
                @foreach(ReportFilters::PERIODS as $key => $label)<option value="{{ $key }}" @selected($filters->period === $key)>{{ $label }}</option>@endforeach
            </select>
        </div>
        <div class="report-custom-dates" data-report-custom @if($filters->period !== 'custom') hidden @endif>
            <div><label for="rf_from" class="form-label">From</label><input id="rf_from" type="date" name="from" value="{{ $filters->from }}" class="form-control form-control-sm"></div>
            <div><label for="rf_to" class="form-label">To</label><input id="rf_to" type="date" name="to" value="{{ $filters->to }}" class="form-control form-control-sm"></div>
        </div>
    @endif
    <div>
        <label for="rf_property" class="form-label">Property</label>
        <select id="rf_property" name="property" class="form-select form-select-sm">
            <option value="">All properties</option>
            @foreach($properties as $id => $title)<option value="{{ $id }}" @selected($filters->complexId === (int) $id)>{{ $title }}</option>@endforeach
        </select>
    </div>
    @if($agents->isNotEmpty())
        <div>
            <label for="rf_agent" class="form-label">Agent</label>
            <select id="rf_agent" name="agent" class="form-select form-select-sm">
                <option value="">All agents</option>
                @foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($filters->agentId === (int) $agent->id)>{{ trim($agent->first_name . ' ' . $agent->last_name) ?: $agent->company_name }}</option>@endforeach
            </select>
        </div>
    @endif
    <div class="report-filter-actions">
        <button class="btn btn-sm btn-primary">Show</button>
        @if($filters->complexId || $filters->agentId || $filters->period !== 'this_month')<a href="{{ $action }}" class="btn btn-sm btn-light">Reset</a>@endif
    </div>
</form>
