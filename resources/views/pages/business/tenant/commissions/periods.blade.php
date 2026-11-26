{{-- Period commission (net of expenses, flat per tenant, tiered) and everything waiting for approval. --}}
<div class="row g-6">
    <div class="col-xl-4">
        <section class="tenant-panel tenant-panel--padded mb-6">
            <h2 class="fs-5 fw-bolder mb-1">Calculate a month</h2>
            <p class="text-muted fs-7 mb-4">Works out commission paid per period (% of rent less expenses, flat per paying tenant, tiered) from the rent collected that month. Results wait for approval. It also runs automatically each month.</p>
            @if($canEdit)
            <form method="post" action="{{ route('tenant_management.agent_commissions.periods') }}" class="d-flex gap-2 align-items-end">
                @csrf
                <div class="flex-grow-1"><label class="form-label fs-7" for="period-month">Month</label><input type="month" class="form-control form-control-sm" id="period-month" name="month" value="{{ $month }}" max="{{ now()->format('Y-m') }}"></div>
                <button class="btn btn-sm btn-primary">Calculate</button>
            </form>
            @endif
            @unless($hasPeriodAgreements)<p class="text-muted fs-8 mt-3 mb-0">No agreement uses a period method yet, so there is nothing to calculate.</p>@endunless
        </section>
    </div>
    <div class="col-xl-8">
        <section class="tenant-panel tenant-panel--padded mb-6" data-period-entries>
            <h2 class="fs-5 fw-bolder mb-4">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</h2>
            @if($periodEntries->isEmpty())
                <p class="text-muted mb-0">No period commission recorded for this month.</p>
            @else
            <div class="tenant-table-scroll" role="region" aria-label="Period commission" tabindex="0">
                <table class="tenant-table" style="min-width:620px">
                    <thead><tr><th>Beneficiary</th><th>How</th><th class="text-end">Basis</th><th class="text-end">Commission</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach($periodEntries as $e)
                        @php $b = $e->breakdown ?? []; @endphp
                        <tr><td><strong>{{ $e->beneficiary_type === 'company' ? 'Company' : $e->agent?->full_name }}</strong><small>{{ $e->kind === 'adjustment' ? 'Adjustment to an approved entry' : '' }}</small></td>
                            <td>{{ $b['agreement'] ?? $e->method }}<small>{{ isset($b['expenses']) ? 'Expenses '.number_format($b['expenses'], 2).' · ' : '' }}{{ isset($b['tenants']) ? $b['tenants'].' paying tenants' : '' }}{{ !empty($b['tier']) ? ' · tier from '.number_format($b['tier']['min']).' at '.$b['tier']['rate'].'%' : '' }}</small></td>
                            <td class="text-end">{{ number_format($e->basis_amount, 2) }}</td><td class="text-end fw-bold">{{ number_format($e->amount, 2) }}</td>
                            <td><span class="badge {{ $e->status === 'pending' ? 'badge-light-warning' : ($e->status === 'paid' ? 'badge-light-success' : 'badge-light-primary') }}">{{ ucfirst($e->status) }}</span></td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>

        <section class="tenant-panel tenant-panel--padded" data-pending-approvals>
            <h2 class="fs-5 fw-bolder mb-1">Waiting for approval</h2>
            <p class="text-muted fs-7 mb-4">Period commission, and payment commission that is more than the company's fee on that rent. Only approved commission can be paid.</p>
            @if($pending->isEmpty())
                <div class="platform-empty" style="display:flex;gap:10px;align-items:center;color:#3f7f5a;background:#f0faf4;border-radius:10px;padding:14px 16px;font-weight:600;font-size:.9rem">Nothing is waiting for approval.</div>
            @else
            <form method="post" action="{{ route('tenant_management.agent_commissions.approve') }}">
                @csrf
                <div class="tenant-table-scroll" role="region" aria-label="Waiting for approval" tabindex="0">
                    <table class="tenant-table" style="min-width:620px">
                        <thead><tr>@if($canEdit)<th><input type="checkbox" class="form-check-input" aria-label="Select all" onclick="this.closest('table').querySelectorAll('[name=&quot;entry_ids[]&quot;]').forEach(c => c.checked = this.checked)"></th>@endif<th>Entry</th><th>Beneficiary</th><th>Why</th><th class="text-end">Commission</th></tr></thead>
                        <tbody>
                        @foreach($pending as $e)
                            <tr>@if($canEdit)<td><input type="checkbox" class="form-check-input" name="entry_ids[]" value="{{ $e->id }}" aria-label="Approve entry {{ $e->id }}"></td>@endif
                                <td>#{{ $e->id }}<small>{{ $e->kind === 'payment' ? $e->collected_on?->format('j M Y') : $e->period_from?->format('M Y') }}</small></td>
                                <td><strong>{{ $e->beneficiary_type === 'company' ? 'Company' : $e->agent?->full_name }}</strong><small>{{ $e->complex?->title }}</small></td>
                                <td class="fs-7">{{ $e->breakdown['note'] ?? ($e->kind === 'period' ? 'Period commission' : ucfirst($e->kind)) }}</td>
                                <td class="text-end fw-bold">{{ number_format($e->amount, 2) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($canEdit)<button class="btn btn-sm btn-primary mt-4">Approve selected</button>@endif
            </form>
            @endif
        </section>
    </div>
</div>
