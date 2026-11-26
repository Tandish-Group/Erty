<?php

namespace App\Services\Commission;

use App\Services\Access\PropertyAccess;

use App\Models\CommissionEntry;
use Illuminate\Support\Facades\DB;

/**
 * Commission reporting from the ledger: the company's gross fee, agent commission paid out of it, agent
 * commission as an additional cost, the company's net, what is owed and paid, and breakdowns. Figures count
 * entries that are earned, approved or paid (a cancelled entry and its reversal are both "reversed" and drop
 * out; a clawback of paid commission counts as a negative earned entry).
 */
class CommissionReportService
{
    public const LIVE = [CommissionEntry::EARNED, CommissionEntry::APPROVED, CommissionEntry::PAID];

    /** @param array{from: string, to: string, complex_id?: ?int, agent_id?: ?int, beneficiary?: ?string, status?: ?string, management_type?: ?string, unit_type?: ?string, kind?: ?string} $f */
    public function query(int $businessId, array $f)
    {
        return CommissionEntry::query()->from('commission_entries as e')->where('e.business_id', $businessId)
            ->where(fn ($q) => $q
                ->where(fn ($p) => $p->whereIn('e.kind', ['payment', 'reversal'])->whereDate('e.collected_on', '>=', $f['from'])->whereDate('e.collected_on', '<=', $f['to']))
                ->orWhere(fn ($p) => $p->whereIn('e.kind', ['period', 'adjustment'])->whereDate('e.period_to', '>=', $f['from'])->whereDate('e.period_to', '<=', $f['to'])))
            ->when($f['complex_id'] ?? null, fn ($q, $v) => $q->where('e.complex_id', $v))
            ->when($f['agent_id'] ?? null, fn ($q, $v) => $q->where('e.agent_id', $v))
            ->when($f['beneficiary'] ?? null, fn ($q, $v) => $q->where('e.beneficiary_type', $v))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('e.status', $v))
            ->when($f['management_type'] ?? null, fn ($q, $v) => $q->where('e.management_type', $v))
            ->when($f['unit_type'] ?? null, fn ($q, $v) => $q->where('e.unit_type', $v))
            ->when($f['kind'] ?? null, fn ($q, $v) => $q->where('e.kind', $v))
            ->tap(fn ($q) => $this->limit($q, 'e.complex_id', fn ($u, $kind, $ids) => $u->where('e.unit_type', $kind)->whereIn('e.unit_id', $ids), 'e.agent_id'));
    }

    /** Rent allocations (live, on rent invoices) for the period, limited the same way as the ledger. */
    private function rentQuery(int $businessId, array $f)
    {
        return DB::table('tenant_invoice_payment_allocations as a')
            ->join('tenant_management as tm', 'tm.id', '=', 'a.tenant_id')->where('tm.managed_by', $businessId)
            ->whereNull('a.reversed_at')->where('a.invoice_kind', 'rent')->whereIn('a.source', ['payment', 'deposit'])
            ->whereDate('a.allocation_date', '>=', $f['from'])->whereDate('a.allocation_date', '<=', $f['to'])
            ->when($f['complex_id'] ?? null, fn ($q, $v) => $q->where('tm.complex_id', $v))
            ->tap(fn ($q) => $this->limit($q, 'tm.complex_id', fn ($u, $kind, $ids) => $u->whereIn($kind === 'commercial' ? 'tm.commercial_unit_id' : 'tm.residential_unit_id', $ids)));
    }

    /**
     * Team members limited to assigned properties see their properties in full, only their units on
     * properties they reach through unit assignments, and always their own commission.
     */
    private function limit($query, string $complexColumn, callable $unitClause, ?string $agentColumn = null)
    {
        $access = app(PropertyAccess::class);
        $ids = $access->complexIds();
        if ($ids === null) {
            return $query;
        }
        $unitOnly = $access->unitScope();
        $full = array_values(array_diff($ids, array_keys($unitOnly)));
        $agentId = $agentColumn ? $access->agentId() : null;
        return $query->where(function ($w) use ($complexColumn, $unitClause, $agentColumn, $full, $unitOnly, $agentId) {
            $w->whereIn($complexColumn, $full ?: [0]);
            foreach ($unitOnly as $complexId => $units) {
                foreach (['residential', 'commercial'] as $kind) {
                    if ($units[$kind]) {
                        $w->orWhere(fn ($u) => $unitClause($u->where($complexColumn, $complexId), $kind, $units[$kind]));
                    }
                }
            }
            if ($agentId) {
                $w->orWhere($agentColumn, $agentId);
            }
        });
    }

    public function summary(int $businessId, array $f): array
    {
        $sum = fn ($q) => round((float) $q->sum('e.amount'), 2);
        $live = fn () => $this->query($businessId, $f)->whereIn('e.status', self::LIVE);

        $gross = $sum($live()->where('e.entitlement', 'management_fee'));
        $fromFee = $sum($live()->where('e.beneficiary_type', 'agent')->where('e.funding', 'company_fee'));
        $additional = $sum($live()->where('e.beneficiary_type', 'agent')->where('e.funding', 'additional_cost'));

        // Reconciliation: rent collected (live allocations) against the rent the ledger has recorded.
        $rentQuery = $this->rentQuery($businessId, $f);
        $rent = app(PropertyAccess::class)->unrestricted(fn () => round((float) $rentQuery->sum('a.allocated_amount'), 2));
        $recorded = round((float) DB::table('tenant_invoice_payment_allocations as a')->whereNull('a.reversed_at')->where('a.invoice_kind', 'rent')
            ->whereIn('a.id', $this->query($businessId, $f)->where('e.kind', 'payment')->where('e.status', '!=', CommissionEntry::REVERSED)->select('e.allocation_id'))
            ->sum('a.allocated_amount'), 2);

        return [
            'gross_fee' => $gross,
            'agent_from_fee' => $fromFee,
            'additional_cost' => $additional,
            'company_net' => round($gross - $fromFee, 2),
            'agent_total' => round($fromFee + $additional, 2),
            'owed' => $sum($this->query($businessId, $f)->where('e.beneficiary_type', 'agent')->whereIn('e.status', [CommissionEntry::EARNED, CommissionEntry::APPROVED])->whereNull('e.payout_id')),
            'paid' => $sum($this->query($businessId, $f)->where('e.beneficiary_type', 'agent')->where('e.status', CommissionEntry::PAID)),
            'pending' => $sum($this->query($businessId, $f)->where('e.status', CommissionEntry::PENDING)),
            'pending_count' => $this->query($businessId, $f)->where('e.status', CommissionEntry::PENDING)->count(),
            'reversals' => $sum($this->query($businessId, $f)->where('e.kind', 'reversal')),
            'reversal_count' => $this->query($businessId, $f)->where('e.kind', 'reversal')->count(),
            'primary' => $sum($live()->where('e.entitlement', 'primary_on_delegated')),
            'sub_agents' => $sum($live()->where('e.entitlement', 'agent')->whereNotNull('e.primary_agent_id')->whereColumn('e.agent_id', '!=', 'e.primary_agent_id')),
            'own_units' => $sum($live()->where('e.entitlement', 'agent')->where(fn ($q) => $q->whereNull('e.primary_agent_id')->orWhereColumn('e.agent_id', 'e.primary_agent_id'))),
            'rent_collected' => $rent,
            'rent_recorded' => $recorded,
            'residential' => $sum($live()->where('e.entitlement', 'management_fee')->where('e.unit_type', 'residential')),
            'commercial' => $sum($live()->where('e.entitlement', 'management_fee')->where('e.unit_type', 'commercial')),
        ];
    }

    /** Totals per property, agent or unit, with the rent collected there (from the live allocations). */
    public function grouped(int $businessId, array $f, string $by)
    {
        $rows = $this->groupedEntries($businessId, $f, $by);
        if ($by === 'agent') {
            return $rows;
        }
        $rentQuery = $this->rentQuery($businessId, $f);
        $rent = app(PropertyAccess::class)->unrestricted(fn () => $rentQuery
            ->when($by === 'unit', fn ($q) => $q->selectRaw("tm.complex_id, CASE WHEN tm.commercial_unit_id IS NOT NULL THEN 'commercial' ELSE 'residential' END as unit_type, COALESCE(tm.commercial_unit_id, tm.residential_unit_id) as unit_id, SUM(a.allocated_amount) as rent")
                ->groupBy('tm.complex_id', 'tm.commercial_unit_id', 'tm.residential_unit_id'),
                fn ($q) => $q->selectRaw('tm.complex_id, SUM(a.allocated_amount) as rent')->groupBy('tm.complex_id'))
            ->get());
        $key = fn ($r) => $by === 'unit' ? $r->complex_id . '|' . $r->unit_type . '|' . $r->unit_id : (string) $r->complex_id;
        $rentBy = $rent->mapWithKeys(fn ($r) => [$key($r) => (float) $r->rent]);
        return $rows->each(fn ($r) => $r->rent = $rentBy[$key($r)] ?? 0.0);
    }

    private function groupedEntries(int $businessId, array $f, string $by)
    {
        $q = $this->query($businessId, $f)->whereIn('e.status', array_merge(self::LIVE, [CommissionEntry::PENDING]));
        $select = [
            DB::raw("SUM(CASE WHEN e.entitlement = 'management_fee' AND e.status <> 'pending' THEN e.amount ELSE 0 END) as company_fee"),
            DB::raw("SUM(CASE WHEN e.beneficiary_type = 'agent' AND e.status <> 'pending' THEN e.amount ELSE 0 END) as agent_commission"),
            DB::raw("SUM(CASE WHEN e.beneficiary_type = 'agent' AND e.funding = 'company_fee' AND e.status <> 'pending' THEN e.amount ELSE 0 END) as agent_from_fee"),
            DB::raw("SUM(CASE WHEN e.status = 'pending' THEN e.amount ELSE 0 END) as pending"),
            DB::raw("SUM(CASE WHEN e.entitlement = 'agent' AND e.kind = 'payment' AND e.status <> 'pending' THEN e.basis_amount ELSE 0 END) as own_rent"),
            DB::raw("SUM(CASE WHEN e.beneficiary_type = 'agent' AND e.status IN ('earned', 'approved') AND e.payout_id IS NULL THEN e.amount ELSE 0 END) as owed"),
        ];
        return match ($by) {
            'agent' => $q->whereNotNull('e.agent_id')->leftJoin('agents as ag', 'ag.id', '=', 'e.agent_id')
                ->groupBy('e.agent_id', 'ag.first_name', 'ag.last_name')->orderBy('ag.first_name')
                ->get(array_merge(['e.agent_id', 'ag.first_name', 'ag.last_name',
                    DB::raw("SUM(CASE WHEN e.entitlement = 'agent' AND e.status <> 'pending' THEN e.amount ELSE 0 END) as own_commission"),
                    DB::raw("SUM(CASE WHEN e.entitlement = 'primary_on_delegated' AND e.status <> 'pending' THEN e.amount ELSE 0 END) as primary_commission"),
                    DB::raw("SUM(CASE WHEN e.entitlement = 'agent' AND e.kind = 'payment' THEN e.basis_amount ELSE 0 END) as agent_rent")], $select)),
            'unit' => $q->whereNotNull('e.unit_id')->leftJoin('complexes as c', 'c.id', '=', 'e.complex_id')
                ->leftJoin('complex_residential_units as ru', fn ($j) => $j->on('ru.id', '=', 'e.unit_id')->where('e.unit_type', 'residential'))
                ->leftJoin('commercial_units as cu', fn ($j) => $j->on('cu.id', '=', 'e.unit_id')->where('e.unit_type', 'commercial'))
                ->groupBy('e.complex_id', 'c.title', 'e.unit_type', 'e.unit_id', 'ru.unit_number', 'cu.unit_number')->orderBy('c.title')
                ->get(array_merge(['e.complex_id', 'c.title', 'e.unit_type', 'e.unit_id', DB::raw('COALESCE(ru.unit_number, cu.unit_number) as unit_number')], $select)),
            default => $q->leftJoin('complexes as c', 'c.id', '=', 'e.complex_id')
                ->groupBy('e.complex_id', 'c.title')->orderBy('c.title')
                ->get(array_merge(['e.complex_id', 'c.title', DB::raw('MAX(e.management_type) as management_type')], $select)),
        };
    }

    /** Ledger lines with what they refer to. */
    public function lines(int $businessId, array $f)
    {
        return $this->query($businessId, $f)
            ->leftJoin('complexes as c', 'c.id', '=', 'e.complex_id')
            ->leftJoin('agents as ag', 'ag.id', '=', 'e.agent_id')
            ->leftJoin('tenant_management as tm', 'tm.id', '=', 'e.tenancy_id')
            ->leftJoin('tenant_profiles as tp', 'tp.id', '=', 'tm.tenant_profile_id')
            ->leftJoin('tenant_invoices as i', 'i.id', '=', 'e.invoice_id')
            ->leftJoin('commission_agreements as ca', 'ca.id', '=', 'e.agreement_id')
            ->select('e.*', 'c.title as property', 'ag.first_name as agent_first', 'ag.last_name as agent_last', 'tm.tenancy_ref', 'tm.unit_number',
                'tp.tenant_name', 'tp.business_name', 'tp.tenant_type', 'i.invoice_number', 'ca.name as agreement_name', 'ca.scope as agreement_scope')
            ->orderByDesc(DB::raw('COALESCE(e.collected_on, e.period_to)'))->orderByDesc('e.id');
    }
}
