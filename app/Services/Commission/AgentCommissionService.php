<?php

namespace App\Services\Commission;

use App\Services\Access\PropertyAccess;
use App\Services\NotificationCenter;

use App\Models\Agent;
use App\Models\AgentCommissionPayout;
use App\Models\CommissionAgreement;
use App\Models\CommissionEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Agent commission, read from the commission ledger (commission_entries, see CommissionLedgerService).
 * Figures are never recalculated from today's rates: each entry keeps the agreement and rate it was earned under.
 *
 * - "Commission" (own): the agent's entries as the agent responsible for the unit, per payment and per period.
 * - "Override": the agent's entries as primary manager on units delegated to other agents.
 * - Statements list the entries behind the figures; payouts settle what is earned or approved and still unpaid,
 *   and deduct clawbacks (reversals of commission already paid).
 */
class AgentCommissionService
{
    private const LEGACY_TYPES = ['percent_collected' => 'sales_value', 'percent_net' => 'net_profit', 'flat_per_tenant' => 'flat_rate', 'tiered' => 'tiered'];

    /** Every agent of the business with their commission for a period. */
    public function getAgentCommissions(int $userId, string $dateFrom, string $dateTo): array
    {
        $agents = Agent::where('created_by', $userId)->orderBy('first_name')->get();
        $rows = $agents->map(fn (Agent $a) => $this->forAgent($a, $dateFrom, $dateTo))->all();

        return ['agents' => $rows, 'summary' => [
            'total_agents' => count($rows),
            'total_payments' => round(array_sum(array_column($rows, 'total_payments')), 2),
            'total_commission' => round(array_sum(array_column($rows, 'total_commission')), 2),
        ]];
    }

    /** One agent's commission for a period (agent profile, "My agent" and the commissions report). */
    public function forAgent(Agent $agent, string $dateFrom, string $dateTo): array
    {
        return app(PropertyAccess::class)->unrestricted(function () use ($agent, $dateFrom, $dateTo) {
            $inPeriod = $this->entries($agent, $dateFrom, $dateTo)->whereIn('status', [CommissionEntry::EARNED, CommissionEntry::APPROVED, CommissionEntry::PAID])->get();
            $pendingPeriod = $this->entries($agent, $dateFrom, $dateTo)->where('status', CommissionEntry::PENDING)->sum('amount');

            $own = $inPeriod->where('entitlement', 'agent');
            $override = $inPeriod->where('entitlement', 'primary_on_delegated');
            $sales = $inPeriod->where('entitlement', 'sale');
            $ownPayment = $own->where('kind', 'payment');
            $agreement = CommissionAgreement::where('business_id', $agent->created_by)->where('entitlement', 'agent')->where('agent_id', $agent->id)
                ->activeOn($dateTo)->orderByDesc('effective_from')->first();
            $overrideAgreement = CommissionAgreement::where('business_id', $agent->created_by)->where('entitlement', 'primary_on_delegated')->where('agent_id', $agent->id)
                ->activeOn($dateTo)->orderByDesc('effective_from')->first();

            $ownCommission = round((float) $own->sum('amount'), 2);
            $overrideCommission = round((float) $override->sum('amount'), 2);
            $salesCommission = round((float) $sales->sum('amount'), 2);

            return [
                'agent_id' => $agent->id,
                'agent_name' => $agent->full_name ?? trim(($agent->first_name ?? '') . ' ' . ($agent->last_name ?? '')),
                'agent_type' => $agent->agent_type,
                'commission_type' => $agreement ? (self::LEGACY_TYPES[$agreement->method] ?? $agreement->method) : null,
                'commission_rate' => $agreement ? (float) ($agreement->method === 'flat_per_tenant' ? $agreement->flat_amount : $agreement->rate) : 0.0,
                'agreement' => $agreement?->describe(),
                'base_salary' => (float) ($agent->base_salary ?? 0),
                'total_payments' => round((float) $ownPayment->sum('basis_amount') + (float) $own->where('kind', 'period')->sum('basis_amount'), 2),
                'total_expenses' => round((float) $own->where('kind', 'period')->sum(fn ($e) => (float) ($e->breakdown['expenses'] ?? 0)), 2),
                'net_payments' => 0.0,
                'tenant_count' => $ownPayment->pluck('tenancy_id')->unique()->count(),
                'has_tiers' => (bool) ($agreement?->tiers),
                'all_calculations' => [],
                'default_commission' => $ownCommission,
                'override_rate' => (float) ($overrideAgreement?->rate ?? 0),
                'override_payments' => round((float) $override->sum('basis_amount'), 2),
                'override_commission' => $overrideCommission,
                'pending_commission' => round((float) $pendingPeriod, 2),
                'sales_collected' => round((float) $sales->where('kind', 'payment')->sum('basis_amount'), 2),
                'sales_commission' => $salesCommission,
                'total_commission' => round($ownCommission + $overrideCommission + $salesCommission, 2),
            ];
        });
    }

    /** this_month (default), last_month, this_quarter, this_year or custom (from/to). */
    public static function period(?string $key, ?string $from = null, ?string $to = null): array
    {
        $now = Carbon::now();
        [$start, $end, $key] = match ($key) {
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'last_month'],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter(), 'this_quarter'],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear(), 'this_year'],
            'custom' => (function () use ($from, $to, $now) {
                try {
                    $a = Carbon::parse((string) $from)->startOfDay();
                    $b = Carbon::parse((string) $to)->endOfDay();
                } catch (\Throwable $e) {
                    return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this_month'];
                }
                return $a->lte($b) ? [$a, $b, 'custom'] : [$b->copy()->startOfDay(), $a->copy()->endOfDay(), 'custom'];
            })(),
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth(), 'this_month'],
        };

        return ['key' => $key, 'from' => $start->toDateString(), 'to' => $end->toDateString(), 'label' => $start->format('j M Y') . ' – ' . $end->format('j M Y')];
    }

    /** The agent's entries for a period: payment entries collected in it, period entries for it, and reversals made in it. */
    private function entries(Agent $agent, string $from, string $to)
    {
        return CommissionEntry::query()->where('agent_id', $agent->id)->where('beneficiary_type', 'agent')
            ->where(fn ($q) => $q
                ->where(fn ($p) => $p->where('kind', 'payment')->whereDate('collected_on', '>=', $from)->whereDate('collected_on', '<=', $to))
                ->orWhere(fn ($p) => $p->whereIn('kind', ['period', 'adjustment'])->whereDate('period_to', '>=', $from)->whereDate('period_to', '<=', $to)));
    }

    /**
     * Statement for a period: the payments behind the commission, the override on delegated units, period
     * commission, clawbacks still to deduct, payouts, and what is due now.
     */
    public function statement(Agent $agent, string $dateFrom, string $dateTo): array
    {
        return app(PropertyAccess::class)->unrestricted(function () use ($agent, $dateFrom, $dateTo) {
            $row = $this->forAgent($agent, $dateFrom, $dateTo);
            $lines = fn ($q) => $q->leftJoin('tenant_management as tm', 'tm.id', '=', 'commission_entries.tenancy_id')
                ->leftJoin('tenant_profiles as tp', 'tp.id', '=', 'tm.tenant_profile_id')
                ->leftJoin('tenant_invoices as i', 'i.id', '=', 'commission_entries.invoice_id')
                ->leftJoin('tenant_invoice_payment_allocations as al', 'al.id', '=', 'commission_entries.allocation_id')
                ->leftJoin('agents as ag', 'ag.id', '=', 'commission_entries.agent_id')
                ->leftJoin('complexes as c', 'c.id', '=', 'commission_entries.complex_id')
                ->select('commission_entries.*', 'tm.tenancy_ref', 'tm.complex_name', 'tm.unit_number', 'i.invoice_number', 'al.source as via_source',
                    'tp.tenant_name', 'tp.business_name', 'tp.tenant_type', 'c.title as property_title')
                ->orderBy('commission_entries.collected_on')->orderBy('commission_entries.id')->get();

            $tenant = fn ($l) => $l->tenant_type === 'Business' ? ($l->business_name ?: $l->tenant_name) : $l->tenant_name;
            $shape = fn ($l) => [
                'entry_id' => $l->id, 'allocation_id' => $l->allocation_id, 'date' => $l->collected_on, 'tenant' => $tenant($l), 'tenancy_ref' => $l->tenancy_ref,
                'property' => $l->complex_name ?: $l->property_title, 'unit' => $l->unit_number, 'invoice' => $l->invoice_number, 'agent' => trim($agent->first_name . ' ' . $agent->last_name),
                'via' => $l->via_source === 'deposit' ? 'Deposit applied' : 'Payment', 'amount' => (float) $l->basis_amount, 'rate' => (float) $l->rate,
                'commission' => (float) $l->amount, 'paid' => (bool) $l->payout_id, 'status' => $l->status, 'currency' => $l->currency ?: default_currency(),
                'note' => (is_array($l->breakdown) ? $l->breakdown : json_decode((string) $l->breakdown, true))['note'] ?? null,
                'tenancy_url' => $l->tenancy_ref ? tenancy_profile_url((object) ['tenancy_ref' => $l->tenancy_ref]) : null,
            ];
            $base = fn () => CommissionEntry::query()->where('commission_entries.agent_id', $agent->id)->where('commission_entries.beneficiary_type', 'agent');
            $live = [CommissionEntry::EARNED, CommissionEntry::APPROVED, CommissionEntry::PAID, CommissionEntry::PENDING];

            $own = $lines($base()->where('commission_entries.entitlement', 'agent')->where('commission_entries.kind', 'payment')->whereIn('commission_entries.status', $live)
                ->whereDate('commission_entries.collected_on', '>=', $dateFrom)->whereDate('commission_entries.collected_on', '<=', $dateTo))->map($shape)->all();
            $team = $lines($base()->where('commission_entries.entitlement', 'primary_on_delegated')->where('commission_entries.kind', 'payment')->whereIn('commission_entries.status', $live)
                ->whereDate('commission_entries.collected_on', '>=', $dateFrom)->whereDate('commission_entries.collected_on', '<=', $dateTo))->map($shape)->all();
            // Sales commission: one line per buyer payment on a house sale.
            $hasSales = CommissionEntry::where('agent_id', $agent->id)->where('entitlement', 'sale')->exists();
            $saleLines = !$hasSales ? [] : CommissionEntry::query()->leftJoin('property_sales as ps', 'ps.id', '=', 'commission_entries.sale_id')
                ->leftJoin('property_sale_payments as sp', 'sp.id', '=', 'commission_entries.sale_payment_id')
                ->leftJoin('complex_residential_units as hu', 'hu.id', '=', 'commission_entries.unit_id')
                ->leftJoin('complexes as c', 'c.id', '=', 'commission_entries.complex_id')
                ->where('commission_entries.agent_id', $agent->id)->where('commission_entries.entitlement', 'sale')->where('commission_entries.kind', 'payment')
                ->whereIn('commission_entries.status', $live)->whereDate('commission_entries.collected_on', '>=', $dateFrom)->whereDate('commission_entries.collected_on', '<=', $dateTo)
                ->select('commission_entries.*', 'ps.reference as sale_reference', 'ps.buyer_name', 'ps.price as sale_price', 'sp.receipt_number', 'hu.unit_number', 'c.title as property_title')
                ->orderBy('commission_entries.collected_on')->orderBy('commission_entries.id')->get()
                ->map(fn ($l) => ['entry_id' => $l->id, 'date' => $l->collected_on, 'sale' => $l->sale_reference, 'buyer' => $l->buyer_name, 'property' => $l->property_title,
                    'unit' => $l->unit_number, 'receipt' => $l->receipt_number, 'amount' => (float) $l->basis_amount, 'sale_price' => (float) $l->sale_price, 'rate' => (float) $l->rate,
                    'agreement' => (is_array($l->breakdown) ? $l->breakdown : json_decode((string) $l->breakdown, true))['agreement'] ?? $l->method,
                    'commission' => (float) $l->amount, 'paid' => (bool) $l->payout_id, 'status' => $l->status, 'currency' => $l->currency ?: default_currency()])->all();
            $periodLines = CommissionEntry::query()->where('agent_id', $agent->id)->where('beneficiary_type', 'agent')->whereIn('kind', ['period', 'adjustment'])
                ->whereDate('period_to', '>=', $dateFrom)->whereDate('period_to', '<=', $dateTo)->orderBy('period_from')->get()
                ->map(fn ($e) => ['entry_id' => $e->id, 'kind' => $e->kind, 'from' => $e->period_from, 'to' => $e->period_to, 'agreement' => $e->breakdown['agreement'] ?? $e->method,
                    'basis' => (float) $e->basis_amount, 'commission' => (float) $e->amount, 'status' => $e->status, 'paid' => (bool) $e->payout_id, 'breakdown' => $e->breakdown])->all();
            $clawbacks = $lines($base()->where('commission_entries.kind', 'reversal')->where('commission_entries.status', CommissionEntry::EARNED)->whereNull('commission_entries.payout_id'))
                ->map(fn ($l) => ['kind' => ['agent' => 'own', 'sale' => 'sale'][$l->entitlement] ?? 'override'] + $shape($l))
                ->map(fn ($l) => array_merge($l, ['commission' => abs($l['commission']), 'amount' => abs($l['amount'])]))->all();

            $payable = $this->payable($agent, $dateFrom, $dateTo)->get();
            $dueOwn = round((float) $payable->where('entitlement', 'agent')->where('kind', '!=', 'reversal')->sum('amount'), 2);
            $dueOverride = round((float) $payable->where('entitlement', 'primary_on_delegated')->where('kind', '!=', 'reversal')->sum('amount'), 2);
            $dueSales = round((float) $payable->where('entitlement', 'sale')->where('kind', '!=', 'reversal')->sum('amount'), 2);
            $dueClaw = round(abs((float) $payable->where('kind', 'reversal')->sum('amount')), 2);

            return [
                'agent' => ['id' => $agent->id, 'name' => $agent->full_name ?? trim($agent->first_name . ' ' . $agent->last_name)],
                'from' => $dateFrom, 'to' => $dateTo,
                'currency' => collect($own)->first()['currency'] ?? default_currency(),
                'figures' => $row,
                'lines' => $own,
                'override_lines' => $team,
                'period_lines' => $periodLines,
                'sale_lines' => $saleLines,
                'clawbacks' => $clawbacks,
                'due' => [
                    'commission' => $dueOwn,
                    'override' => $dueOverride,
                    'clawback' => $dueClaw,
                    'sales' => $dueSales,
                    'total' => round($dueOwn + $dueOverride + $dueSales - $dueClaw, 2),
                    'rent_collected' => round((float) $payable->where('entitlement', 'agent')->where('kind', 'payment')->sum('basis_amount'), 2),
                ],
                'payouts' => AgentCommissionPayout::where('agent_id', $agent->id)->orderByDesc('paid_at')->get(),
            ];
        });
    }

    /** Entries a payout for this period settles: earned or approved and unpaid, plus open clawbacks from any date. */
    private function payable(Agent $agent, string $from, string $to)
    {
        return CommissionEntry::query()->where('agent_id', $agent->id)->where('beneficiary_type', 'agent')
            ->whereIn('status', [CommissionEntry::EARNED, CommissionEntry::APPROVED])->whereNull('payout_id')
            ->where(fn ($q) => $q
                ->where(fn ($p) => $p->where('kind', 'payment')->whereDate('collected_on', '>=', $from)->whereDate('collected_on', '<=', $to))
                ->orWhere(fn ($p) => $p->whereIn('kind', ['period', 'adjustment'])->whereDate('period_to', '>=', $from)->whereDate('period_to', '<=', $to))
                ->orWhere('kind', 'reversal'));
    }

    /** Pay what is due on a statement; the entries settled are marked paid and can't be paid again. */
    public function recordPayout(Agent $agent, string $dateFrom, string $dateTo, array $data): AgentCommissionPayout
    {
        return DB::transaction(function () use ($agent, $dateFrom, $dateTo, $data) {
            DB::table('agents')->where('id', $agent->id)->lockForUpdate()->first();
            $statement = $this->statement($agent, $dateFrom, $dateTo);
            if ($statement['due']['total'] <= 0) {
                throw ValidationException::withMessages(['payout' => 'Nothing is due for this period.']);
            }
            $entryIds = $this->payable($agent, $dateFrom, $dateTo)->pluck('id');
            $payout = AgentCommissionPayout::create([
                'agent_id' => $agent->id, 'kind' => 'agent', 'managed_by' => $agent->created_by,
                'period_from' => $dateFrom, 'period_to' => $dateTo, 'currency' => $statement['currency'],
                'rent_collected' => $statement['due']['rent_collected'], 'commission' => $statement['due']['commission'],
                'override' => $statement['due']['override'], 'clawback' => $statement['due']['clawback'], 'total' => $statement['due']['total'],
                'lines' => ['entries' => $entryIds->values(), 'sales_commission' => $statement['due']['sales']],
                'method' => $data['method'] ?? null, 'reference' => $data['reference'] ?? null, 'notes' => $data['notes'] ?? null,
                'paid_at' => $data['paid_at'] ?? now(), 'paid_by' => auth()->id(),
            ]);
            CommissionEntry::whereIn('id', $entryIds)->update(['payout_id' => $payout->id, 'status' => CommissionEntry::PAID]);
            app(NotificationCenter::class)->commissionPaid($payout, $agent);
            return $payout;
        });
    }
}
