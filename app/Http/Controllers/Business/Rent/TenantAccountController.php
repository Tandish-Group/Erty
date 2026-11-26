<?php

namespace App\Http\Controllers\Business\Rent;

use App\Http\Controllers\Controller;
use App\Models\TenantFollowUp;
use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * A tenancy's account: record money in (confirmed or awaiting clearance), confirm, reverse,
 * refund credit or deposit, add a charge, waive a late fee, and the statement.
 */
class TenantAccountController extends Controller
{
    public function __construct(private TenantAccountService $account, private TenantChargesService $charges)
    {
    }

    public function summary(int $tenancy)
    {
        $tenancy = $this->tenancy($tenancy);
        $open = $this->account->openInvoices($tenancy)->map(fn (TenantInvoice $i) => [
            'id' => $i->id,
            'number' => $i->invoice_number,
            'kind' => $i->kind_label,
            'description' => $i->line_items[0]['description'] ?? $i->kind_label,
            'due_date' => $i->due_date?->format('j M Y'),
            'amount' => (float) $i->amount,
            'balance' => $i->balance,
            'overdue' => $i->isOverdue(),
        ])->values();
        return response()->json(['status' => 200, 'summary' => $this->account->summary($tenancy), 'open_invoices' => $open, 'follow_ups' => $this->followUps($tenancy)]);
    }

    /** Record a call, visit or message about the tenant's account (nothing is sent from Erty). */
    public function storeFollowUp(Request $request, int $tenancy)
    {
        abort_unless($this->canFollowUp(), 403, 'Logging follow-ups needs permission to add tenants or record payments.');
        $tenancy = $this->tenancy($tenancy);
        $data = $request->validate([
            'method' => 'required|in:' . implode(',', array_keys(TenantFollowUp::METHODS)),
            'note' => 'required|string|max:2000',
            'contacted_on' => 'required|date|before_or_equal:today',
            'promise_date' => 'nullable|date|after_or_equal:contacted_on',
            'promise_amount' => 'nullable|numeric|min:0.01|required_with:promise_date',
        ]);
        TenantFollowUp::create($data + [
            'tenancy_id' => $tenancy->id,
            'user_id' => auth()->id(),
            'promise_status' => !empty($data['promise_date']) ? 'open' : null,
        ]);
        return response()->json(['status' => 200, 'message' => 'Follow-up recorded.', 'follow_ups' => $this->followUps($tenancy)]);
    }

    /** Mark a promise to pay as kept or broken. */
    public function updatePromise(Request $request, int $followUp)
    {
        abort_unless($this->canFollowUp(), 403);
        $followUp = TenantFollowUp::query()->whereKey($followUp)->whereNotNull('promise_date')
            ->whereIn('tenancy_id', TenantManagement::query()->where('managed_by', business_owner_id())->select('id'))->firstOrFail();
        $followUp->update($request->validate(['promise_status' => 'required|in:open,kept,broken']));
        return response()->json(['status' => 200, 'message' => 'Promise marked ' . $followUp->promise_status . '.', 'follow_ups' => $this->followUps($followUp->tenancy)]);
    }

    private function canFollowUp(): bool
    {
        $user = auth()->user();
        return $user->hasBusinessPermission('tenant_management', 'payments') || $user->hasBusinessPermission('tenant_management', 'create');
    }

    private function followUps(TenantManagement $tenancy): array
    {
        $ids = TenantManagement::query()->where('managed_by', business_owner_id())
            ->where(fn ($q) => $q->whereKey($tenancy->id)->orWhere('id', $tenancy->previous_tenancy_id ?? 0))->pluck('id');
        return TenantFollowUp::with('user:id,first_name,last_name')->whereIn('tenancy_id', $ids)
            ->latest('contacted_on')->latest('id')->limit(20)->get()
            ->map(fn (TenantFollowUp $f) => [
                'id' => $f->id,
                'method' => TenantFollowUp::METHODS[$f->method] ?? $f->method,
                'note' => $f->note,
                'contacted_on' => $f->contacted_on->format('j M Y'),
                'by' => $f->user ? trim($f->user->first_name . ' ' . $f->user->last_name) : '—',
                'promise_date' => $f->promise_date?->format('j M Y'),
                'promise_amount' => $f->promise_amount !== null ? (float) $f->promise_amount : null,
                'promise_status' => $f->promise_status,
                'promise_due' => $f->promise_status === 'open' && $f->promise_date?->lt(today()),
            ])->all();
    }

    public function recordPayment(Request $request, int $tenancy)
    {
        $tenancy = $this->tenancy($tenancy);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date|before_or_equal:today|after_or_equal:' . now()->subYears(2)->toDateString(),
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,check,card,other',
            'transaction_reference' => 'nullable|string|max:255',
            'payment_notes' => 'nullable|string|max:2000',
            'received' => 'required|in:confirmed,pending',
            'first_invoice_id' => 'nullable|integer',
            'payment_receipt' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,pdf',
        ]);
        $data['pending'] = $data['received'] === 'pending';
        if (!empty($data['first_invoice_id']) && !$this->account->openInvoices($tenancy)->contains('id', (int) $data['first_invoice_id'])) {
            throw ValidationException::withMessages(['first_invoice_id' => 'Choose an unpaid invoice of this tenant.']);
        }
        if ($request->hasFile('payment_receipt')) {
            $data['receipt_path'] = $request->file('payment_receipt')->store('users/' . business_owner_id() . '/tenants/' . $tenancy->tenancy_ref . '/payments/receipts', 'public');
        }
        $payment = $this->account->record($tenancy, $data);
        return $this->done($tenancy, $payment->payment_status === TenantPayment::PENDING
            ? 'Payment recorded as awaiting clearance. It will be applied once you confirm it.'
            : 'Payment recorded.' . ((float) $payment->unallocated_amount > 0 ? ' ' . $tenancy->currency . ' ' . number_format((float) $payment->unallocated_amount, 2) . ' is held as credit for the next invoice.' : ''));
    }

    public function confirm(int $payment)
    {
        $payment = $this->payment($payment);
        $this->account->confirm($payment);
        return $this->done($this->tenancy($payment->tenant_id), 'Payment confirmed and applied.');
    }

    public function reverse(Request $request, int $payment)
    {
        $payment = $this->payment($payment);
        $data = $request->validate(['reason' => 'required|string|max:255']);
        $this->account->reverse($payment, $data['reason']);
        return $this->done($this->tenancy($payment->tenant_id), 'Entry reversed. What it paid is owed again.');
    }

    public function refund(Request $request, int $tenancy)
    {
        $tenancy = $this->tenancy($tenancy);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'from' => 'required|in:credit,deposit',
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_method' => 'required|in:cash,bank_transfer,mobile_money,check,card,other',
            'transaction_reference' => 'nullable|string|max:255',
            'payment_notes' => 'nullable|string|max:2000',
        ]);
        $this->account->refund($tenancy, (float) $data['amount'], $data['from'], $data);
        return $this->done($tenancy, 'Refund recorded.');
    }

    public function charge(Request $request, int $tenancy)
    {
        $tenancy = $this->tenancy($tenancy);
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'due_date' => 'nullable|date',
        ]);
        $invoice = $this->charges->charge($tenancy, 'adjustment', (float) $data['amount'], $data['description'], isset($data['due_date']) ? Carbon::parse($data['due_date']) : null);
        $this->charges->notify($tenancy, collect([$invoice]));
        return $this->done($tenancy, 'Charge ' . $invoice->invoice_number . ' added.');
    }

    public function void(Request $request, int $invoice)
    {
        $invoice = TenantInvoice::query()->whereKey($invoice)->whereIn('tenancy_id', TenantManagement::query()->where('managed_by', business_owner_id())->select('id'))->firstOrFail();
        $data = $request->validate(['reason' => 'required|string|max:255']);
        if (!in_array($invoice->kind, ['late_fee', 'adjustment'], true)) {
            throw ValidationException::withMessages(['reason' => 'Only late fees and added charges can be waived. Rent changes are made with Edit tenancy.']);
        }
        $this->account->void($invoice, $data['reason']);
        return $this->done($this->tenancy($invoice->tenancy_id), $invoice->kind === 'late_fee' ? 'Late fee waived.' : 'Charge voided.');
    }

    public function statementPdf(Request $request, int $tenancy)
    {
        $tenancy = $this->tenancy($tenancy);
        $tenancy->loadMissing(['tenantProfile', 'manager.business']);
        $from = $request->query('from');
        $to = $request->query('to');
        $pdf = Pdf::loadView('pdf.tenant_statement', [
            'tenancy' => $tenancy,
            'statement' => $this->account->statement($tenancy, $from, $to),
            'summary' => $this->account->summary($tenancy),
            'from' => $from,
            'to' => $to,
            'business' => $tenancy->manager?->business?->business_name ?: config('app.name'),
        ])->setPaper('a4');
        return $pdf->download('statement-' . $tenancy->tenancy_ref . '.pdf');
    }

    public function receipt(int $payment)
    {
        $payment = $this->payment($payment);
        abort_unless($payment->receipt_path && Storage::disk('public')->exists($payment->receipt_path), 404);
        return Storage::disk('public')->download($payment->receipt_path);
    }

    private function tenancy(int $id): TenantManagement
    {
        return TenantManagement::query()->where('managed_by', business_owner_id())->findOrFail($id);
    }

    private function payment(int $id): TenantPayment
    {
        return TenantPayment::query()->whereKey($id)->whereIn('tenant_id', TenantManagement::query()->where('managed_by', business_owner_id())->select('id'))->firstOrFail();
    }

    private function done(TenantManagement $tenancy, string $message)
    {
        return response()->json(['status' => 200, 'message' => $message, 'summary' => $this->account->summary($tenancy)]);
    }
}
