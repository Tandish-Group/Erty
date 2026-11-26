@extends('pages.business.layouts.app')
@section('content')
@php
    $cur = default_currency();
    $money = fn ($v) => money($v, $cur, 0);
    $tone = ['reserved' => 'is-held', 'agreed' => 'is-let', 'completed' => 'is-free', 'cancelled' => ''];
    $filtered = $search !== '' || $status || request()->filled('complex_id');
@endphp
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center" id="kt_header_container">
        <div class="page-title d-flex flex-column justify-content-center">
            <h1 class="text-dark fw-bolder my-0 fs-3">Sales</h1>
            <ul class="breadcrumb breadcrumb-dot fw-bold fs-7 my-1"><li class="breadcrumb-item text-muted"><a href="{{ route('complex.index') }}" class="text-muted text-hover-primary">Property portfolio</a></li><li class="breadcrumb-item text-dark">House sales</li></ul>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>

<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl inv-page" id="kt_content_container" data-sales-index>

    <header class="inv-head">
        <div>
            <span class="inv-eyebrow">House sales</span>
            <h2>Sales in progress and completed</h2>
            <p>Each sale has a buyer, a price and a payment plan. Record the buyer's payments as they come in; complete the sale once it is paid and the house is handed over.</p>
        </div>
        @if($canCreate)
        <div class="inv-actions"><a class="btn btn-primary" href="{{ route('sales.create') }}"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>Start a sale</a></div>
        @endif
    </header>

    <div class="inv-stats" data-sales-stats>
        <div><span>In progress</span><strong>{{ $stats['live'] }}</strong><small>{{ $forSale }} {{ \Illuminate\Support\Str::plural('house', $forSale) }} for sale and free</small></div>
        <div><span>Under agreement</span><strong>{{ $money($stats['value']) }}</strong><small>value of sales in progress</small></div>
        <div><span>Overdue</span><strong class="{{ $stats['overdue'] > 0 ? 'text-danger' : '' }}">{{ $money($stats['overdue']) }}</strong><small>{{ $stats['overdue_count'] ? $stats['overdue_count'] . ' ' . \Illuminate\Support\Str::plural('sale', $stats['overdue_count']) . ' behind on the plan' : 'no instalment past due' }}</small></div>
        <div class="inv-stat-area">
            @php $pct = $stats['value'] > 0 ? round($stats['collected'] / $stats['value'] * 100) : 0; @endphp
            <span>Collected on sales in progress</span><strong>{{ $money($stats['collected']) }}</strong>
            <div class="inv-bar" role="img" aria-label="{{ $pct }}% collected"><i style="width: {{ $pct }}%"></i></div>
            <small>{{ $pct }}% of the agreed value · {{ $stats['completed'] }} {{ \Illuminate\Support\Str::plural('sale', $stats['completed']) }} completed this year</small>
        </div>
    </div>

    <section class="inv-panel">
        <form method="GET" class="inv-toolbar" role="search" aria-label="Filter sales">
            <div class="inv-search"><i class="bi bi-search" aria-hidden="true"></i><input class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="Buyer or sale reference" aria-label="Search buyer or sale reference"></div>
            @if($complexes->count() > 1)
            <select class="form-select form-select-sm" name="complex_id" aria-label="Property"><option value="">All properties</option>@foreach($complexes as $c)<option value="{{ $c->id }}" @selected((int) request('complex_id') === $c->id)>{{ $c->title }}</option>@endforeach</select>
            @endif
            <select class="form-select form-select-sm" name="status" aria-label="Status"><option value="">Any status</option>@foreach(\App\Models\PropertySale::STATUSES as $value => $label)<option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>@endforeach</select>
            <button class="btn btn-sm btn-primary">Apply</button>
            @if($filtered)<a class="btn btn-sm btn-light" href="{{ route('sales.index') }}">Reset</a>@endif
            <span class="inv-count">{{ $sales->total() }} {{ \Illuminate\Support\Str::plural('sale', $sales->total()) }}</span>
        </form>
        <div class="table-responsive">
            <table class="table inv-table align-middle mb-0">
                <thead><tr><th>House</th><th>Buyer</th><th>Price</th><th>Paid</th><th>Status</th><th>Agent</th><th class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                @forelse($sales as $sale)
                    @php
                        $paid = $sale->collected();
                        $pct = $sale->price > 0 ? min(100, round($paid / $sale->price * 100)) : 0;
                        $late = $sale->isLive() ? $sale->overdue() : collect();
                        $next = $sale->instalments->first(fn ($i) => $i->outstanding() > 0);
                    @endphp
                    <tr data-sale="{{ $sale->reference }}">
                        <td>
                            <div class="inv-space">House {{ $sale->unit?->unit_number ?: '#' . $sale->residential_unit_id }}</div>
                            <div class="inv-sub">{{ $sale->complex?->title }} · {{ $sale->unit?->layoutLabel() }}</div>
                        </td>
                        <td>{{ $sale->buyer_name }}<div class="inv-sub">{{ $sale->reference }} · agreed {{ $sale->agreement_date->format('j M Y') }}</div></td>
                        <td class="text-nowrap"><strong>{{ $money($sale->price) }}</strong></td>
                        <td class="inv-area">
                            <div><strong>{{ $money($paid) }}</strong> · {{ $pct }}%</div>
                            <div class="inv-bar is-small" role="img" aria-label="{{ $pct }}% paid"><i style="width: {{ $pct }}%"></i></div>
                            <div class="inv-sub">@if($late->isNotEmpty())<span class="text-danger">{{ $money($late->sum(fn ($i) => $i->outstanding())) }} overdue</span>@elseif($sale->isLive() && $next)Next: {{ $money($next->outstanding()) }} due {{ $next->due_date->format('j M Y') }}@elseif($sale->status === 'completed')Completed {{ $sale->completed_at?->format('j M Y') }}@else—@endif</div>
                        </td>
                        <td><span class="inv-status {{ $tone[$sale->status] ?? '' }}">{{ $sale->statusLabel() }}</span></td>
                        <td class="fs-7">{{ $sale->agent?->full_name ?? 'No agent' }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-light" href="{{ route('sales.show', $sale->reference) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="inv-empty">
                        <i class="bi bi-house-check" aria-hidden="true"></i>
                        <strong>{{ $filtered ? 'No sales match' : 'No sales yet' }}</strong>
                        <span>{{ $filtered ? 'Change the search or filters.' : ($forSale ? 'Start a sale from a house that is for sale.' : 'Mark houses "for sale" on a property, then start a sale here.') }}</span>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($sales->hasPages())<div class="inv-pages">{{ $sales->links('pagination::bootstrap-5') }}</div>@endif
    </section>
</div>
</div>
@endsection
