<?php

namespace App\Http\Controllers\Business\Agents;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\CommissionAgreement;
use App\Models\CommissionEntry;
use App\Models\Complex;
use App\Services\Commission\AgentCommissionService;
use App\Services\Commission\CommissionAgreementService;
use App\Services\Commission\CommissionLedgerService;
use App\Services\Commission\CommissionPeriodService;
use App\Services\Commission\CommissionReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Agents → Commissions: the commission ledger (company fee, agent commission, what is owed and paid), breakdowns
 * by property, agent and unit, period commission for approval, commission agreements, and agent statements
 * with payouts. Everything reads the ledger; nothing is recalculated from today's rates.
 */
class AgentCommissionController extends Controller
{
    public const TABS = ['summary' => 'Summary', 'ledger' => 'Ledger', 'properties' => 'By property', 'agents' => 'By agent', 'units' => 'By unit', 'periods' => 'Periods & approvals', 'agreements' => 'Agreements'];
    /** What an agent who may only view sees: their own commission. */
    public const OWN_TABS = ['summary', 'ledger', 'properties', 'units'];

    public function __construct(private AgentCommissionService $service, private CommissionReportService $reports) {}

    public function index(Request $request)
    {
        $businessId = business_owner_id();
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'summary';
        $own = $this->ownAgent();
        $limited = auth()->user()->limitedToAssignedProperties();
        abort_if(($own || $limited) && !in_array($tab, self::OWN_TABS, true), 403, $own ? 'You can see your own commission only.' : 'Commission periods, agreements and agents are for people who work with every property.');
        [$period, $filters] = $this->filters($request);
        $data = ['tab' => $tab, 'tabs' => ($own || $limited) ? \Illuminate\Support\Arr::only(self::TABS, self::OWN_TABS) : self::TABS, 'ownAgent' => $own, 'period' => $period, 'filters' => $filters,
            'canEdit' => auth()->user()->hasBusinessPermission('agent_management', 'edit'),
            'canEditFee' => $this->canEditFee(),
            'filterComplex' => $filters['complex_id'] ? Complex::find($filters['complex_id'], ['id', 'title']) : null,
            'filterAgent' => $filters['agent_id'] ? Agent::where('created_by', $businessId)->find($filters['agent_id']) : null];

        $data += match ($tab) {
            'summary' => ['summary' => $this->reports->summary($businessId, $filters), 'byAgent' => $this->reports->grouped($businessId, $filters, 'agent')],
            'ledger' => ['lines' => $this->reports->lines($businessId, $filters)->paginate(50)->withQueryString()],
            'properties' => ['rows' => $this->reports->grouped($businessId, $filters, 'property')],
            'agents' => ['rows' => $this->reports->grouped($businessId, $filters, 'agent')],
            'units' => ['rows' => $this->reports->grouped($businessId, $filters, 'unit')],
            'periods' => $this->periodData($businessId, $request),
            'agreements' => $this->agreementData($businessId),
        };

        return view('pages.business.tenant.agent-commissions', $data);
    }

    /** The ledger lines for the filters, as CSV. */
    public function export(Request $request)
    {
        [$period, $filters] = $this->filters($request);
        $lines = $this->reports->lines(business_owner_id(), $filters);
        $name = 'commission-ledger-' . $period['from'] . '-to-' . $period['to'] . '.csv';

        return response()->streamDownload(function () use ($lines) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Entry', 'Kind', 'Date', 'Period', 'Beneficiary', 'Agent', 'Entitlement', 'Funding', 'Property', 'Unit', 'Tenant', 'Tenancy', 'Invoice',
                'Rental period', 'Agreement', 'Method', 'Rate %', 'Rent', 'Commission', 'Currency', 'Status', 'Payout', 'Reverses entry']);
            $lines->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $l) {
                    fputcsv($out, [$l->id, $l->kind, optional($l->collected_on)->toDateString(), $l->period_from ? $l->period_from->toDateString() . ' – ' . $l->period_to->toDateString() : '',
                        $l->beneficiary_type, trim($l->agent_first . ' ' . $l->agent_last), $l->entitlement, $l->funding, $l->property, $l->unit_number, $l->tenant_type === 'Business' ? ($l->business_name ?: $l->tenant_name) : $l->tenant_name,
                        $l->tenancy_ref, $l->invoice_number, $l->rental_period, $l->agreement_name ?: ($l->breakdown['agreement'] ?? ''), $l->method, $l->rate, $l->basis_amount, $l->amount,
                        $l->currency, $l->status, $l->payout_id, $l->reverses_entry_id]);
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    // ─── Agreements ──────────────────────────────────────────────────────────

    public function saveAgreement(Request $request, CommissionAgreementService $agreements)
    {
        $businessId = business_owner_id();
        $data = $request->validate([
            'replaces_id' => ['nullable', 'integer'],
            'entitlement' => ['required', Rule::in(CommissionAgreement::ENTITLEMENTS)],
            'scope' => ['required', Rule::in(CommissionAgreement::SCOPES)],
            'complex_id' => ['nullable', 'integer', 'required_if:scope,property,agent_property,unit'],
            'agent_id' => ['nullable', 'integer', 'required_if:scope,agent,agent_property'],
            'unit_type' => ['nullable', 'required_if:scope,unit', 'in:residential,commercial'],
            'unit_number' => ['nullable', 'required_if:scope,unit', 'string', 'max:50'],
            'method' => ['required', Rule::in(CommissionAgreement::METHODS)],
            'rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'flat_amount' => ['nullable', 'numeric', 'min:0', 'required_if:method,flat_per_tenant,flat_per_sale'],
            'tiers' => ['nullable', 'array', 'max:10'],
            'tiers.*.min' => ['nullable', 'numeric', 'min:0'], 'tiers.*.max' => ['nullable', 'numeric', 'min:0'], 'tiers.*.rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sharing' => ['nullable', 'in:separate,share_of_pool'],
            'sub_share_pct' => ['nullable', 'numeric', 'min:0', 'max:100', 'required_if:sharing,share_of_pool'],
            'funding' => ['nullable', Rule::in(CommissionAgreement::FUNDING)],
            'applies_to' => ['nullable', 'array'], 'applies_to.*' => ['in:rent,deposit,service_charge,late_fee'],
            'effective_from' => ['required', 'date'],
            'name' => ['nullable', 'string', 'max:120'],
            'change_reason' => ['nullable', 'string', 'max:255'],
        ], [], ['complex_id' => 'property', 'agent_id' => 'agent', 'sub_share_pct' => 'sub-agent share']);

        if ($data['entitlement'] === 'management_fee' && !$this->canEditFee()) {
            abort(403, 'Only the account owner or an administrator sets the company\'s commission.');
        }
        if ($data['entitlement'] === 'management_fee' && !in_array($data['scope'], ['company', 'property'], true)) {
            throw ValidationException::withMessages(['scope' => 'The company\'s commission is set for the whole company or one property.']);
        }
        $replacing = !empty($data['replaces_id']) ? CommissionAgreement::where('business_id', $businessId)->whereNull('effective_to')->findOrFail($data['replaces_id']) : null;

        $fields = collect($data)->only(['entitlement', 'scope', 'method', 'rate', 'flat_amount', 'tiers', 'sharing', 'sub_share_pct', 'funding', 'applies_to', 'effective_from', 'name'])->all();
        $fields['rate'] = (float) ($fields['rate'] ?? 0);
        $fields['complex_id'] = in_array($data['scope'], ['property', 'agent_property', 'unit'], true)
            ? Complex::forBusiness($businessId)->findOrFail($data['complex_id'])->id : null;
        $fields['agent_id'] = in_array($data['scope'], ['agent', 'agent_property'], true) || ($data['scope'] === 'unit' && !empty($data['agent_id']))
            ? Agent::where('created_by', $businessId)->findOrFail($data['agent_id'])->id : null;
        if ($data['scope'] === 'unit') {
            $model = $data['unit_type'] === 'commercial' ? \App\Models\CommercialUnit::class : \App\Models\ComplexResidentialUnit::class;
            $unit = $model::where('complex_id', $fields['complex_id'])->whereRaw('lower(unit_number) = ?', [mb_strtolower(trim($data['unit_number']))])->first();
            if (!$unit) {
                throw ValidationException::withMessages(['unit_number' => 'No ' . $data['unit_type'] . ' unit with that number at this property.']);
            }
            $fields['unit_type'] = $data['unit_type'];
            $fields['unit_id'] = $unit->id;
        }
        if ($replacing) {
            foreach (['entitlement', 'scope', 'complex_id', 'agent_id'] as $key) {
                if ((string) $replacing->$key !== (string) ($fields[$key] ?? '')) {
                    throw ValidationException::withMessages(['scope' => 'A change keeps who and where the agreement covers. Add a new agreement for something else.']);
                }
            }
        }
        $agreements->save($businessId, $fields, $replacing, $data['change_reason'] ?? null);

        return redirect()->route('tenant_management.agent_commissions', ['tab' => 'agreements'])->with('success', $replacing ? 'Agreement changed. Commission already recorded keeps its earlier terms.' : 'Agreement added.');
    }

    public function endAgreement(Request $request, int $agreement, CommissionAgreementService $agreements)
    {
        $data = $request->validate(['last_day' => ['required', 'date']]);
        $row = CommissionAgreement::where('business_id', business_owner_id())->whereNull('effective_to')->findOrFail($agreement);
        if ($row->entitlement === 'management_fee' && !$this->canEditFee()) {
            abort(403);
        }
        if ($data['last_day'] < $row->effective_from->toDateString()) {
            throw ValidationException::withMessages(['last_day' => 'The last day cannot be before the agreement started.']);
        }
        $agreements->end($row, $data['last_day']);
        return redirect()->route('tenant_management.agent_commissions', ['tab' => 'agreements'])->with('success', 'Agreement ended. Its history is kept.');
    }

    // ─── Periods and approvals ───────────────────────────────────────────────

    public function calculatePeriod(Request $request, CommissionPeriodService $periods)
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $month = \Carbon\Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
        abort_if($month->isFuture(), 422, 'That month has not started yet.');
        $r = $periods->calculate(business_owner_id(), $month->toDateString(), $month->copy()->endOfMonth()->toDateString());

        return redirect()->route('tenant_management.agent_commissions', ['tab' => 'periods', 'month' => $data['month']])
            ->with('success', "Calculated: {$r['created']} new, {$r['updated']} updated" . ($r['adjustments'] ? ", {$r['adjustments']} adjustments to approved entries" : '') . '.');
    }

    public function approve(Request $request, CommissionLedgerService $ledger)
    {
        $data = $request->validate(['entry_ids' => ['required', 'array', 'min:1'], 'entry_ids.*' => ['integer']]);
        $count = $ledger->approve($data['entry_ids'], business_owner_id());
        return back()->with('success', $count . ' ' . \Illuminate\Support\Str::plural('entry', $count) . ' approved.');
    }

    // ─── Statement and payouts ───────────────────────────────────────────────

    public function statement(Request $request, int $agentId)
    {
        $agent = Agent::where('id', $agentId)->where('created_by', business_owner_id())->firstOrFail();
        abort_unless(\App\Services\Agents\AgentProfileService::canSeeCommissionOf($agent), 403, 'You can see your own commission statement only.');
        $period = AgentCommissionService::period($request->query('period', $request->filled('from') ? 'custom' : null), $request->query('from'), $request->query('to'));
        $statement = $this->service->statement($agent, $period['from'], $period['to']);
        $canPay = auth()->user()->hasBusinessPermission('agent_management', 'edit');
        return view('pages.business.agents.statement', compact('agent', 'period', 'statement', 'canPay'));
    }

    public function recordPayout(Request $request, int $agentId)
    {
        $agent = Agent::where('id', $agentId)->where('created_by', business_owner_id())->firstOrFail();
        $data = $request->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from'],
            'method' => ['nullable', 'in:bank_transfer,mobile_money,cash,check,payroll,other'],
            'reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $payout = $this->service->recordPayout($agent, $data['from'], $data['to'], $data);
        return redirect()->route('tenant_management.agent_commissions.statement', ['agentId' => $agent->id, 'period' => 'custom', 'from' => $data['from'], 'to' => $data['to']])
            ->with('success', 'Payout of ' . $payout->currency . ' ' . number_format((float) $payout->total, 2) . ' recorded.');
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function filters(Request $request): array
    {
        $period = AgentCommissionService::period($request->query('period', $request->filled('from') ? 'custom' : null), $request->query('from'), $request->query('to'));
        $pick = fn (string $key, array $allowed) => in_array($request->query($key), $allowed, true) ? $request->query($key) : null;
        $filters = [
            'from' => $period['from'], 'to' => $period['to'],
            'complex_id' => $request->integer('complex_id') ?: null,
            'agent_id' => $request->integer('agent_id') ?: null,
            'beneficiary' => $pick('beneficiary', ['company', 'agent']),
            'status' => $pick('status', ['pending', 'earned', 'approved', 'paid', 'reversed']),
            'management_type' => $pick('management_type', ['company', 'individual']),
            'unit_type' => $pick('unit_type', ['residential', 'commercial']),
            'kind' => $pick('kind', ['payment', 'period', 'reversal', 'adjustment']),
        ];
        if ($own = $this->ownAgent()) {
            $filters = array_replace($filters, ['agent_id' => $own->id, 'beneficiary' => 'agent']); // never widened by the query string
        }
        return [$period, $filters];
    }

    /** The viewer's agent profile when they may only see their own commission (AgentProfileService::ownCommissionOnly). */
    private function ownAgent(): ?Agent
    {
        return \App\Services\Agents\AgentProfileService::ownCommissionOnly();
    }

    private function canEditFee(): bool
    {
        $user = auth()->user();
        return $user->isBusinessOwner() || $user->hasBusinessPermission('account_settings', 'edit');
    }

    private function periodData(int $businessId, Request $request): array
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : now()->subMonthNoOverflow()->format('Y-m');
        $from = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $pending = CommissionEntry::with(['agent:id,first_name,last_name', 'complex:id,title'])->where('business_id', $businessId)->where('status', CommissionEntry::PENDING)
            ->orderByDesc('id')->limit(200)->get();
        $periodEntries = CommissionEntry::with(['agent:id,first_name,last_name', 'agreement'])->where('business_id', $businessId)->whereIn('kind', ['period', 'adjustment'])
            ->whereDate('period_from', $from->toDateString())->orderBy('beneficiary_type')->orderBy('agent_id')->get();
        $hasPeriodAgreements = CommissionAgreement::where('business_id', $businessId)->whereIn('method', CommissionAgreement::PERIOD_METHODS)->exists();
        return compact('month', 'pending', 'periodEntries', 'hasPeriodAgreements');
    }

    private function agreementData(int $businessId): array
    {
        $agreements = CommissionAgreement::with(['agent:id,first_name,last_name', 'complex:id,title', 'creator:id,first_name,last_name'])
            ->where('business_id', $businessId)->orderByRaw('effective_to is not null')->orderBy('entitlement')->orderBy('scope')->orderByDesc('effective_from')->get();
        $unitNumbers = [
            'residential' => \App\Models\ComplexResidentialUnit::whereIn('id', $agreements->where('unit_type', 'residential')->pluck('unit_id'))->pluck('unit_number', 'id'),
            'commercial' => \App\Models\CommercialUnit::whereIn('id', $agreements->where('unit_type', 'commercial')->pluck('unit_id'))->pluck('unit_number', 'id'),
        ];
        $hasFee = $agreements->where('entitlement', 'management_fee')->whereNull('effective_to')->isNotEmpty();
        return compact('agreements', 'unitNumbers', 'hasFee');
    }
}
