<?php

namespace App\Services\Rent;

use App\Services\Access\PropertyAccess;
use App\Services\Commission\CommissionLedgerService;
use App\Services\NotificationCenter;

use App\Models\Agent;
use App\Models\Complex;
use App\Models\TenantInvoice;
use App\Models\TenantInvoicePaymentAllocation;
use App\Models\TenantManagement;
use App\Models\TenantPayment;
use App\Models\TenantTenancyBilling;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A tenant's running account for one tenancy and the tenancies it was transferred from or to.
 *
 * - Each invoice holds only its own charges (rent, service charge, deposit, late fee, adjustment).
 * - A confirmed payment settles the oldest unpaid invoice first; anything left over is credit,
 *   used automatically when the next invoice is issued.
 * - A pending payment (e.g. an uncleared cheque) counts for nothing until it is confirmed.
 * - Reversing a payment (bounced, entered in error) reopens what it paid.
 * - The deposit is held, not revenue: "deposit applied" moves held money onto unpaid invoices,
 *   and a refund is money paid back out of credit.
 * - Every allocation records the agent holding the tenancy when it was made, and the lead agent
 *   who earns an override on it, so commission follows the agent at the time.
 */
class TenantAccountService
{
    /** Entries that put value on the account (cash, transfer credit, deposit applied). */
    public const CREDITING = ['payment', 'credit', 'deposit_release'];

    // ─── Account scope ────────────────────────────────────────────────────

    /** Ids of this tenancy and every tenancy linked to it by transfers. */
    public function chainIds(TenantManagement $tenancy): array
    {
        return $this->free(function () use ($tenancy) {
            $ids = [$tenancy->id];
            $queue = [$tenancy->id];
            while ($queue) {
                $id = array_shift($queue);
                $row = TenantManagement::query()->select('id', 'previous_tenancy_id')->find($id);
                $linked = TenantManagement::query()->where('previous_tenancy_id', $id)->pluck('id')->all();
                if ($row?->previous_tenancy_id) {
                    $linked[] = (int) $row->previous_tenancy_id;
                }
                foreach ($linked as $next) {
                    if (!in_array($next, $ids, true)) {
                        $ids[] = $next;
                        $queue[] = $next;
                    }
                }
            }
            sort($ids);
            return $ids;
        });
    }

    /** The newest tenancy in the chain (where new charges and credit live). */
    public function currentTenancy(TenantManagement $tenancy): TenantManagement
    {
        $ids = $this->chainIds($tenancy);
        return $this->free(fn () => TenantManagement::query()->whereIn('id', $ids)->orderByDesc('id')->first()) ?? $tenancy;
    }

    // ─── Figures ──────────────────────────────────────────────────────────

    /** Unpaid invoices, oldest due first. */
    public function openInvoices(TenantManagement $tenancy, bool $lock = false): Collection
    {
        $query = TenantInvoice::query()
            ->whereIn('tenancy_id', $this->chainIds($tenancy))
            ->whereIn('status', [TenantInvoice::UNPAID, TenantInvoice::PARTIAL])
            ->orderBy('due_date')->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        return $query->get();
    }

    public function outstanding(TenantManagement $tenancy): float
    {
        return round($this->openInvoices($tenancy)->sum(fn (TenantInvoice $i) => $i->balance), 2);
    }

    public function overdue(TenantManagement $tenancy): float
    {
        return round($this->openInvoices($tenancy)->filter(fn (TenantInvoice $i) => $i->isOverdue())->sum(fn (TenantInvoice $i) => $i->balance), 2);
    }

    /** Money received but not yet used on an invoice. */
    public function credit(TenantManagement $tenancy): float
    {
        return round((float) TenantPayment::query()
            ->whereIn('tenant_id', $this->chainIds($tenancy))
            ->whereIn('entry_type', self::CREDITING)
            ->where('payment_status', TenantPayment::CONFIRMED)
            ->sum('unallocated_amount'), 2);
    }

    /** What the tenant owes now (negative = the tenant is in credit). */
    public function balance(TenantManagement $tenancy): float
    {
        return round($this->outstanding($tenancy) - $this->credit($tenancy), 2);
    }

    /** Deposit billed (not voided) across the chain. */
    public function depositRequired(TenantManagement $tenancy): float
    {
        return round((float) TenantInvoice::query()->whereIn('tenancy_id', $this->chainIds($tenancy))
            ->where('kind', 'deposit')->where('status', '!=', TenantInvoice::VOID)->sum('amount'), 2);
    }

    /** Deposit money held: paid deposit invoices less what was applied to other charges or refunded. */
    public function depositHeld(TenantManagement $tenancy): float
    {
        $ids = $this->chainIds($tenancy);
        $paid = (float) TenantInvoicePaymentAllocation::query()
            ->whereIn('invoice_id', TenantInvoice::query()->whereIn('tenancy_id', $ids)->where('kind', 'deposit')->select('id'))
            ->whereNull('reversed_at')->sum('allocated_amount');
        $released = (float) TenantPayment::query()->whereIn('tenant_id', $ids)
            ->where('entry_type', 'deposit_release')->where('payment_status', TenantPayment::CONFIRMED)->sum('amount_paid');
        return round(max(0, $paid - $released), 2);
    }

    public function summary(TenantManagement $tenancy): array
    {
        $open = $this->openInvoices($tenancy);
        return [
            'currency' => $tenancy->currency ?: default_currency(),
            'outstanding' => round($open->sum(fn ($i) => $i->balance), 2),
            'overdue' => round($open->filter(fn ($i) => $i->isOverdue())->sum(fn ($i) => $i->balance), 2),
            'credit' => $this->credit($tenancy),
            'balance' => $this->balance($tenancy),
            'deposit_required' => $this->depositRequired($tenancy),
            'deposit_held' => $this->depositHeld($tenancy),
            'pending_payments' => round((float) TenantPayment::query()->whereIn('tenant_id', $this->chainIds($tenancy))
                ->where('payment_status', TenantPayment::PENDING)->sum('amount_paid'), 2),
            'open_invoices' => $open->count(),
        ];
    }

    // ─── Recording money ──────────────────────────────────────────────────

    /**
     * Record money received from the tenant.
     *
     * @param array{amount: float|string, payment_date: string, payment_method: string, transaction_reference?: ?string,
     *              payment_notes?: ?string, receipt_path?: ?string, pending?: bool, first_invoice_id?: ?int} $data
     */
    public function record(TenantManagement $tenancy, array $data): TenantPayment
    {
        $amount = round((float) $data['amount'], 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the amount received.']);
        }
        if (!empty($data['transaction_reference'])) {
            $duplicate = TenantPayment::query()->whereIn('tenant_id', $this->chainIds($tenancy))
                ->where('transaction_reference', $data['transaction_reference'])
                ->where('payment_status', '!=', TenantPayment::REVERSED)->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['transaction_reference' => 'A payment with this reference is already recorded for this tenant.']);
            }
        }

        return DB::transaction(function () use ($tenancy, $data, $amount) {
            $current = $this->lockAccount($tenancy);
            $pending = !empty($data['pending']);
            $payment = TenantPayment::create([
                'tenant_id' => $current->id,
                'entry_type' => 'payment',
                'recorded_by' => auth()->id(),
                'payment_date' => $data['payment_date'],
                'payment_period' => Carbon::parse($data['payment_date'])->format('Y-m'),
                'amount_paid' => $amount,
                'total_amount' => $amount,
                'unallocated_amount' => $pending ? 0 : $amount,
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_status' => $pending ? TenantPayment::PENDING : TenantPayment::CONFIRMED,
                'confirmed_at' => $pending ? null : now(),
                'confirmed_by' => $pending ? null : auth()->id(),
                'payment_notes' => $data['payment_notes'] ?? null,
                'receipt_path' => $data['receipt_path'] ?? null,
                'late_fee_paid_now' => false,
            ]);
            if (!$pending) {
                $this->allocate($payment, $current, $data['first_invoice_id'] ?? null, Carbon::parse($data['payment_date']));
            }
            app(NotificationCenter::class)->paymentRecorded($payment, $current);
            return $payment->fresh();
        });
    }

    /** A pending payment has cleared: apply it. */
    public function confirm(TenantPayment $payment): TenantPayment
    {
        if ($payment->payment_status !== TenantPayment::PENDING) {
            throw ValidationException::withMessages(['payment' => 'Only a payment awaiting clearance can be confirmed.']);
        }
        return DB::transaction(function () use ($payment) {
            $tenancy = $this->lockAccount($this->tenancyOf($payment));
            $payment->update([
                'payment_status' => TenantPayment::CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
                'unallocated_amount' => $payment->amount_paid,
            ]);
            $this->allocate($payment->fresh(), $tenancy, null, Carbon::parse($payment->payment_date));
            return $payment->fresh();
        });
    }

    /** Undo a payment that bounced or was entered in error; what it paid is owed again. */
    public function reverse(TenantPayment $payment, string $reason): TenantPayment
    {
        if ($payment->payment_status === TenantPayment::REVERSED) {
            throw ValidationException::withMessages(['payment' => 'This entry is already reversed.']);
        }
        return DB::transaction(function () use ($payment, $reason) {
            $tenancy = $this->lockAccount($this->tenancyOf($payment));
            if ($payment->entry_type === 'refund') {
                $this->restoreRefundedCredit($payment);
            } elseif ($payment->entry_type === 'payment' || $payment->entry_type === 'credit' || $payment->entry_type === 'deposit_release') {
                $this->guardCreditStillAvailable($payment, $tenancy);
                $live = TenantInvoicePaymentAllocation::query()->where('payment_id', $payment->id)->whereNull('reversed_at')->get(['id', 'invoice_id']);
                $invoiceIds = $live->pluck('invoice_id')->unique();
                TenantInvoicePaymentAllocation::query()->whereIn('id', $live->pluck('id'))->update(['reversed_at' => now()]);
                app(CommissionLedgerService::class)->reverseAllocations($live->pluck('id'), 'Payment reversed: ' . $reason);
                TenantInvoice::query()->whereIn('id', $invoiceIds)->get()->each(fn (TenantInvoice $i) => $this->syncInvoice($i));
            }
            $payment->update([
                'payment_status' => TenantPayment::REVERSED,
                'unallocated_amount' => 0,
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
                'reversal_reason' => $reason,
            ]);
            $this->syncDeposit($tenancy);
            $this->applyCredit($tenancy);
            if ($payment->entry_type === 'payment') {
                app(NotificationCenter::class)->paymentReversed($payment, $tenancy, $reason);
            }
            return $payment->fresh();
        });
    }

    /** Money paid back to the tenant out of credit, or out of the deposit held. */
    public function refund(TenantManagement $tenancy, float $amount, string $from, array $data = []): TenantPayment
    {
        $amount = round($amount, 2);
        return DB::transaction(function () use ($tenancy, $amount, $from, $data) {
            $current = $this->lockAccount($tenancy);
            if ($from === 'deposit') {
                if ($amount > $this->depositHeld($current) + 0.001) {
                    throw ValidationException::withMessages(['amount' => 'The refund is more than the deposit held (' . number_format($this->depositHeld($current), 2) . ').']);
                }
                $this->releaseDeposit($current, $amount, $data['payment_date'] ?? now()->toDateString(), 'Deposit returned for refund', false, 'returned');
            }
            if ($amount <= 0 || $amount > $this->credit($current) + 0.001) {
                throw ValidationException::withMessages(['amount' => 'The refund is more than the credit available (' . number_format($this->credit($current), 2) . ').']);
            }
            $drawn = $this->drawCredit($current, $amount);
            return TenantPayment::create([
                'tenant_id' => $current->id,
                'entry_type' => 'refund',
                'recorded_by' => auth()->id(),
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'payment_period' => Carbon::parse($data['payment_date'] ?? now())->format('Y-m'),
                'amount_paid' => $amount,
                'total_amount' => $amount,
                'unallocated_amount' => 0,
                'payment_method' => $data['payment_method'] ?? 'other',
                'transaction_reference' => $data['transaction_reference'] ?? null,
                'payment_status' => TenantPayment::CONFIRMED,
                'confirmed_at' => now(),
                'confirmed_by' => auth()->id(),
                'payment_notes' => $data['payment_notes'] ?? ($from === 'deposit' ? 'Deposit refund' : 'Credit refund'),
                'allocation_data' => ['from' => $from, 'drawn' => $drawn],
            ]);
        });
    }

    /** A non-cash credit (e.g. unused rent returned at a transfer). */
    public function addCredit(TenantManagement $tenancy, float $amount, string $date, string $note): ?TenantPayment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        return DB::transaction(function () use ($tenancy, $amount, $date, $note) {
            $current = $this->lockAccount($tenancy);
            $entry = $this->entry($current, 'credit', $amount, $date, $note);
            $this->allocate($entry, $current, null, Carbon::parse($date));
            return $entry->fresh();
        });
    }

    /**
     * Move held deposit onto the account: it settles unpaid invoices (rent settled this way counts
     * as collected on that date), and anything left is credit (refundable).
     */
    public function releaseDeposit(TenantManagement $tenancy, float $amount, string $date, string $note, bool $allocate = true, string $purpose = 'applied'): ?TenantPayment
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return null;
        }
        return DB::transaction(function () use ($tenancy, $amount, $date, $note, $allocate, $purpose) {
            $current = $this->lockAccount($tenancy);
            if ($amount > $this->depositHeld($current) + 0.001) {
                throw ValidationException::withMessages(['amount' => 'Only ' . number_format($this->depositHeld($current), 2) . ' of deposit is held.']);
            }
            $entry = $this->entry($current, 'deposit_release', $amount, $date, $note);
            // "applied" = used for what was owed; "returned" = given back to the tenant as credit (to refund).
            $entry->update(['allocation_data' => ['purpose' => $purpose]]);
            if ($allocate) {
                $this->allocate($entry, $current, null, Carbon::parse($date), excludeDeposit: true);
            }
            $this->syncDeposit($current);
            return $entry->fresh();
        });
    }

    /**
     * Move-out settlement:
     *  1. damages are charged;
     *  2. rent and service charges for periods that start after the leaving date are voided (anything
     *     already paid on them goes back to credit);
     *  3. the deposit held pays what is still owed (rent settled this way counts as collected);
     *  4. the rest of the deposit is returned to the tenant's credit;
     *  5. the refund (all credit by default, or the amount given) is paid out.
     * With $dryRun the figures are worked out and everything is rolled back (used for the preview).
     */
    public function settleMoveOut(TenantManagement $tenancy, string $date, float $damages = 0, ?string $damageNote = null, ?float $refund = null, array $refundData = [], bool $dryRun = false): array
    {
        $run = function () use ($tenancy, $date, $damages, $damageNote, $refund, $refundData) {
            $current = $this->lockAccount($tenancy);
            $charges = app(TenantChargesService::class);
            if ($damages > 0) {
                $charges->charge($current, 'adjustment', round($damages, 2), $damageNote ?: 'Damages at move-out', Carbon::parse($date));
            }
            TenantInvoice::query()->whereIn('tenancy_id', $this->chainIds($current))->whereIn('kind', ['rent', 'service_charge'])
                ->whereDate('billing_period_start', '>', $date)->where('status', '!=', TenantInvoice::VOID)->get()
                ->each(fn (TenantInvoice $i) => $this->void($i, 'Tenancy ended before this period'));

            $held = $this->depositHeld($current);
            $owed = $this->outstanding($current);
            $applied = round(min($held, $owed), 2);
            if ($applied > 0) {
                $this->releaseDeposit($current, $applied, $date, 'Deposit applied at move-out', true, 'applied');
            }
            $returned = round($this->depositHeld($current), 2);
            if ($returned > 0) {
                $this->releaseDeposit($current, $returned, $date, 'Deposit returned at move-out', true, 'returned');
            }
            $refundable = $this->credit($current);
            $refundNow = $refund === null ? $refundable : round($refund, 2);
            if ($refundNow > $refundable + 0.001) {
                throw ValidationException::withMessages(['security_deposit_refund' => 'Only ' . number_format($refundable, 2) . ' is owed back to the tenant.']);
            }
            if ($refundNow > 0) {
                $this->refund($current, $refundNow, 'credit', $refundData + ['payment_date' => $date, 'payment_notes' => 'Refund at move-out']);
            }
            return [
                'currency' => $current->currency ?: default_currency(),
                'deposit_held' => $held,
                'damages' => round($damages, 2),
                'owed_before_deposit' => $owed,
                'deposit_applied' => $applied,
                'deposit_returned' => $returned,
                'refundable' => $refundable,
                'refund' => $refundNow,
                'left_to_refund' => $this->credit($current),
                'still_owed' => $this->outstanding($current),
            ];
        };

        if (!$dryRun) {
            return DB::transaction($run);
        }
        DB::beginTransaction();
        try {
            return $run();
        } finally {
            DB::rollBack();
        }
    }

    /** Use any credit on the account to settle unpaid invoices (called whenever an invoice is issued). */
    public function applyCredit(TenantManagement $tenancy, ?Carbon $on = null): void
    {
        DB::transaction(function () use ($tenancy, $on) {
            $current = $this->lockAccount($tenancy);
            $entries = TenantPayment::query()->whereIn('tenant_id', $this->chainIds($current))
                ->whereIn('entry_type', self::CREDITING)->where('payment_status', TenantPayment::CONFIRMED)
                ->where('unallocated_amount', '>', 0)->orderBy('payment_date')->orderBy('id')->lockForUpdate()->get();
            foreach ($entries as $entry) {
                $this->allocate($entry, $current, null, $on ?? now()->startOfDay(), excludeDeposit: $entry->entry_type === 'deposit_release');
            }
        });
    }

    /** Waive a late fee or void a charge raised in error. */
    public function void(TenantInvoice $invoice, string $reason): TenantInvoice
    {
        return DB::transaction(function () use ($invoice, $reason) {
            $tenancy = $this->lockAccount($this->free(fn () => TenantManagement::findOrFail($invoice->tenancy_id)));
            $invoice = TenantInvoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($invoice->isVoid()) {
                return $invoice;
            }
            // Money already applied to this charge goes back to its entries as credit.
            $allocations = TenantInvoicePaymentAllocation::query()->where('invoice_id', $invoice->id)->whereNull('reversed_at')->get();
            foreach ($allocations as $allocation) {
                $allocation->update(['reversed_at' => now()]);
                if ($allocation->payment_id) {
                    TenantPayment::query()->whereKey($allocation->payment_id)->increment('unallocated_amount', (float) $allocation->allocated_amount);
                }
            }
            app(CommissionLedgerService::class)->reverseAllocations($allocations->pluck('id'), 'Charge voided: ' . $reason);
            $invoice->update([
                'status' => TenantInvoice::VOID,
                'paid_amount' => 0,
                'paid_at' => null,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $reason,
            ]);
            $this->syncDeposit($tenancy);
            $this->applyCredit($tenancy);
            return $invoice->fresh();
        });
    }

    // ─── Statement ────────────────────────────────────────────────────────

    /**
     * Every charge and every entry in date order with a running balance (what the tenant owes).
     * Deposit invoices and deposit payments appear like any other charge; "deposit applied" lowers the balance.
     */
    public function statement(TenantManagement $tenancy, ?string $from = null, ?string $to = null): array
    {
        $ids = $this->chainIds($tenancy);
        $lines = collect();
        TenantInvoice::query()->whereIn('tenancy_id', $ids)->get()->each(function (TenantInvoice $i) use ($lines) {
            // A charge is dated when it applies: the period start (or due date when it has no period),
            // unless it was entered earlier than that. Back-rent entered today then sits in its own month.
            $applies = ($i->billing_period_start ?? $i->due_date)?->toDateString();
            $entered = $i->created_at?->toDateString();
            $date = $applies && $entered ? min($applies, $entered) : ($applies ?? $entered);
            $lines->push(['date' => $date, 'sort' => $date . 'a' . sprintf('%010d', $i->id), 'type' => 'charge', 'reference' => $i->invoice_number,
                'description' => $i->kind_label . ($i->billing_period_start ? ' · ' . $i->billing_period_start->format('j M Y') . ' – ' . $i->billing_period_end?->format('j M Y') : (in_array($i->kind, ['adjustment', 'late_fee'], true) && !empty($i->line_items[0]['description']) ? ' · ' . $i->line_items[0]['description'] : '')) . ($i->void_reason ? ' · ' . $i->display_status . ': ' . $i->void_reason : ''),
                'debit' => (float) $i->amount, 'credit' => 0.0, 'invoice_id' => $i->id]);
            if ($i->isVoid()) {
                $voidDate = $i->voided_at?->toDateString() ?? $date;
                $lines->push(['date' => $voidDate, 'sort' => $voidDate . 'b' . sprintf('%010d', $i->id), 'type' => 'void', 'reference' => $i->invoice_number,
                    'description' => ($i->kind === 'late_fee' ? 'Late fee waived' : 'Charge voided') . ($i->void_reason ? ': ' . $i->void_reason : ''),
                    'debit' => 0.0, 'credit' => (float) $i->amount]);
            }
        });
        TenantPayment::query()->whereIn('tenant_id', $ids)->where('payment_status', '!=', TenantPayment::PENDING)->get()->each(function (TenantPayment $p) use ($lines) {
            $date = $p->payment_date->toDateString();
            $label = $p->entry_type === 'deposit_release' && ($p->allocation_data['purpose'] ?? null) === 'returned' ? 'Deposit returned to the tenant' : (TenantPayment::ENTRY_LABELS[$p->entry_type] ?? 'Payment');
            $desc = $label . (in_array($p->entry_type, ['payment', 'refund'], true) ? ' · ' . str_replace('_', ' ', $p->payment_method) : ($p->payment_notes && $p->entry_type !== 'deposit_release' ? ' · ' . $p->payment_notes : ''));
            $isRefund = $p->entry_type === 'refund';
            $lines->push(['date' => $date, 'sort' => $date . 'c' . sprintf('%010d', $p->id), 'type' => $p->entry_type, 'reference' => $p->transaction_reference,
                'description' => $desc, 'debit' => $isRefund ? (float) $p->amount_paid : 0.0, 'credit' => $isRefund ? 0.0 : (float) $p->amount_paid, 'payment_id' => $p->id]);
            if ($p->payment_status === TenantPayment::REVERSED) {
                $rdate = $p->reversed_at?->toDateString() ?? $date;
                $lines->push(['date' => $rdate, 'sort' => $rdate . 'd' . sprintf('%010d', $p->id), 'type' => 'reversal', 'reference' => $p->transaction_reference,
                    'description' => 'Reversed: ' . $label . ($p->reversal_reason ? ' · ' . $p->reversal_reason : ''),
                    'debit' => $isRefund ? 0.0 : (float) $p->amount_paid, 'credit' => $isRefund ? (float) $p->amount_paid : 0.0]);
            }
        });

        $sorted = $lines->sortBy('sort')->values();
        $running = 0.0;
        $opening = 0.0;
        $rows = [];
        foreach ($sorted as $line) {
            $running = round($running + $line['debit'] - $line['credit'], 2);
            if ($from && $line['date'] < $from) {
                $opening = $running;
                continue;
            }
            if ($to && $line['date'] > $to) {
                continue;
            }
            $line['balance'] = $running;
            unset($line['sort']);
            $rows[] = $line;
        }
        return ['opening_balance' => $opening, 'lines' => $rows, 'closing_balance' => $rows ? end($rows)['balance'] : $opening];
    }

    // ─── Internals ────────────────────────────────────────────────────────

    /** Apply an entry's unused amount to open invoices, oldest first (a chosen invoice first). */
    public function allocate(TenantPayment $entry, TenantManagement $tenancy, ?int $firstInvoiceId, Carbon $on, bool $excludeDeposit = false): void
    {
        $remaining = round((float) $entry->fresh()->unallocated_amount, 2);
        if ($remaining <= 0) {
            return;
        }
        $open = $this->openInvoices($tenancy, true);
        if ($excludeDeposit) {
            $open = $open->reject(fn (TenantInvoice $i) => $i->kind === 'deposit')->values();
        }
        if ($firstInvoiceId) {
            $open = $open->sortBy(fn (TenantInvoice $i) => $i->id === $firstInvoiceId ? 0 : 1, SORT_NUMERIC, false)->values();
        }
        $source = match ($entry->entry_type) { 'credit' => 'credit', 'deposit_release' => 'deposit', default => 'payment' };
        $first = null;
        foreach ($open as $invoice) {
            if ($remaining <= 0) {
                break;
            }
            $before = $invoice->balance;
            if ($before <= 0) {
                continue;
            }
            $take = round(min($before, $remaining), 2);
            [$agentId, $overrideId] = $this->agentsFor($invoice->tenancy_id);
            $allocation = TenantInvoicePaymentAllocation::create([
                'invoice_id' => $invoice->id,
                'payment_id' => $entry->id,
                'tenant_id' => $invoice->tenancy_id,
                'allocated_amount' => $take,
                'invoice_balance_before' => $before,
                'invoice_balance_after' => round($before - $take, 2),
                'allocation_type' => 'payment',
                'source' => $source,
                'invoice_kind' => $invoice->kind,
                'agent_id' => $agentId,
                'override_agent_id' => $overrideId,
                'recorded_by' => auth()->id(),
                'allocation_date' => $on->toDateString(),
                'notes' => $invoice->kind_label . ' ' . $invoice->invoice_number,
            ]);
            // Commission is recorded with the money, in the same transaction.
            app(CommissionLedgerService::class)->recordAllocation($allocation);
            $remaining = round($remaining - $take, 2);
            $this->syncInvoice($invoice);
            $first ??= $invoice->id;
        }
        $entry->update(['unallocated_amount' => max(0, $remaining)] + ($entry->invoice_id || !$first ? [] : ['invoice_id' => $first]));
        $this->syncDeposit($tenancy);
    }

    /** Store an invoice's paid amount and status from its live allocations. */
    public function syncInvoice(TenantInvoice $invoice): void
    {
        $invoice = $invoice->fresh();
        if ($invoice->isVoid()) {
            return;
        }
        $paid = round((float) TenantInvoicePaymentAllocation::query()->where('invoice_id', $invoice->id)->whereNull('reversed_at')->sum('allocated_amount'), 2);
        $status = $paid >= (float) $invoice->amount - 0.005 ? TenantInvoice::PAID : ($paid > 0 ? TenantInvoice::PARTIAL : TenantInvoice::UNPAID);
        $invoice->update(['paid_amount' => $paid, 'status' => $status, 'paid_at' => $status === TenantInvoice::PAID ? ($invoice->paid_at ?? now()) : null]);

        if ($invoice->billing_id && $invoice->kind === 'rent') {
            $tenancyBillingId = DB::table('tenant_billings')->where('id', $invoice->billing_id)->value('tenancy_billing_id');
            if ($tenancyBillingId) {
                TenantTenancyBilling::query()->whereKey($tenancyBillingId)->update([
                    'paid_amount' => $paid,
                    'status' => $status === TenantInvoice::PAID ? 'Paid' : ($paid > 0 ? 'Partially paid' : 'Invoiced'),
                    'paid_date' => $status === TenantInvoice::PAID ? now()->toDateString() : null,
                ]);
            }
        }
    }

    /** Keep the tenancy's deposit-paid figure in line with what is held. */
    public function syncDeposit(TenantManagement $tenancy): void
    {
        $current = $this->currentTenancy($tenancy);
        $held = $this->depositHeld($current);
        $last = TenantInvoicePaymentAllocation::query()
            ->whereIn('invoice_id', TenantInvoice::query()->whereIn('tenancy_id', $this->chainIds($current))->where('kind', 'deposit')->select('id'))
            ->whereNull('reversed_at')->max('allocation_date');
        $this->free(fn () => TenantManagement::query()->whereKey($current->id)->update([
            'deposit_paid' => $held,
            'deposit_paid_date' => $held > 0 && $last ? Carbon::parse($last)->toDateString() : null,
        ]));
    }

    /**
     * The agent credited for money on this tenancy right now, and the lead agent who earns an override:
     * the property's assigned agent, when the tenancy's agent reports to them.
     *
     * @return array{0: ?int, 1: ?int}
     */
    public function agentsFor(int $tenancyId): array
    {
        return $this->free(function () use ($tenancyId) {
            $tenancy = TenantManagement::query()->select('id', 'assigned_agent_id', 'complex_id')->find($tenancyId);
            $agentId = $tenancy?->assigned_agent_id ? (int) $tenancy->assigned_agent_id : null;
            if (!$agentId) {
                return [null, null];
            }
            $leadId = $tenancy->complex_id ? Complex::query()->whereKey($tenancy->complex_id)->value('assigned_agent_id') : null;
            $reportsTo = Agent::query()->whereKey($agentId)->value('reports_to_agent_id');
            $override = ($leadId && (int) $leadId !== $agentId && (int) $reportsTo === (int) $leadId) ? (int) $leadId : null;
            return [$agentId, $override];
        });
    }

    private function entry(TenantManagement $tenancy, string $type, float $amount, string $date, string $note): TenantPayment
    {
        return TenantPayment::create([
            'tenant_id' => $tenancy->id,
            'entry_type' => $type,
            'recorded_by' => auth()->id(),
            'payment_date' => $date,
            'payment_period' => Carbon::parse($date)->format('Y-m'),
            'amount_paid' => $amount,
            'total_amount' => $amount,
            'unallocated_amount' => $amount,
            'payment_method' => 'other',
            'payment_status' => TenantPayment::CONFIRMED,
            'confirmed_at' => now(),
            'confirmed_by' => auth()->id(),
            'payment_notes' => $note,
            'late_fee_paid_now' => false,
        ]);
    }

    /** Take credit from entries, oldest first; returns [payment_id => amount]. */
    private function drawCredit(TenantManagement $tenancy, float $amount): array
    {
        $drawn = [];
        $entries = TenantPayment::query()->whereIn('tenant_id', $this->chainIds($tenancy))
            ->whereIn('entry_type', self::CREDITING)->where('payment_status', TenantPayment::CONFIRMED)
            ->where('unallocated_amount', '>', 0)->orderBy('payment_date')->orderBy('id')->lockForUpdate()->get();
        foreach ($entries as $entry) {
            if ($amount <= 0) {
                break;
            }
            $take = round(min((float) $entry->unallocated_amount, $amount), 2);
            $entry->decrement('unallocated_amount', $take);
            $drawn[$entry->id] = $take;
            $amount = round($amount - $take, 2);
        }
        return $drawn;
    }

    private function restoreRefundedCredit(TenantPayment $refund): void
    {
        foreach ((array) ($refund->allocation_data['drawn'] ?? []) as $paymentId => $amount) {
            TenantPayment::query()->whereKey($paymentId)->increment('unallocated_amount', (float) $amount);
        }
    }

    /** Reversing an entry whose credit was already refunded would leave a hole: reverse the refund first. */
    private function guardCreditStillAvailable(TenantPayment $payment, TenantManagement $tenancy): void
    {
        $refunded = TenantPayment::query()->whereIn('tenant_id', $this->chainIds($tenancy))
            ->where('entry_type', 'refund')->where('payment_status', TenantPayment::CONFIRMED)->get()
            ->sum(fn (TenantPayment $r) => (float) ($r->allocation_data['drawn'][$payment->id] ?? 0));
        if ($refunded > 0) {
            throw ValidationException::withMessages(['payment' => 'Part of this payment was refunded to the tenant. Reverse that refund first.']);
        }
    }

    private function lockAccount(TenantManagement $tenancy): TenantManagement
    {
        $current = $this->currentTenancy($tenancy);
        $this->free(fn () => TenantManagement::query()->whereKey($current->id)->lockForUpdate()->first());
        return $current;
    }

    /** Account figures are business-wide: the assigned-property limit doesn't apply inside this service. */
    private function free(callable $callback): mixed
    {
        return app(PropertyAccess::class)->unrestricted($callback);
    }

    private function tenancyOf(TenantPayment $payment): TenantManagement
    {
        return $this->free(fn () => TenantManagement::findOrFail($payment->tenant_id));
    }
}
