@extends('pages.business.layouts.app')
@section('content')
@php
    $cur = default_currency();
    $buyer = $lead?->profile;
    $price = old('price', $unit?->sale_price);
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">Start a sale</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1"><li class="breadcrumb-item text-muted"><a href="{{ route('sales.index') }}" class="text-muted text-hover-primary">Sales</a></li><li class="breadcrumb-item text-dark">New sale</li></ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page sale-page" id="kt_content_container" data-sale-create>
    <header class="inv-head">
        <div>
            <span class="inv-eyebrow">New sale</span>
            <h2>{{ $unit ? 'Sell house ' . ($unit->unit_number ?: '#' . $unit->id) . ' at ' . $unit->complex->title : 'Sell a house' }}</h2>
            <p>Record the buyer, the agreed price and how they will pay. The house goes under offer straight away and leaves rentable inventory; the agent on the sale earns sales commission as the buyer pays.</p>
        </div>
    </header>

    @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Please check these details</strong>@foreach($errors->all() as $error)<p class="mb-0 mt-2">{{ $error }}</p>@endforeach</div>@endif
    @if($blocker)<div class="alert alert-warning" role="alert">{{ $blocker }}</div>@endif

    <form method="POST" action="{{ route('sales.store') }}" class="row g-6" data-sale-form>
        @csrf
        @if($lead)<input type="hidden" name="lead_id" value="{{ $lead->id }}">@endif
        <div class="col-xl-8">
            <section class="inv-panel sale-section">
                <h3>1. The house</h3>
                @if($unit)
                    <input type="hidden" name="unit_id" value="{{ $unit->id }}">
                    <dl class="sale-facts">
                        <div><dt>House</dt><dd>{{ $unit->label() }} · {{ $unit->complex->title }}</dd></div>
                        <div><dt>Layout</dt><dd>{{ $unit->layoutLabel() }} · {{ $unit->bathrooms }} baths · {{ number_format((float) $unit->sq_ft) }} sq ft{{ $unit->plotLabel() ? ' · plot ' . $unit->plotLabel() : '' }}</dd></div>
                        <div><dt>Asking price</dt><dd>{{ $unit->sale_price ? $cur . ' ' . number_format($unit->sale_price) : 'Not set' }}</dd></div>
                        @if($unit->title_number)<div><dt>Title</dt><dd>{{ $unit->title_number }}</dd></div>@endif
                    </dl>
                @else
                    <label class="form-label" for="sale-unit">House for sale</label>
                    <select class="form-select" id="sale-unit" name="unit_id" required data-sale-unit>
                        <option value="">{{ $houses->isEmpty() ? 'No house is for sale and free right now' : 'Choose a house' }}</option>
                        @foreach($houses->groupBy(fn ($h) => $h->complex?->title) as $property => $group)
                            <optgroup label="{{ $property }}">@foreach($group as $h)<option value="{{ $h->id }}" data-price="{{ $h->sale_price }}" @selected((int) old('unit_id') === $h->id)>{{ $h->label() }} · {{ $h->layoutLabel() }}{{ $h->sale_price ? ' · ' . $cur . ' ' . number_format($h->sale_price) : '' }}</option>@endforeach</optgroup>
                        @endforeach
                    </select>
                    <div class="form-text">Only houses marked for sale (or for rent or sale), available, and without a tenancy are listed.</div>
                @endif
            </section>

            <section class="inv-panel sale-section">
                <h3>2. The buyer</h3>
                <div class="row g-4">
                    <div class="col-md-6"><label class="form-label required" for="buyer-name">Full name or company</label><input class="form-control" id="buyer-name" name="buyer_name" maxlength="255" required value="{{ old('buyer_name', $buyer?->full_name) }}"></div>
                    <div class="col-md-6"><label class="form-label" for="buyer-phone">Phone</label><input class="form-control" id="buyer-phone" name="buyer_phone" maxlength="40" value="{{ old('buyer_phone', $buyer?->phone ?? $buyer?->mobile) }}"></div>
                    <div class="col-md-6"><label class="form-label" for="buyer-email">Email</label><input class="form-control" id="buyer-email" name="buyer_email" type="email" maxlength="255" value="{{ old('buyer_email', $buyer?->email) }}"></div>
                    <div class="col-md-3"><label class="form-label" for="buyer-id">ID / passport no.</label><input class="form-control" id="buyer-id" name="buyer_id_number" maxlength="60" value="{{ old('buyer_id_number') }}"></div>
                    <div class="col-md-3"><label class="form-label" for="buyer-pin">KRA PIN</label><input class="form-control" id="buyer-pin" name="buyer_kra_pin" maxlength="30" value="{{ old('buyer_kra_pin') }}"></div>
                </div>
                @if($lead)<p class="text-muted fs-7 mt-3 mb-0"><i class="bi bi-link-45deg" aria-hidden="true"></i> From lead {{ $lead->full_name }}. Starting the sale marks the lead as won.</p>@endif
            </section>

            <section class="inv-panel sale-section">
                <h3>3. Price & payment plan</h3>
                <div class="row g-4">
                    <div class="col-md-4"><label class="form-label required" for="sale-price">Agreed price ({{ $cur }})</label><input class="form-control" id="sale-price" name="price" type="number" min="1" step="any" required value="{{ $price }}" data-sale-price></div>
                    <div class="col-md-4"><label class="form-label required" for="sale-date">Agreement date</label><input class="form-control" id="sale-date" name="agreement_date" type="date" required value="{{ old('agreement_date', now()->toDateString()) }}"></div>
                    <div class="col-md-4"><label class="form-label" for="sale-deposit">Deposit ({{ $cur }})</label><input class="form-control" id="sale-deposit" name="deposit_amount" type="number" min="0" step="any" value="{{ old('deposit_amount') }}" data-sale-deposit placeholder="0 = none"><div class="form-text"><button type="button" class="btn btn-link p-0 fs-8" data-deposit-pct="10">10%</button> · <button type="button" class="btn btn-link p-0 fs-8" data-deposit-pct="20">20%</button> · <button type="button" class="btn btn-link p-0 fs-8" data-deposit-pct="30">30%</button> of the price</div></div>
                    <div class="col-md-4"><label class="form-label" for="sale-deposit-due">Deposit due</label><input class="form-control" id="sale-deposit-due" name="deposit_due_date" type="date" value="{{ old('deposit_due_date', now()->toDateString()) }}"></div>
                    <div class="col-md-8"><span class="form-label d-block">Balance after the deposit</span>
                        <div class="d-flex flex-wrap gap-5 pt-2">
                            <label class="form-check"><input class="form-check-input" type="radio" name="plan" value="equal" @checked(old('plan', 'equal') === 'equal') data-plan> <span class="form-check-label">Equal instalments</span></label>
                            <label class="form-check"><input class="form-check-input" type="radio" name="plan" value="custom" @checked(old('plan') === 'custom') data-plan> <span class="form-check-label">My own schedule</span></label>
                        </div></div>
                </div>
                <div class="row g-4 mt-1" data-plan-equal>
                    <div class="col-md-4"><label class="form-label" for="sale-count">Number of instalments</label><input class="form-control" id="sale-count" name="instalments" type="number" min="0" max="360" value="{{ old('instalments', 1) }}" data-sale-count><div class="form-text">1 = the balance in one payment.</div></div>
                    <div class="col-md-4"><label class="form-label" for="sale-first">First instalment due</label><input class="form-control" id="sale-first" name="first_due_date" type="date" value="{{ old('first_due_date', now()->addMonthNoOverflow()->toDateString()) }}" data-sale-first></div>
                    <div class="col-md-4"><label class="form-label" for="sale-every">Every</label><select class="form-select" id="sale-every" name="frequency" data-sale-every>@foreach(['monthly' => 'Month', 'quarterly' => 'Quarter', 'yearly' => 'Year'] as $v => $l)<option value="{{ $v }}" @selected(old('frequency', 'monthly') === $v)>{{ $l }}</option>@endforeach</select></div>
                </div>
                <div class="mt-4" data-plan-custom hidden>
                    <p class="text-muted fs-7 mb-2">Add each instalment of the balance. They must add up to the price less the deposit.</p>
                    <div data-custom-rows>
                        @foreach(old('schedule', [['due_date' => '', 'amount' => ''], ['due_date' => '', 'amount' => '']]) as $i => $row)
                            <div class="d-flex gap-2 mb-2"><input type="date" class="form-control" name="schedule[{{ $i }}][due_date]" value="{{ $row['due_date'] ?? '' }}" aria-label="Instalment {{ $i + 1 }} due date"><input type="number" min="0" step="any" class="form-control" name="schedule[{{ $i }}][amount]" value="{{ $row['amount'] ?? '' }}" placeholder="Amount" aria-label="Instalment {{ $i + 1 }} amount" data-custom-amount></div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-light" data-add-row><i class="bi bi-plus" aria-hidden="true"></i> Add instalment</button>
                </div>
            </section>

            <section class="inv-panel sale-section">
                <h3>4. Agent & notes</h3>
                <div class="row g-4">
                    <div class="col-md-6"><label class="form-label" for="sale-agent">Agent on the sale</label>
                        @include('pages.business.agents.partials.picker', ['name' => 'agent_id', 'id' => 'sale-agent', 'selected' => $defaultAgent, 'query' => [], 'placeholder' => 'Search agents'])
                        <div class="form-text">Earns sales commission under their sales agreement as the buyer pays. Defaults to the house's agent.</div></div>
                    <div class="col-md-6"><label class="form-label" for="sale-notes">Notes</label><textarea class="form-control" id="sale-notes" name="notes" rows="3" maxlength="5000" placeholder="Conditions, lawyer, mortgage lender…">{{ old('notes') }}</textarea></div>
                </div>
            </section>
        </div>

        <aside class="col-xl-4">
            <section class="inv-panel sale-section sale-sticky" aria-live="polite">
                <h3>Payment plan</h3>
                <ol class="sale-plan" data-plan-preview><li class="text-muted">Enter the price to see the plan.</li></ol>
                <div class="sale-total"><span>Total</span><strong data-plan-total>—</strong></div>
                <p class="text-danger fs-7 mb-0" data-plan-warning hidden></p>
                <button class="btn btn-primary w-100 mt-4" @disabled($blocker || (!$unit && $houses->isEmpty()))>Start sale & put house under offer</button>
                <a class="btn btn-light w-100 mt-2" href="{{ url()->previous() === url()->current() ? route('sales.index') : url()->previous() }}">Cancel</a>
            </section>
        </aside>
    </form>
</div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-sale-form]');
    const cur = @json($cur);
    const fmt = v => cur + ' ' + Math.round(v).toLocaleString();
    const $ = s => form.querySelector(s);
    const addMonths = (d, n) => { const x = new Date(d); const day = x.getDate(); x.setMonth(x.getMonth() + n); if (x.getDate() < day) x.setDate(0); return x; };
    const label = d => isNaN(d) ? '—' : d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    function render() {
        const price = parseFloat($('[data-sale-price]').value) || 0, deposit = parseFloat($('[data-sale-deposit]').value) || 0;
        const custom = form.querySelector('[name="plan"]:checked')?.value === 'custom';
        $('[data-plan-equal]').hidden = custom; $('[data-plan-custom]').hidden = !custom;
        const list = $('[data-plan-preview]'), warn = $('[data-plan-warning]');
        const rows = [];
        if (deposit > 0) rows.push(['Deposit', new Date($('[name="deposit_due_date"]').value), deposit]);
        const balance = Math.round((price - deposit) * 100) / 100;
        let total = deposit;
        if (custom) {
            form.querySelectorAll('[data-custom-rows] > div').forEach((r, i) => { const a = parseFloat(r.querySelector('[data-custom-amount]').value) || 0; if (a > 0) { rows.push(['Instalment ' + (i + 1), new Date(r.querySelector('[type=date]').value), a]); total += a; } });
        } else if (balance > 0) {
            const n = Math.max(1, parseInt($('[data-sale-count]').value) || 1), step = { monthly: 1, quarterly: 3, yearly: 12 }[$('[data-sale-every]').value] || 1;
            const each = Math.floor(balance / n * 100) / 100; let due = new Date($('[data-sale-first]').value);
            for (let i = 1; i <= n; i++) { const amt = i === n ? Math.round((balance - each * (n - 1)) * 100) / 100 : each; if (n <= 12 || i <= 3 || i === n) rows.push([n === 1 ? 'Balance' : 'Instalment ' + i + ' of ' + n, due, amt]); else if (i === 4) rows.push(['…', null, null]); total += amt; due = addMonths(due, step); }
        }
        list.innerHTML = price > 0 ? rows.map(([l, d, a]) => '<li><span>' + l + (d ? '<small>' + label(d) + '</small>' : '') + '</span>' + (a !== null ? '<strong>' + fmt(a) + '</strong>' : '') + '</li>').join('') : '<li class="text-muted">Enter the price to see the plan.</li>';
        $('[data-plan-total]').textContent = price > 0 ? fmt(total) : '—';
        const off = price > 0 && Math.abs(total - price) > 0.01;
        warn.hidden = !off && !(deposit > price);
        warn.textContent = deposit > price ? 'The deposit is more than the price.' : (off ? 'The plan adds up to ' + fmt(total) + ', not the price of ' + fmt(price) + '.' : '');
    }
    form.addEventListener('input', render); form.addEventListener('change', render);
    form.querySelectorAll('[data-deposit-pct]').forEach(b => b.addEventListener('click', () => { const p = parseFloat($('[data-sale-price]').value) || 0; $('[data-sale-deposit]').value = Math.round(p * b.dataset.depositPct / 100); render(); }));
    $('[data-add-row]').addEventListener('click', () => { const box = $('[data-custom-rows]'), i = box.children.length; box.insertAdjacentHTML('beforeend', '<div class="d-flex gap-2 mb-2"><input type="date" class="form-control" name="schedule[' + i + '][due_date]" aria-label="Instalment ' + (i + 1) + ' due date"><input type="number" min="0" step="any" class="form-control" name="schedule[' + i + '][amount]" placeholder="Amount" aria-label="Instalment ' + (i + 1) + ' amount" data-custom-amount></div>'); });
    const unit = $('[data-sale-unit]');
    if (unit) unit.addEventListener('change', () => { const p = unit.selectedOptions[0]?.dataset.price; if (p && !$('[data-sale-price]').value) $('[data-sale-price]').value = Math.round(p); render(); });
    render();
});
</script>
@endsection
