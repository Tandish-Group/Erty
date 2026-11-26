@extends('pages.business.layouts.app')
@section('content')
<div class="header" id="kt_header">
    <div class="container-xxl d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-icon btn-light d-lg-none" id="kt_aside_toggle" type="button" aria-label="Open navigation">☰</button>
            <span class="fw-bold fs-4"><a href="/agents/commissions" class="text-muted">Commissions</a> › {{ $statement['agent']['name'] }}</span>
        </div>
        @include('pages.business.include.topbar.index')
    </div>
</div>
<div class="content d-flex flex-column flex-column-fluid" id="kt_content">
<div class="container-xxl" id="kt_content_container" data-agent-statement-page>
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif

    <form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-6" data-statement-period>
        <input type="hidden" name="period" value="custom">
        <div><label class="form-label fs-8 mb-1" for="st-from">From</label><input type="date" class="form-control form-control-sm" id="st-from" name="from" value="{{ $period['from'] }}"></div>
        <div><label class="form-label fs-8 mb-1" for="st-to">To</label><input type="date" class="form-control form-control-sm" id="st-to" name="to" value="{{ $period['to'] }}"></div>
        <button class="btn btn-sm btn-light-primary" type="submit">Show</button>
    </form>

    @include('pages.business.agents.partials.statement', ['statement' => $statement])

    @if($canPay)
    <div class="card border-0 shadow-sm mb-6" data-payout-form>
        <div class="card-header border-0 pt-6"><div class="card-title"><h2 class="fs-5 fw-bold mb-0">Record payout</h2></div></div>
        <div class="card-body pt-2">
            @if($statement['due']['total'] > 0)
            <form method="POST" action="{{ route('tenant_management.agent_commissions.payouts', $agent->id) }}" class="row g-4 align-items-end">
                @csrf
                <input type="hidden" name="from" value="{{ $period['from'] }}"><input type="hidden" name="to" value="{{ $period['to'] }}">
                <div class="col-md-3"><label class="form-label fs-7" for="po-date">Paid on</label><input type="date" class="form-control" id="po-date" name="paid_at" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}"></div>
                <div class="col-md-3"><label class="form-label fs-7" for="po-method">Method</label>
                    <select class="form-select" id="po-method" name="method">
                        @foreach(['bank_transfer' => 'Bank transfer', 'mobile_money' => 'Mobile money', 'payroll' => 'With payroll', 'cash' => 'Cash', 'check' => 'Cheque', 'other' => 'Other'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select></div>
                <div class="col-md-3"><label class="form-label fs-7" for="po-ref">Reference</label><input type="text" class="form-control" id="po-ref" name="reference" maxlength="255"></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary w-100" data-record-payout>Pay {{ ($statement['currency'] ?? default_currency()) . ' ' . number_format($statement['due']['total'], 2) }}</button></div>
                <div class="col-12"><label class="form-label fs-7" for="po-notes">Notes</label><input type="text" class="form-control" id="po-notes" name="notes" maxlength="1000"></div>
            </form>
            <p class="text-muted fs-8 mt-3 mb-0">Marks the commission lines marked "Due" above as paid, so they are not paid again. Base salary is paid separately.</p>
            @else
                <p class="text-muted fs-7 mb-0">Nothing is due for this period.</p>
            @endif
        </div>
    </div>
    @endif
</div>
</div>
@endsection
