{{-- Commission for a period: same calculation as Agents → Commissions (AgentCommissionService::forAgent). --}}
@php
    $typeLabels = ['sales_value' => 'Share of collections', 'net_profit' => 'Share of collections after property expenses', 'flat_rate' => 'Flat amount per tenant', 'tiered' => 'Tiered by collections'];
    $money = fn ($v) => money($v);
    $type = $commission['commission_type'] ?? null;
@endphp
@if(!empty($compact))
<details class="agent-commission-note" data-agent-commission>
    <summary>How your commission is worked out</summary>
    @include('pages.business.agents.partials.commission-explanation')
</details>
@else
<div class="card border-0 shadow-sm mb-6" data-agent-commission>
    <div class="card-header border-0 pt-6 flex-wrap gap-3">
        <div class="card-title flex-column align-items-start">
            <h2 class="fs-4 fw-bold mb-1">{{ $title ?? 'Commission earned' }}</h2>
            <span class="text-muted fs-7" data-commission-period>{{ $period['label'] }}</span>
        </div>
        <form method="GET" class="d-flex flex-wrap align-items-end gap-2" data-commission-form @if(!empty($formHash)) action="{{ url()->current() }}#{{ $formHash }}" @endif>
            <div>
                <label class="form-label fs-8 mb-1" for="commission-period">Period</label>
                <select class="form-select form-select-sm" id="commission-period" name="period" onchange="const range = this.form.querySelector('[data-custom-range]'); range.classList.toggle('d-none', this.value !== 'custom'); range.classList.toggle('d-flex', this.value === 'custom'); if (this.value !== 'custom') this.form.submit();">
                    @foreach(['this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'this_year' => 'This year', 'custom' => 'Custom range'] as $value => $label)
                        <option value="{{ $value }}" @selected($period['key'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="{{ $period['key'] === 'custom' ? 'd-flex' : 'd-none' }} align-items-end gap-2" data-custom-range>
                <div><label class="form-label fs-8 mb-1" for="commission-from">From</label><input type="date" class="form-control form-control-sm" id="commission-from" name="from" value="{{ $period['from'] }}"></div>
                <div><label class="form-label fs-8 mb-1" for="commission-to">To</label><input type="date" class="form-control form-control-sm" id="commission-to" name="to" value="{{ $period['to'] }}"></div>
                <button class="btn btn-sm btn-light-primary" type="submit">Show</button>
            </div>
        </form>
    </div>
    <div class="card-body pt-2">
        <div class="row g-4 mb-4">
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Commission</div><div class="fs-3 fw-bold text-primary" data-commission-amount>{{ $money($commission['total_commission'] ?? $commission['default_commission']) }}</div>@if(($commission['override_commission'] ?? 0) > 0)<div class="text-muted fs-8">{{ $money($commission['default_commission']) }} own + {{ $money($commission['override_commission']) }} on delegated units</div>@endif</div></div>
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Rent collected on credited tenancies</div><div class="fs-4 fw-bold">{{ $money($commission['total_payments']) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Paying tenancies</div><div class="fs-4 fw-bold">{{ number_format($commission['tenant_count']) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border border-dashed rounded p-4 h-100"><div class="text-muted fs-8 fw-semibold">Property expenses in period</div><div class="fs-4 fw-bold">{{ $money($commission['total_expenses']) }}</div></div></div>
        </div>
        @if(($commission['override_rate'] ?? 0) > 0 || ($commission['override_payments'] ?? 0) > 0)
        <div class="border rounded p-4 mb-4" data-commission-override>
            <div class="fw-semibold fs-7 mb-1">On units delegated to sub-agents</div>
            <div class="fs-7 text-muted">{{ rtrim(rtrim(number_format((float) $commission['override_rate'], 2), '0'), '.') }}% of {{ $money($commission['override_payments']) }} collected on units this agent delegated, on properties where they are primary manager = <strong class="text-dark">{{ $money($commission['override_commission']) }}</strong>.</div>
        </div>
        @endif
        @include('pages.business.agents.partials.commission-explanation')
    </div>
</div>
@endif
