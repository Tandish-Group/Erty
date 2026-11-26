{{-- Commission agreements: who earns what, where, and since when. Changes start a new version; old versions stay. --}}
@php
    $entitlementLabel = ['management_fee' => 'Company commission', 'agent' => 'Agent commission', 'primary_on_delegated' => 'Primary manager on delegated units', 'sale' => 'Sales commission'];
    $scopeLabel = ['company' => 'Company-wide default', 'property' => 'Property', 'agent' => 'Agent', 'agent_property' => 'Agent at a property', 'unit' => 'Unit'];
    $current = $agreements->whereNull('effective_to');
    $ended = $agreements->whereNotNull('effective_to');
    $where = function ($a) use ($unitNumbers) {
        return match ($a->scope) {
            'company' => 'Whole company',
            'property' => $a->complex?->title,
            'agent' => $a->agent?->full_name,
            'agent_property' => $a->agent?->full_name . ' at ' . $a->complex?->title,
            'unit' => ($a->complex?->title) . ' · ' . ($unitNumbers[$a->unit_type][$a->unit_id] ?? '#'.$a->unit_id) . ($a->agent ? ' (' . $a->agent->full_name . ')' : ''),
            default => '',
        };
    };
@endphp
<div class="row g-6">
    <div class="col-xl-8">
        @unless($hasFee)
            <div class="alert alert-light-warning border border-warning mb-6 fs-7">No company commission is set. The company earns nothing on rent collected, and agent commission is recorded as an additional cost. @if($canEditFee)Add a company-wide commission to start.@endif</div>
        @endunless
        <section class="tenant-panel tenant-panel--padded mb-6" data-agreements-current>
            <h2 class="fs-5 fw-bolder mb-1">In force</h2>
            <p class="text-muted fs-7 mb-4">The most specific agreement wins. Company commission: property, then company. Agent commission: unit, then agent at a property, then property, then agent, then company.</p>
            @if($current->isEmpty())
                <p class="text-muted mb-0">No agreements yet.</p>
            @else
            <div class="tenant-table-scroll" role="region" aria-label="Agreements in force" tabindex="0">
                <table class="tenant-table" style="min-width:760px">
                    <thead><tr><th>For</th><th>Applies to</th><th>Terms</th><th>Funding</th><th>Since</th><th></th></tr></thead>
                    <tbody>
                    @foreach($current as $a)
                        <tr data-agreement="{{ $a->id }}">
                            <td><strong>{{ $entitlementLabel[$a->entitlement] }}</strong><small>{{ $a->name }}</small></td>
                            <td>{{ $where($a) }}<small>{{ $scopeLabel[$a->scope] }}</small></td>
                            <td>{{ $a->describe() }}@if($a->entitlement === 'primary_on_delegated')<small>{{ $a->sharing === 'share_of_pool' ? 'Pool shared: sub-agent gets '.rtrim(rtrim(number_format($a->sub_share_pct, 2), '0'), '.').'%' : 'On top of the sub-agent\'s own commission' }}</small>@endif
                                @if($a->method === 'tiered' && $a->tiers)<small>@foreach($a->tiers as $t){{ number_format($t['min']) }}–{{ $t['max'] === null ? '∞' : number_format($t['max']) }}: {{ $t['rate'] }}%@if(!$loop->last) · @endif @endforeach</small>@endif</td>
                            <td>{{ $a->entitlement === 'management_fee' ? '—' : ($a->entitlement === 'sale' ? 'Paid by the business' : ($a->funding === 'company_fee' ? 'From the company fee' : 'Additional cost')) }}</td>
                            <td class="text-nowrap">{{ $a->effective_from->format('j M Y') }}</td>
                            <td class="text-end text-nowrap">
                                @if($canEdit && ($a->entitlement !== 'management_fee' || $canEditFee))
                                    @php $changePayload = ["id" => $a->id, "entitlement" => $a->entitlement, "scope" => $a->scope, "complex" => $a->complex ? ["id" => $a->complex->id, "title" => $a->complex->title] : null, "agent" => $a->agent ? ["id" => $a->agent->id, "name" => $a->agent->full_name] : null, "unit_type" => $a->unit_type, "unit_number" => $unitNumbers[$a->unit_type][$a->unit_id] ?? null, "method" => $a->method, "rate" => $a->rate, "flat_amount" => $a->flat_amount, "tiers" => $a->tiers, "sharing" => $a->sharing, "sub_share_pct" => $a->sub_share_pct, "funding" => $a->funding, "name" => $a->name]; @endphp
                                    <button type="button" class="btn btn-sm btn-light" data-agreement-change="{{ json_encode($changePayload) }}">Change</button>
                                    <form method="post" action="{{ route('tenant_management.agent_commissions.agreements.end', $a->id) }}" class="d-inline" onsubmit="const d = prompt('Last day of this agreement (YYYY-MM-DD)', '{{ now()->toDateString() }}'); if (!d) return false; this.last_day.value = d;">@csrf<input type="hidden" name="last_day"><button class="btn btn-sm btn-light-danger">End</button></form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        @if($ended->isNotEmpty())
        <section class="tenant-panel tenant-panel--padded" data-agreements-history>
            <h2 class="fs-5 fw-bolder mb-4">Earlier versions</h2>
            <div class="tenant-table-scroll" role="region" aria-label="Earlier agreements" tabindex="0">
                <table class="tenant-table" style="min-width:680px">
                    <thead><tr><th>For</th><th>Applies to</th><th>Terms</th><th>In force</th><th>Changed because</th></tr></thead>
                    <tbody>
                    @foreach($ended as $a)
                        <tr><td>{{ $entitlementLabel[$a->entitlement] }}</td><td>{{ $where($a) }}</td><td>{{ $a->describe() }}</td>
                            <td class="text-nowrap">{{ $a->effective_from->format('j M Y') }} – {{ $a->effective_to->format('j M Y') }}</td><td class="fs-7">{{ $a->change_reason ?: '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif
    </div>

    <div class="col-xl-4">
        @if($canEdit)
        <section class="tenant-panel tenant-panel--padded" data-agreement-form-panel>
            <h2 class="fs-5 fw-bolder mb-1" data-agreement-form-title>Add an agreement</h2>
            <p class="text-muted fs-7 mb-4" data-agreement-form-help>New terms apply to rent collected from the start date.</p>
            <form method="post" action="{{ route('tenant_management.agent_commissions.agreements.save') }}" data-agreement-form>
                @csrf
                <input type="hidden" name="replaces_id" value="">
                <div class="mb-3"><label class="form-label fs-7" for="ag-entitlement">Who earns</label>
                    <select class="form-select form-select-sm" id="ag-entitlement" name="entitlement">
                        @if($canEditFee)<option value="management_fee">The company (management commission)</option>@endif
                        <option value="agent">The agent responsible for the unit</option>
                        <option value="primary_on_delegated">The primary manager, on units delegated to others</option>
                        @module('sales')<option value="sale">The agent on a house sale (sales commission)</option>@endmodule
                    </select></div>
                <div class="mb-3"><label class="form-label fs-7" for="ag-scope">Applies to</label>
                    <select class="form-select form-select-sm" id="ag-scope" name="scope">
                        @foreach($scopeLabel as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select></div>
                <div class="mb-3" data-field="complex"><label class="form-label fs-7" for="ag-complex">Property</label>@include('pages.business.complex.partials.picker', ['name' => 'complex_id', 'id' => 'ag-complex', 'selected' => null])</div>
                <div class="mb-3" data-field="agent"><label class="form-label fs-7" for="ag-agent">Agent</label>@include('pages.business.agents.partials.picker', ['name' => 'agent_id', 'id' => 'ag-agent', 'selected' => null, 'query' => []])</div>
                <div class="row g-2 mb-3" data-field="unit">
                    <div class="col-5"><label class="form-label fs-7" for="ag-unit-type">Unit type</label><select class="form-select form-select-sm" id="ag-unit-type" name="unit_type"><option value="residential">Residential</option><option value="commercial">Commercial</option></select></div>
                    <div class="col-7"><label class="form-label fs-7" for="ag-unit">Unit number</label><input class="form-control form-control-sm" id="ag-unit" name="unit_number" maxlength="50"></div>
                </div>
                <div class="mb-3"><label class="form-label fs-7" for="ag-method">How it is worked out</label>
                    <select class="form-select form-select-sm" id="ag-method" name="method">
                        <option value="percent_collected">% of each rent payment (recorded with the payment)</option>
                        <option value="percent_net">% of rent less property expenses (per month)</option>
                        <option value="flat_per_tenant">Flat amount per paying tenant (per month)</option>
                        <option value="tiered">Tiered by rent collected (per month)</option>
                        <option value="percent_of_sale" data-sale>% of the sale price (earned as the buyer pays)</option>
                        <option value="flat_per_sale" data-sale>Flat amount per house sold (earned as the buyer pays)</option>
                    </select></div>
                <div class="mb-3" data-field="rate"><label class="form-label fs-7" for="ag-rate">Rate (%)</label><input type="number" step="0.001" min="0" max="100" class="form-control form-control-sm" id="ag-rate" name="rate"></div>
                <div class="mb-3" data-field="flat"><label class="form-label fs-7" for="ag-flat" data-flat-label>Amount per paying tenant</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="ag-flat" name="flat_amount"></div>
                <div class="mb-3" data-field="tiers"><span class="form-label fs-7 d-block">Tiers (rent collected in the month → rate)</span>
                    @for($i = 0; $i < 4; $i++)
                        <div class="d-flex gap-1 mb-1"><input type="number" min="0" class="form-control form-control-sm" name="tiers[{{ $i }}][min]" placeholder="From" aria-label="Tier {{ $i + 1 }} from"><input type="number" min="0" class="form-control form-control-sm" name="tiers[{{ $i }}][max]" placeholder="To (blank = no limit)" aria-label="Tier {{ $i + 1 }} to"><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" name="tiers[{{ $i }}][rate]" placeholder="%" aria-label="Tier {{ $i + 1 }} rate"></div>
                    @endfor
                </div>
                <div class="mb-3" data-field="sharing"><label class="form-label fs-7" for="ag-sharing">With the sub-agent</label>
                    <select class="form-select form-select-sm" id="ag-sharing" name="sharing"><option value="separate">Separate: on top of the sub-agent's own commission</option><option value="share_of_pool">Share one pool (the rate above) with the sub-agent</option></select></div>
                <div class="mb-3" data-field="share"><label class="form-label fs-7" for="ag-share">Sub-agent's share of the pool (%)</label><input type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" id="ag-share" name="sub_share_pct"></div>
                <div class="mb-3" data-field="funding"><label class="form-label fs-7" for="ag-funding">Paid</label>
                    <select class="form-select form-select-sm" id="ag-funding" name="funding"><option value="company_fee">Out of the company's fee (default)</option><option value="additional_cost">As an additional cost</option></select></div>
                <div class="mb-3" data-field="counts"><span class="form-label fs-7 d-block">Counts</span>
                    @foreach(['rent' => 'Rent', 'service_charge' => 'Service charges', 'late_fee' => 'Late fees', 'deposit' => 'Deposits'] as $v => $l)
                        <label class="form-check form-check-inline fs-7"><input class="form-check-input" type="checkbox" name="applies_to[]" value="{{ $v }}" @checked($v === 'rent')> {{ $l }}</label>
                    @endforeach
                    <div class="form-text">Only rent counts unless you tick others.</div></div>
                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="form-label fs-7" for="ag-from">Starts</label><input type="date" class="form-control form-control-sm" id="ag-from" name="effective_from" value="{{ now()->toDateString() }}" required></div>
                    <div class="col-6"><label class="form-label fs-7" for="ag-name">Name (optional)</label><input class="form-control form-control-sm" id="ag-name" name="name" maxlength="120"></div>
                </div>
                <div class="mb-4" data-field="reason" hidden><label class="form-label fs-7" for="ag-reason">Why it changes</label><input class="form-control form-control-sm" id="ag-reason" name="change_reason" maxlength="255"></div>
                <div class="d-flex gap-2"><button class="btn btn-sm btn-primary" data-agreement-submit>Save agreement</button><button type="button" class="btn btn-sm btn-light" data-agreement-reset hidden>Cancel change</button></div>
            </form>
        </section>
        @endif
    </div>
</div>
@if($canEdit)
<script>
    // Show only the fields that apply; "Change" loads an agreement into the form as a new version.
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-agreement-form]');
        if (!form) return;
        const field = n => form.querySelector('[data-field="' + n + '"]');
        const val = n => form.querySelector('[name="' + n + '"]').value;
        const sync = () => {
            const ent = val('entitlement'), scope = val('scope'), method = val('method');
            const scopeSelect = form.querySelector('[name="scope"]');
            [...scopeSelect.options].forEach(o => { o.hidden = ent === 'management_fee' && !['company', 'property'].includes(o.value); });
            if (scopeSelect.selectedOptions[0]?.hidden) scopeSelect.value = 'company';
            const s = scopeSelect.value;
            field('complex').hidden = !['property', 'agent_property', 'unit'].includes(s);
            field('agent').hidden = !['agent', 'agent_property', 'unit'].includes(s);
            field('unit').hidden = s !== 'unit';
            // Sales commission uses only the sale methods; rent commission never does.
            const sale = ent === 'sale';
            const methodSelect = form.querySelector('[name="method"]');
            [...methodSelect.options].forEach(o => { o.hidden = sale !== ('sale' in o.dataset); });
            if (methodSelect.selectedOptions[0]?.hidden) methodSelect.value = sale ? 'percent_of_sale' : 'percent_collected';
            const m = methodSelect.value;
            field('rate').hidden = ['flat_per_tenant', 'tiered', 'flat_per_sale'].includes(m);
            field('flat').hidden = !['flat_per_tenant', 'flat_per_sale'].includes(m);
            form.querySelector('[data-flat-label]').textContent = m === 'flat_per_sale' ? 'Amount per house sold' : 'Amount per paying tenant';
            field('counts').hidden = sale;
            field('tiers').hidden = m !== 'tiered';
            field('sharing').hidden = ent !== 'primary_on_delegated';
            field('share').hidden = ent !== 'primary_on_delegated' || val('sharing') !== 'share_of_pool';
            field('funding').hidden = ent === 'management_fee' || sale;
        };
        form.addEventListener('change', sync); sync();
        const setSelect2 = (name, id, text) => { const el = form.querySelector('[name="' + name + '"]'); if (!el) return; el.innerHTML = '<option value=""></option>' + (id ? '<option value="' + id + '" selected></option>' : ''); if (id) el.options[1].textContent = text; if (window.jQuery) jQuery(el).trigger('change'); };
        document.addEventListener('click', e => {
            const btn = e.target.closest('[data-agreement-change]');
            if (btn) {
                const a = JSON.parse(btn.dataset.agreementChange);
                form.replaces_id.value = a.id;
                ['entitlement', 'scope', 'method', 'sharing', 'funding'].forEach(n => { const el = form.querySelector('[name="' + n + '"]'); if (el && a[n]) el.value = a[n]; });
                form.rate.value = a.rate ?? ''; form.flat_amount.value = a.flat_amount ?? ''; form.sub_share_pct.value = a.sub_share_pct ?? ''; form.name.value = a.name ?? '';
                form.unit_type.value = a.unit_type || 'residential'; form.unit_number.value = a.unit_number || '';
                (a.tiers || []).forEach((t, i) => { ['min', 'max', 'rate'].forEach(k => { const el = form.querySelector('[name="tiers[' + i + '][' + k + ']"]'); if (el) el.value = t[k] ?? ''; }); });
                setSelect2('complex_id', a.complex?.id, a.complex?.title); setSelect2('agent_id', a.agent?.id, a.agent?.name);
                ['entitlement', 'scope'].forEach(n => form.querySelector('[name="' + n + '"]').setAttribute('disabled', 'disabled'));
                form.querySelectorAll('[data-locked]').forEach(x => x.remove());
                ['entitlement', 'scope'].forEach(n => { const h = document.createElement('input'); h.type = 'hidden'; h.name = n; h.value = a[n]; h.dataset.locked = 1; form.appendChild(h); });
                form.effective_from.value = new Date(Date.now() + 864e5).toISOString().slice(0, 10);
                document.querySelector('[data-agreement-form-title]').textContent = 'Change agreement';
                document.querySelector('[data-agreement-form-help]').textContent = 'The current terms end the day before the new start date. Commission already recorded keeps its terms.';
                field('reason').hidden = false; form.querySelector('[data-agreement-reset]').hidden = false;
                sync(); form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            if (e.target.closest('[data-agreement-reset]')) location.reload();
        });
    });
</script>
@endif
