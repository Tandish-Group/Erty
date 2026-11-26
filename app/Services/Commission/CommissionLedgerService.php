<?php

namespace App\Services\Commission;

use App\Services\Access\PropertyAccess;
use App\Services\Agents\AssignmentHistoryService;
use App\Services\NotificationCenter;

use App\Models\CommissionAgreement;
use App\Models\CommissionEntry;
use App\Models\TenantInvoice;
use App\Models\TenantInvoicePaymentAllocation;
use App\Models\TenantManagement;
use Illuminate\Support\Facades\DB;

/**
 * The commission ledger. Entries are written when money is applied to a tenant's bills
 * (TenantAccountService::allocate) and reversed when that money is reversed (reverse, void).
 *
 * For each rent allocation of amount A, using the agreements in force on the collection date and who was
 * responsible for the unit then (assignment_history):
 * - management_fee: the company earns A × its fee rate;
 * - agent: the agent responsible for the unit (unit agent, else the tenancy's agent, else the property's
 *   primary manager) earns A × their rate — only for "% of rent collected"; the other methods are period
 *   entries (CommissionPeriodService);
 * - primary_on_delegated: on a unit delegated to someone else, the primary manager earns either their own
 *   override rate ("separate"), or a split of one pool with the sub-agent ("share of pool") so the same rent
 *   is never paid twice.
 * Agent commission is funded out of the company's fee unless the agreement says "additional cost". With no
 * company fee configured it is recorded as an additional cost and flagged; if fee-funded commission on an
 * allocation exceeds the company's fee there, the entries wait for approval.
 */
class CommissionLedgerService
{
    public function __construct(
        private CommissionAgreementService $agreements,
        private AssignmentHistoryService $history,
        private PropertyAccess $access,
    ) {}

    /** Who was responsible for a tenancy's unit on a date. */
    public function responsibility(object $tenancy, $date): array
    {
        $complexId = $tenancy->complex_id ? (int) $tenancy->complex_id : null;
        [$unitType, $unitId] = $tenancy->commercial_unit_id
            ? ['commercial', (int) $tenancy->commercial_unit_id]
            : ($tenancy->residential_unit_id ? ['residential', (int) $tenancy->residential_unit_id] : [null, null]);

        $primary = $complexId ? $this->history->primaryOn($complexId, $date) : null;
        $unitAgent = $unitId ? $this->history->unitAgentOn($unitType, $unitId, $date) : null;
        $agent = $unitAgent ?: ($tenancy->assigned_agent_id ? (int) $tenancy->assigned_agent_id : null) ?: $primary;

        return [
            'complex_id' => $complexId, 'unit_type' => $unitType, 'unit_id' => $unitId,
            'primary_agent_id' => $primary, 'management_type' => $primary ? 'individual' : 'company', 'agent_id' => $agent,
        ];
    }

    /** Record the commission earned on one allocation (idempotent). */
    public function recordAllocation(TenantInvoicePaymentAllocation $allocation, bool $backfilled = false): int
    {
        if ($allocation->reversed_at || !in_array($allocation->source, ['payment', 'deposit'], true) || (float) $allocation->allocated_amount <= 0) {
            return 0;
        }
        return $this->access->unrestricted(function () use ($allocation, $backfilled) {
            if (CommissionEntry::where('allocation_id', $allocation->id)->where('kind', 'payment')->exists()) {
                return 0;
            }
            $tenancy = TenantManagement::find($allocation->tenant_id);
            if (!$tenancy || !$tenancy->managed_by) {
                return 0;
            }
            $businessId = (int) $tenancy->managed_by;
            $date = $allocation->allocation_date;
            $invoiceKind = (string) $allocation->invoice_kind;
            $amount = round((float) $allocation->allocated_amount, 2);
            $resp = $this->responsibility($tenancy, $date);
            $invoice = $allocation->invoice_id ? TenantInvoice::find($allocation->invoice_id, ['id', 'billing_period_start', 'billing_period_end']) : null;

            $base = [
                'business_id' => $businessId, 'kind' => 'payment', 'complex_id' => $resp['complex_id'], 'unit_type' => $resp['unit_type'], 'unit_id' => $resp['unit_id'],
                'tenancy_id' => $tenancy->id, 'tenant_profile_id' => $tenancy->tenant_profile_id, 'allocation_id' => $allocation->id,
                'payment_id' => $allocation->payment_id, 'invoice_id' => $allocation->invoice_id,
                'rental_period' => $invoice?->billing_period_start ? trim(substr((string) $invoice->billing_period_start, 0, 10) . ' – ' . substr((string) $invoice->billing_period_end, 0, 10)) : null,
                'collected_on' => substr((string) $date, 0, 10), 'primary_agent_id' => $resp['primary_agent_id'], 'management_type' => $resp['management_type'],
                'basis_amount' => $amount, 'currency' => $tenancy->currency ?: default_currency(),
            ];
            $ctx = ['complex_id' => $resp['complex_id'], 'unit_type' => $resp['unit_type'], 'unit_id' => $resp['unit_id']];
            $rows = [];

            // The company's management commission.
            $fee = $this->agreements->resolve($businessId, 'management_fee', $ctx, $date);
            $feeAmount = 0.0;
            $feeUsable = $fee && $fee->method === 'percent_collected' && $fee->appliesTo($invoiceKind);
            if ($feeUsable) {
                $feeAmount = round($amount * $fee->rate / 100, 2);
                $rows[] = $this->row($base, 'company', null, 'management_fee', $fee, $fee->rate, $feeAmount, null);
            }

            // The agents.
            $agentId = $resp['agent_id'];
            $agentRows = [];
            if ($agentId) {
                $primary = $resp['primary_agent_id'];
                $delegated = $primary && $primary !== $agentId;
                $share = $delegated ? $this->agreements->resolve($businessId, 'primary_on_delegated', $ctx + ['agent_id' => $primary], $date) : null;
                $shareUsable = $share && $share->method === 'percent_collected' && $share->appliesTo($invoiceKind);

                if ($shareUsable && $share->sharing === 'share_of_pool') {
                    $subPct = max(0, min(100, (float) $share->sub_share_pct));
                    $subRate = round($share->rate * $subPct / 100, 3);
                    $agentRows[] = $this->row($base, 'agent', $agentId, 'agent', $share, $subRate, round($amount * $subRate / 100, 2), $share->funding, ['pool_rate' => $share->rate, 'sub_share_pct' => $subPct]);
                    $agentRows[] = $this->row($base, 'agent', $primary, 'primary_on_delegated', $share, round($share->rate - $subRate, 3), round($amount * ($share->rate - $subRate) / 100, 2), $share->funding, ['pool_rate' => $share->rate, 'sub_share_pct' => $subPct]);
                } else {
                    $own = $this->agreements->resolve($businessId, 'agent', $ctx + ['agent_id' => $agentId], $date);
                    if ($own && $own->method === 'percent_collected' && $own->appliesTo($invoiceKind)) {
                        $agentRows[] = $this->row($base, 'agent', $agentId, 'agent', $own, $own->rate, round($amount * $own->rate / 100, 2), $own->funding);
                    }
                    if ($shareUsable) {
                        $agentRows[] = $this->row($base, 'agent', $primary, 'primary_on_delegated', $share, $share->rate, round($amount * $share->rate / 100, 2), $share->funding);
                    }
                }
            }

            // Funding: out of the company's fee by default; flag what cannot be.
            $fromFee = 0.0;
            foreach ($agentRows as &$r) {
                if ($r['funding'] === 'company_fee' && !$feeUsable) {
                    $r['funding'] = 'additional_cost';
                    $r['breakdown']['note'] = 'No company fee is set up for this rent, so this is recorded as an additional cost.';
                }
                if ($r['funding'] === 'company_fee') {
                    $fromFee += $r['amount'];
                }
            }
            unset($r);
            if ($fromFee > $feeAmount + 0.005) {
                foreach ($agentRows as &$r) {
                    if ($r['funding'] === 'company_fee') {
                        $r['status'] = CommissionEntry::PENDING;
                        $r['breakdown']['note'] = 'Agent commission from the company\'s fee (' . number_format($fromFee, 2) . ') is more than the fee on this rent (' . number_format($feeAmount, 2) . '). Approve to pay it.';
                    }
                }
                unset($r);
            }

            $rows = array_merge($rows, array_filter($agentRows, fn ($r) => abs($r['amount']) >= 0.005 || $r['rate'] > 0));
            foreach ($rows as &$r) {
                if ($backfilled) {
                    $r['breakdown']['backfilled'] = true;
                }
                $r['breakdown'] = $r['breakdown'] ? json_encode($r['breakdown']) : null;
                $r['created_at'] = $r['updated_at'] = now();
            }
            unset($r);
            if ($rows) {
                DB::table('commission_entries')->insertOrIgnore($rows);
                $waiting = array_filter($rows, fn ($r) => ($r['status'] ?? null) === CommissionEntry::PENDING);
                if ($waiting && !$backfilled) {
                    app(NotificationCenter::class)->commissionAwaitingApproval((int) reset($waiting)['business_id'], count($waiting),
                        (float) array_sum(array_column($waiting, 'amount')), 'agent commission on a payment is more than the company\'s fee');
                }
            }
            return count($rows);
        });
    }

    /**
     * Money taken back off bills (payment reversed, charge voided): each live entry gets a negative reversal
     * entry. Unpaid commission is cancelled; commission already paid out stays and the reversal is deducted
     * from the next payout (a clawback).
     */
    public function reverseAllocations(iterable $allocationIds, ?string $reason = null): int
    {
        $ids = collect($allocationIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return 0;
        }
        return $this->access->unrestricted(function () use ($ids, $reason) {
            $count = 0;
            $entries = CommissionEntry::whereIn('allocation_id', $ids)->where('kind', 'payment')->where('status', '!=', CommissionEntry::REVERSED)->get();
            foreach ($entries as $entry) {
                if (CommissionEntry::where('reverses_entry_id', $entry->id)->exists()) {
                    continue;
                }
                $paid = $entry->payout_id || $entry->status === CommissionEntry::PAID;
                $copy = collect($entry->getAttributes())->except(['id', 'created_at', 'updated_at', 'payout_id', 'approved_by', 'approved_at', 'status', 'kind', 'amount', 'basis_amount', 'breakdown', 'reverses_entry_id'])->all();
                CommissionEntry::create($copy + [
                    'kind' => 'reversal', 'amount' => -$entry->amount, 'basis_amount' => -$entry->basis_amount, 'reverses_entry_id' => $entry->id,
                    'status' => $paid ? CommissionEntry::EARNED : CommissionEntry::REVERSED,
                    'collected_on' => now()->toDateString(),
                    'breakdown' => ['reason' => $reason, 'original_collected_on' => optional($entry->collected_on)->toDateString(), 'clawback' => $paid],
                ]);
                if (!$paid) {
                    $entry->forceFill(['status' => CommissionEntry::REVERSED])->save();
                }
                $count++;
            }
            return $count;
        });
    }

    /**
     * Sales commission on one buyer payment (idempotent): the sale's agent earns, under the "sale" agreement in
     * force on the payment date, rate × payment ("% of the sale price") or the flat amount × payment ÷ price
     * ("flat per house sold"), so the commission builds up as the buyer pays. Paid by the business.
     */
    public function recordSalePayment(\App\Models\PropertySalePayment $payment): int
    {
        if ($payment->status !== 'confirmed' || $payment->amount <= 0) {
            return 0;
        }
        return $this->access->unrestricted(function () use ($payment) {
            if (CommissionEntry::where('sale_payment_id', $payment->id)->where('kind', 'payment')->exists()) {
                return 0;
            }
            $sale = \App\Models\PropertySale::withoutGlobalScopes()->find($payment->sale_id);
            if (!$sale || !$sale->agent_id || $sale->price <= 0) {
                return 0;
            }
            $date = $payment->paid_on;
            $ctx = ['complex_id' => (int) $sale->complex_id, 'unit_type' => 'residential', 'unit_id' => (int) $sale->residential_unit_id, 'agent_id' => (int) $sale->agent_id];
            $agreement = $this->agreements->resolve((int) $sale->business_id, 'sale', $ctx, $date);
            if (!$agreement) {
                return 0;
            }
            $amount = round((float) $payment->amount, 2);
            [$rate, $earned] = $agreement->method === 'flat_per_sale'
                ? [0.0, round((float) $agreement->flat_amount * $amount / $sale->price, 2)]
                : [(float) $agreement->rate, round($amount * $agreement->rate / 100, 2)];
            if (abs($earned) < 0.005) {
                return 0;
            }
            $primary = $this->history->primaryOn((int) $sale->complex_id, $date);
            // forceCreate: every column is set here on purpose (the guarded-column list is cached per process).
            CommissionEntry::forceCreate([
                'business_id' => (int) $sale->business_id, 'kind' => 'payment', 'beneficiary_type' => 'agent', 'agent_id' => (int) $sale->agent_id,
                'entitlement' => 'sale', 'funding' => 'additional_cost', 'complex_id' => (int) $sale->complex_id,
                'unit_type' => 'residential', 'unit_id' => (int) $sale->residential_unit_id, 'sale_id' => $sale->id, 'sale_payment_id' => $payment->id,
                'collected_on' => $date->toDateString(), 'primary_agent_id' => $primary, 'management_type' => $primary ? 'individual' : 'company',
                'agreement_id' => $agreement->id, 'method' => $agreement->method, 'rate' => $rate, 'basis_amount' => $amount, 'amount' => $earned,
                'currency' => $sale->currency ?: default_currency(), 'status' => CommissionEntry::EARNED,
                'breakdown' => ['agreement' => $agreement->describe(), 'scope' => $agreement->scope, 'sale' => $sale->reference, 'receipt' => $payment->receipt_number, 'sale_price' => $sale->price],
            ]);
            return 1;
        });
    }

    /** A buyer payment reversed: its sales commission is reversed like rent commission (cancelled, or clawed back once paid out). */
    public function reverseSalePayment(\App\Models\PropertySalePayment $payment, ?string $reason = null): int
    {
        return $this->access->unrestricted(fn () => $this->reverseEntries(
            CommissionEntry::where('sale_payment_id', $payment->id)->where('kind', 'payment')->where('status', '!=', CommissionEntry::REVERSED)->get(), $reason));
    }

    private function reverseEntries($entries, ?string $reason): int
    {
        $count = 0;
        foreach ($entries as $entry) {
            if (CommissionEntry::where('reverses_entry_id', $entry->id)->exists()) {
                continue;
            }
            $paid = $entry->payout_id || $entry->status === CommissionEntry::PAID;
            $copy = collect($entry->getAttributes())->except(['id', 'created_at', 'updated_at', 'payout_id', 'approved_by', 'approved_at', 'status', 'kind', 'amount', 'basis_amount', 'breakdown', 'reverses_entry_id', 'sale_payment_id'])->all();
            CommissionEntry::forceCreate($copy + [
                'kind' => 'reversal', 'amount' => -$entry->amount, 'basis_amount' => -$entry->basis_amount, 'reverses_entry_id' => $entry->id,
                'status' => $paid ? CommissionEntry::EARNED : CommissionEntry::REVERSED,
                'collected_on' => now()->toDateString(),
                'breakdown' => ['reason' => $reason, 'original_collected_on' => optional($entry->collected_on)->toDateString(), 'clawback' => $paid],
            ]);
            if (!$paid) {
                $entry->forceFill(['status' => CommissionEntry::REVERSED])->save();
            }
            $count++;
        }
        return $count;
    }

    /** Approve entries waiting for approval (exceeding the fee, or period commission). */
    public function approve(array $entryIds, int $businessId): int
    {
        return CommissionEntry::where('business_id', $businessId)->whereIn('id', $entryIds)->where('status', CommissionEntry::PENDING)
            ->update(['status' => CommissionEntry::APPROVED, 'approved_by' => auth()->id(), 'approved_at' => now()]);
    }

    /** Record entries for allocations that have none yet (installation, or after a gap). */
    public function backfill(int $businessId): int
    {
        $count = 0;
        TenantInvoicePaymentAllocation::withoutGlobalScopes()
            ->whereIn('tenant_id', DB::table('tenant_management')->where('managed_by', $businessId)->select('id'))
            ->whereNull('reversed_at')->whereIn('source', ['payment', 'deposit'])
            ->whereNotExists(fn ($q) => $q->from('commission_entries')->whereColumn('commission_entries.allocation_id', 'tenant_invoice_payment_allocations.id'))
            ->orderBy('allocation_date')->orderBy('id')
            ->chunkById(200, function ($allocations) use (&$count) {
                foreach ($allocations as $allocation) {
                    $count += $this->recordAllocation($allocation, true);
                }
            });
        return $count;
    }

    private function row(array $base, string $beneficiary, ?int $agentId, string $entitlement, CommissionAgreement $agreement, float $rate, float $amount, ?string $funding, array $breakdown = []): array
    {
        return $base + [
            'beneficiary_type' => $beneficiary, 'agent_id' => $agentId, 'entitlement' => $entitlement, 'funding' => $funding,
            'agreement_id' => $agreement->id, 'method' => $agreement->method, 'rate' => $rate, 'amount' => $amount,
            'breakdown' => $breakdown + ['agreement' => $agreement->describe(), 'scope' => $agreement->scope],
            'status' => CommissionEntry::EARNED,
        ];
    }
}
