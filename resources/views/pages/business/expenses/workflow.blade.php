@extends('pages.business.layouts.app')
@section('content')
<div class="content"><div class="container-xxl py-6">
    <a href="{{ route('business.expenses.index', ['complex_id' => encrypt($property->id)]) }}">← Property expense ledger</a>
    <div class="d-flex flex-wrap justify-content-between gap-3 my-6"><div><h1>Expense #{{ $expense->id }} · {{ $property->title }}</h1><p>{{ $expense->description }}</p><small class="text-muted">{{ $expense->scope }} · {{ $expense->expense_date }} · Allocation group {{ $expense->allocation_group ?: 'Legacy record' }}</small></div><span class="badge badge-light-primary align-self-start">{{ ucfirst($expense->workflow_status) }}</span></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    @if($expense->workflow_status === 'returned')
        <div class="alert alert-warning"><strong>Correction requested</strong><p class="mb-0">{{ data_get(json_decode($events->firstWhere('action', 'return')?->details ?? '{}', true), 'input.note', 'Review the expense details before resubmitting.') }}</p></div>
    @elseif($expense->workflow_status === 'void')
        <div class="alert alert-secondary">This expense is voided. Its evidence and history are retained, but it is excluded from the default ledger.</div>
    @endif
    <div class="row g-4 mb-6">@foreach(['Recorded cost' => $expense->amount, 'Payments recorded' => $paid / 100, 'Outstanding balance' => $balance / 100] as $label => $amount)<div class="col-md-4"><div class="card card-body"><small class="text-muted">{{ $label }}</small><strong class="fs-2">{{ $expense->currency }} {{ number_format($amount, 2) }}</strong></div></div>@endforeach</div>
    <p class="text-muted">Capture → evidence & reviewer → submit → approve or return → record payments. Payment entries document payments made elsewhere; they do not transfer money. Voided costs are excluded from the default ledger.</p>
    <div class="row g-6"><div class="col-lg-7">
        <div class="card card-body mb-6"><h2>1. Supplier & review responsibility</h2>
            @if($editable && $capabilities['edit'])
                <form method="POST" action="{{ route('business.expenses.workflow.act', $expense->id) }}">@csrf<input type="hidden" name="action" value="details"><input type="hidden" name="confirmed" value="1">
                    <div class="row g-4 mt-1">@foreach(['supplier' => 'Supplier / payee', 'invoice_reference' => 'Invoice reference', 'due_date' => 'Due date'] as $field => $label)<div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'due_date' ? 'date' : 'text' }}" value="{{ old($field, $expense->$field) }}" @required($field === 'supplier')></div>@endforeach
                    <div class="col-md-6"><label class="form-label" for="reviewer_id">Assigned reviewer</label><select required class="form-select" name="reviewer_id" id="reviewer_id"><option value="">Choose reviewer</option>@foreach($reviewers as $reviewer)<option value="{{ $reviewer->id }}" @selected($expense->reviewer_id == $reviewer->id)>{{ $reviewer->first_name }} {{ $reviewer->last_name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="category">Expense category</label><select required id="category" name="type" class="form-select"><option value="">Choose category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($expense->type == $category->id)>{{ $category->expense_type }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="recurrence">Repeat schedule</label><select name="recurrence" id="recurrence" class="form-select">@foreach(['none', 'monthly', 'quarterly', 'yearly'] as $frequency)<option @selected($expense->recurrence === $frequency)>{{ $frequency }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label" for="next_occurrence">Next draft date</label><input class="form-control" type="date" name="next_occurrence" id="next_occurrence" value="{{ $expense->next_occurrence }}"></div></div>
                    <p class="text-muted mt-3">Recurring costs generate a new draft only when requested on or after the due date. They are never automatically approved or paid.</p><button class="btn btn-primary mt-3">Save supplier & routing</button>
                </form>
            @else
                <dl class="row mb-0">
                    <dt class="col-sm-4">Supplier / payee</dt><dd class="col-sm-8">{{ $expense->supplier ?: 'Not recorded' }}</dd>
                    <dt class="col-sm-4">Invoice reference</dt><dd class="col-sm-8">{{ $expense->invoice_reference ?: 'Not recorded' }}</dd>
                    <dt class="col-sm-4">Due date</dt><dd class="col-sm-8">{{ $expense->due_date ?: 'Not recorded' }}</dd>
                    <dt class="col-sm-4">Assigned reviewer</dt><dd class="col-sm-8">{{ $reviewers->firstWhere('id', $expense->reviewer_id)?->first_name }} {{ $reviewers->firstWhere('id', $expense->reviewer_id)?->last_name }} @unless($reviewers->contains('id', $expense->reviewer_id))Reviewer unavailable — contact your account administrator.@endunless</dd>
                    <dt class="col-sm-4">Expense category</dt><dd class="col-sm-8">{{ $categories->firstWhere('id', $expense->type)?->expense_type ?: 'Not recorded' }}</dd>
                    <dt class="col-sm-4">Repeat schedule</dt><dd class="col-sm-8">{{ ucfirst($expense->recurrence) }}{{ $expense->next_occurrence ? ' · Next draft: '.$expense->next_occurrence : '' }}</dd>
                </dl>
            @endif
        </div>
        <div class="card card-body mb-6"><h2>2. Supporting evidence</h2><p class="text-muted">Invoices or receipts, PDF/JPG/PNG, up to 10 MB each. Files are private to your business.</p>
            @foreach($evidence as $file)<a class="d-block mb-3" href="{{ route('business.expenses.evidence.download', [$expense->id, $file->id]) }}">{{ $file->original_name }}</a>@endforeach
            @if($editable && $capabilities['edit'])<form method="POST" enctype="multipart/form-data" action="{{ route('business.expenses.evidence', $expense->id) }}">@csrf<label class="form-label" for="evidence">Attach invoice or receipt</label><input required class="form-control mb-3" type="file" id="evidence" name="evidence" accept=".pdf,.jpg,.jpeg,.png"><button class="btn btn-light-primary">Upload evidence</button></form>@endif
        </div>
        <div class="card card-body"><h2>Payment history</h2>@forelse($payments as $payment)<div class="border-bottom py-3">{{ $payment->paid_on }} · {{ $expense->currency }} {{ number_format($payment->amount, 2) }} · {{ $payment->method }} · {{ $payment->reference }}</div>@empty<p class="text-muted">No payments recorded. Existing expenses are not assumed paid.</p>@endforelse</div>
    </div><div class="col-lg-5">
        <div class="card card-body mb-6"><h2>3. Next action</h2>
            @if($editable && $capabilities['edit'])
                <p>Save the supplier, category and reviewer above, attach supporting evidence, then submit for review.</p>
                @include('pages.business.expenses.workflow-action', ['action' => 'submit', 'label' => 'Submit for review', 'note' => 'Evidence exception reason (required if no attachment)'])
            @elseif($expense->workflow_status === 'pending' && $capabilities['approve'])
                @include('pages.business.expenses.workflow-action', ['action' => 'approve', 'label' => 'Approve expense', 'note' => 'Review note'])
                @include('pages.business.expenses.workflow-action', ['action' => 'return', 'label' => 'Return for correction', 'note' => 'Required reason for return'])
            @elseif($expense->workflow_status === 'pending')<p>Waiting for the assigned reviewer.</p>@endif
            @if($expense->workflow_status === 'approved' && $balance > 0 && $capabilities['pay'])
                <form method="POST" action="{{ route('business.expenses.workflow.act', $expense->id) }}" class="mt-4">@csrf<input type="hidden" name="action" value="pay"><input type="hidden" name="submission_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                    <h3>Record payment</h3><label class="form-label" for="payment-amount">Amount ({{ $expense->currency }})</label><input required type="number" min="0.01" max="{{ $balance / 100 }}" step="0.01" name="amount" id="payment-amount" class="form-control mb-3">
                    <label class="form-label" for="paid_on">Payment date</label><input required class="form-control mb-3" type="date" name="paid_on" id="paid_on" max="{{ now()->toDateString() }}">
                    <label class="form-label" for="method">Method</label><select name="method" id="method" class="form-select mb-3">@foreach(['bank', 'mobile_money', 'cash', 'card', 'other'] as $method)<option>{{ $method }}</option>@endforeach</select>
                    <label class="form-label" for="reference">Payment reference / voucher</label><input required name="reference" id="reference" class="form-control mb-3" maxlength="255">
                    <label class="d-block mb-3"><input required type="checkbox" name="confirmed" value="1"> I confirm this payment was made and the reference is correct.</label><button class="btn btn-primary">Record payment</button>
                </form>
            @endif
            @if($expense->workflow_status === 'approved' && $expense->next_occurrence && $expense->next_occurrence <= now()->toDateString() && $capabilities['create'])
                @include('pages.business.expenses.workflow-action', ['action' => 'recur', 'label' => 'Create next recurring draft', 'note' => null])
            @endif
            @if($expense->workflow_status !== 'void' && $paid === 0 && $capabilities['delete'])
                <details class="mt-5"><summary>Void an incorrect expense</summary><p class="text-muted">Keeps the history and excludes this cost from the default ledger.</p>@include('pages.business.expenses.workflow-action', ['action' => 'void', 'label' => 'Void expense', 'note' => 'Required reason for voiding'])</details>
            @endif
        </div>
        <div class="card card-body"><h2>Audit history</h2>@forelse($events as $event)<details class="border-bottom py-3"><summary>{{ ucfirst(str_replace('_', ' ', $event->action)) }} · {{ $event->created_at }}</summary><p class="text-muted mt-2">Recorded by user #{{ $event->actor_id }}</p><pre class="small text-wrap">{{ json_encode(json_decode($event->details), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details>@empty<p class="text-muted">Legacy record. No previous approval or payment is inferred.</p>@endforelse</div>
    </div></div>
</div></div>
@endsection
