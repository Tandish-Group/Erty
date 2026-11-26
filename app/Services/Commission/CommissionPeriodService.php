<?php

namespace App\Services\Commission;

use App\Services\Access\PropertyAccess;
use App\Services\NotificationCenter;

use App\Models\CommissionAgreement;
use App\Models\CommissionEntry;
use App\Models\ComplexExpense;
use App\Models\TenantInvoicePaymentAllocation;
use App\Models\TenantManagement;
use Illuminate\Support\Facades\DB;

/**
 * Commission worked out per period: % of rent less property expenses, a flat amount per paying tenant, and
 * tiered rates by rent collected. Each period's collections are grouped by the agreement that applies to them
 * (same precedence and responsibility as payment entries), then one pending entry per agreement and
 * beneficiary is written for approval. Recalculating replaces pending entries; once approved, later changes
 * (for example a reversed payment) become an adjustment entry instead of editing the approved one.
 */
class CommissionPeriodService
{
    public function __construct(
        private CommissionAgreementService $agreements,
        private CommissionLedgerService $ledger,
        private PropertyAccess $access,
    ) {}

    /** @return array{created: int, updated: int, adjustments: int, locked: int} */
    public function calculate(int $businessId, string $from, string $to): array
    {
        return $this->access->unrestricted(fn () => DB::transaction(function () use ($businessId, $from, $to) {
            $buckets = $this->buckets($businessId, $from, $to);
            $result = ['created' => 0, 'updated' => 0, 'adjustments' => 0, 'locked' => 0];
            $seen = [];

            foreach ($buckets as $key => $bucket) {
                /** @var CommissionAgreement $agreement */
                $agreement = $bucket['agreement'];
                [$amount, $breakdown] = $this->amountFor($agreement, $bucket, $from, $to);
                $seen[] = $key;
                $match = fn ($q) => $q->where('business_id', $businessId)->where('agreement_id', $agreement->id)
                    ->where('beneficiary_type', $bucket['beneficiary'])->where('entitlement', $bucket['entitlement'])
                    ->whereDate('period_from', $from)->whereDate('period_to', $to)
                    ->when($bucket['agent_id'], fn ($w) => $w->where('agent_id', $bucket['agent_id']), fn ($w) => $w->whereNull('agent_id'));

                $existing = CommissionEntry::query()->where('kind', 'period')->tap($match)->first();
                $fields = [
                    'business_id' => $businessId, 'kind' => 'period', 'beneficiary_type' => $bucket['beneficiary'], 'agent_id' => $bucket['agent_id'],
                    'entitlement' => $bucket['entitlement'], 'funding' => $bucket['beneficiary'] === 'agent' ? $agreement->funding : null,
                    'complex_id' => $bucket['complex_ids'] === [] ? null : (count($bucket['complex_ids']) === 1 ? $bucket['complex_ids'][0] : null),
                    'agreement_id' => $agreement->id, 'method' => $agreement->method, 'rate' => $breakdown['rate'] ?? $agreement->rate,
                    'basis_amount' => $bucket['basis'], 'amount' => $amount, 'currency' => $bucket['currency'],
                    'breakdown' => $breakdown, 'period_from' => $from, 'period_to' => $to,
                ];

                if (!$existing) {
                    if (abs($amount) >= 0.005) {
                        CommissionEntry::create($fields + ['status' => CommissionEntry::PENDING]);
                        $result['created']++;
                    }
                } elseif ($existing->status === CommissionEntry::PENDING) {
                    $existing->update($fields);
                    $result['updated']++;
                } else {
                    // Approved or paid: record the difference as an adjustment awaiting approval.
                    $result['locked']++;
                    $settled = (float) $existing->amount + (float) CommissionEntry::query()->where('kind', 'adjustment')->tap($match)
                        ->where('status', '!=', CommissionEntry::PENDING)->sum('amount');
                    $delta = round($amount - $settled, 2);
                    $pending = CommissionEntry::query()->where('kind', 'adjustment')->tap($match)->where('status', CommissionEntry::PENDING)->first();
                    if (abs($delta) < 0.005) {
                        $pending?->delete();
                    } else {
                        $adjustment = ['kind' => 'adjustment', 'amount' => $delta, 'basis_amount' => 0, 'reverses_entry_id' => $existing->id,
                            'breakdown' => $breakdown + ['adjusts' => $existing->id, 'previously' => $settled]] + $fields;
                        $adjustment['kind'] = 'adjustment';
                        $adjustment['amount'] = $delta;
                        $pending ? $pending->update($adjustment) : CommissionEntry::create($adjustment + ['status' => CommissionEntry::PENDING]);
                        $result['adjustments']++;
                    }
                }
            }

            // Pending period entries that no longer apply (for example after a reversal) are removed.
            CommissionEntry::where('business_id', $businessId)->where('kind', 'period')->where('status', CommissionEntry::PENDING)
                ->whereDate('period_from', $from)->whereDate('period_to', $to)->get()
                ->each(function (CommissionEntry $entry) use ($seen) {
                    $key = $entry->agreement_id . '|' . $entry->beneficiary_type . '|' . $entry->entitlement . '|' . ($entry->agent_id ?: 0);
                    if (!in_array($key, $seen, true)) {
                        $entry->delete();
                    }
                });

            if ($result['created'] + $result['updated'] + $result['adjustments'] > 0) {
                $waiting = CommissionEntry::where('business_id', $businessId)->whereIn('kind', ['period', 'adjustment'])->where('status', CommissionEntry::PENDING)
                    ->whereDate('period_from', $from)->whereDate('period_to', $to);
                app(NotificationCenter::class)->commissionAwaitingApproval($businessId, (clone $waiting)->count(), (float) (clone $waiting)->sum('amount'),
                    'period commission for ' . \Carbon\Carbon::parse($from)->format('M Y'));
            }
            return $result;
        }));
    }

    /** Collections in the period, grouped by the period-based agreement and beneficiary that apply. */
    private function buckets(int $businessId, string $from, string $to): array
    {
        $buckets = [];
        $add = function (CommissionAgreement $agreement, string $beneficiary, ?int $agentId, string $entitlement, object $allocation, object $tenancy, ?int $complexId) use (&$buckets) {
            $key = $agreement->id . '|' . $beneficiary . '|' . $entitlement . '|' . ($agentId ?: 0);
            $buckets[$key] ??= ['agreement' => $agreement, 'beneficiary' => $beneficiary, 'agent_id' => $agentId, 'entitlement' => $entitlement,
                'basis' => 0.0, 'tenancies' => [], 'complex_ids' => [], 'allocations' => 0, 'currency' => $tenancy->currency ?: default_currency()];
            $buckets[$key]['basis'] = round($buckets[$key]['basis'] + (float) $allocation->allocated_amount, 2);
            $buckets[$key]['tenancies'][$tenancy->id] = true;
            if ($complexId) $buckets[$key]['complex_ids'][$complexId] = $complexId;
            $buckets[$key]['allocations']++;
        };

        $allocations = TenantInvoicePaymentAllocation::withoutGlobalScopes()
            ->whereIn('tenant_id', DB::table('tenant_management')->where('managed_by', $businessId)->select('id'))
            ->whereNull('reversed_at')->whereIn('source', ['payment', 'deposit'])
            ->whereBetween('allocation_date', [$from, $to . ' 23:59:59'])->orderBy('id')->get();
        $tenancies = TenantManagement::whereIn('id', $allocations->pluck('tenant_id')->unique())->get()->keyBy('id');

        foreach ($allocations as $allocation) {
            $tenancy = $tenancies->get($allocation->tenant_id);
            if (!$tenancy) continue;
            $date = $allocation->allocation_date;
            $kind = (string) $allocation->invoice_kind;
            $resp = $this->ledger->responsibility($tenancy, $date);
            $ctx = ['complex_id' => $resp['complex_id'], 'unit_type' => $resp['unit_type'], 'unit_id' => $resp['unit_id']];

            $fee = $this->agreements->resolve($businessId, 'management_fee', $ctx, $date);
            if ($fee?->isPeriodBased() && $fee->appliesTo($kind)) {
                $add($fee, 'company', null, 'management_fee', $allocation, $tenancy, $resp['complex_id']);
            }
            if ($agentId = $resp['agent_id']) {
                $primary = $resp['primary_agent_id'];
                if ($primary && $primary !== $agentId) {
                    $share = $this->agreements->resolve($businessId, 'primary_on_delegated', $ctx + ['agent_id' => $primary], $date);
                    if ($share?->isPeriodBased() && $share->appliesTo($kind)) {
                        $add($share, 'agent', $primary, 'primary_on_delegated', $allocation, $tenancy, $resp['complex_id']);
                    }
                    if ($share && $share->sharing === 'share_of_pool') continue; // the pool replaces the sub-agent's own terms
                }
                $own = $this->agreements->resolve($businessId, 'agent', $ctx + ['agent_id' => $agentId], $date);
                if ($own?->isPeriodBased() && $own->appliesTo($kind)) {
                    $add($own, 'agent', $agentId, 'agent', $allocation, $tenancy, $resp['complex_id']);
                }
            }
        }
        foreach ($buckets as &$b) {
            $b['complex_ids'] = array_values($b['complex_ids']);
        }
        return $buckets;
    }

    /** @return array{0: float, 1: array} */
    private function amountFor(CommissionAgreement $agreement, array $bucket, string $from, string $to): array
    {
        $basis = (float) $bucket['basis'];
        $tenants = count($bucket['tenancies']);
        $breakdown = ['basis' => $basis, 'tenants' => $tenants, 'payments' => $bucket['allocations'], 'agreement' => $agreement->describe(), 'scope' => $agreement->scope];

        switch ($agreement->method) {
            case 'percent_net':
                // Expenses of the properties this beneficiary answers for: all of them for the company, the
                // properties where an agent is the primary manager for an agent (never shared between agents).
                $complexIds = $bucket['beneficiary'] === 'company'
                    ? $bucket['complex_ids']
                    : DB::table('complexes')->whereIn('id', $bucket['complex_ids'] ?: [0])->where('assigned_agent_id', $bucket['agent_id'])->pluck('id')->all();
                $expenses = $complexIds ? (float) ComplexExpense::withoutGlobalScopes()->whereIn('complex_id', $complexIds)
                    ->whereBetween('expense_date', [$from, $to . ' 23:59:59'])
                    ->where(fn ($q) => $q->whereNull('workflow_status')->orWhereNotIn('workflow_status', ['draft', 'pending', 'void']))
                    ->sum('amount') : 0.0;
                $net = max(0, $basis - $expenses);
                return [round($net * $agreement->rate / 100, 2), $breakdown + ['expenses' => round($expenses, 2), 'net' => round($net, 2), 'rate' => $agreement->rate]];
            case 'flat_per_tenant':
                return [round((float) $agreement->flat_amount * $tenants, 2), $breakdown + ['flat_amount' => (float) $agreement->flat_amount, 'rate' => null]];
            case 'tiered':
                foreach ($agreement->tiers ?? [] as $tier) {
                    $minOk = $basis >= (float) $tier['min'];
                    $maxOk = $tier['max'] === null || $basis <= (float) $tier['max'];
                    if ($minOk && $maxOk) {
                        return [round($basis * (float) $tier['rate'] / 100, 2), $breakdown + ['tier' => $tier, 'rate' => (float) $tier['rate']]];
                    }
                }
                return [0.0, $breakdown + ['tier' => null, 'rate' => 0]];
        }
        return [0.0, $breakdown];
    }
}
