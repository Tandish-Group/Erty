<?php

namespace App\Services\Commission;

use App\Models\CommissionAgreement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Commission agreements: which one applies (precedence), and changing them without rewriting history.
 *
 * Precedence, most specific first (the first agreement in force on the date wins):
 * - management_fee (the company's commission):  property > company default
 * - agent (the agent responsible for the unit):  unit > agent at this property > property default for agents > agent > company default
 * - primary_on_delegated (the primary manager on units delegated to others): agent at this property > property > agent (primary) > company
 */
class CommissionAgreementService
{
    /** @param array{complex_id?: ?int, agent_id?: ?int, unit_type?: ?string, unit_id?: ?int} $ctx */
    public function resolve(int $businessId, string $entitlement, array $ctx, $date): ?CommissionAgreement
    {
        $candidates = $this->inForce($businessId, $entitlement, $date);
        $complexId = $ctx['complex_id'] ?? null;
        $agentId = $ctx['agent_id'] ?? null;
        $unitType = $ctx['unit_type'] ?? null;
        $unitId = $ctx['unit_id'] ?? null;

        $order = match ($entitlement) {
            'management_fee' => [
                fn ($a) => $a->scope === 'property' && $complexId && (int) $a->complex_id === (int) $complexId,
                fn ($a) => $a->scope === 'company',
            ],
            'agent' => [
                fn ($a) => $a->scope === 'unit' && $unitId && $a->unit_type === $unitType && (int) $a->unit_id === (int) $unitId && (!$a->agent_id || (int) $a->agent_id === (int) $agentId),
                fn ($a) => $a->scope === 'agent_property' && $agentId && (int) $a->agent_id === (int) $agentId && (int) $a->complex_id === (int) $complexId,
                fn ($a) => $a->scope === 'property' && $complexId && (int) $a->complex_id === (int) $complexId,
                fn ($a) => $a->scope === 'agent' && $agentId && (int) $a->agent_id === (int) $agentId,
                fn ($a) => $a->scope === 'company',
            ],
            // sale: the agent on the sale — house > agent at this property > property > agent > company
            'sale' => [
                fn ($a) => $a->scope === 'unit' && $unitId && $a->unit_type === $unitType && (int) $a->unit_id === (int) $unitId && (!$a->agent_id || (int) $a->agent_id === (int) $agentId),
                fn ($a) => $a->scope === 'agent_property' && $agentId && (int) $a->agent_id === (int) $agentId && (int) $a->complex_id === (int) $complexId,
                fn ($a) => $a->scope === 'property' && $complexId && (int) $a->complex_id === (int) $complexId,
                fn ($a) => $a->scope === 'agent' && $agentId && (int) $a->agent_id === (int) $agentId,
                fn ($a) => $a->scope === 'company',
            ],
            'primary_on_delegated' => [
                fn ($a) => $a->scope === 'agent_property' && $agentId && (int) $a->agent_id === (int) $agentId && (int) $a->complex_id === (int) $complexId,
                fn ($a) => $a->scope === 'property' && $complexId && (int) $a->complex_id === (int) $complexId,
                fn ($a) => $a->scope === 'agent' && $agentId && (int) $a->agent_id === (int) $agentId,
                fn ($a) => $a->scope === 'company',
            ],
            default => [],
        };
        foreach ($order as $matches) {
            if ($found = $candidates->first($matches)) {
                return $found;
            }
        }
        return null;
    }

    /** @var array<string, \Illuminate\Support\Collection> agreements in force, per business/entitlement/date (this instance only) */
    private array $cache = [];

    /** Agreements of one entitlement in force on a date. */
    public function inForce(int $businessId, string $entitlement, $date)
    {
        $key = $businessId . '|' . $entitlement . '|' . \Carbon\Carbon::parse($date)->toDateString();
        return $this->cache[$key] ??= CommissionAgreement::where('business_id', $businessId)->where('entitlement', $entitlement)
            ->activeOn($date)->orderByDesc('effective_from')->orderByDesc('id')->get();
    }

    /**
     * Start an agreement, or change one: the current version ends the day before the new one starts, so
     * entries already recorded keep pointing at the terms they used.
     */
    public function save(int $businessId, array $data, ?CommissionAgreement $replacing = null, ?string $reason = null): CommissionAgreement
    {
        $data = $this->normalise($data);
        $this->validateFunding($businessId, $data);

        return DB::transaction(function () use ($businessId, $data, $replacing, $reason) {
            $from = \Carbon\Carbon::parse($data['effective_from']);
            if ($replacing) {
                if ($from->lte($replacing->effective_from)) {
                    throw ValidationException::withMessages(['effective_from' => 'A change must start after the current terms started (' . $replacing->effective_from->format('j M Y') . ').']);
                }
                $replacing->forceFill(['effective_to' => $from->copy()->subDay()->toDateString(), 'ended_by' => auth()->id()])->save();
            }
            $agreement = CommissionAgreement::create($data + [
                'business_id' => $businessId, 'replaces_id' => $replacing?->id, 'change_reason' => $reason, 'created_by' => auth()->id(),
            ]);
            $this->forgetCache();
            return $agreement;
        });
    }

    private const LEGACY_METHODS = ['sales_value' => 'percent_collected', 'net_profit' => 'percent_net', 'flat_rate' => 'flat_per_tenant', 'tiered' => 'tiered'];

    /**
     * Keep an agent's own agreement (and their override as a primary manager) in step with the commission
     * settings on the agent profile. A change ends the current version yesterday and starts a new one today;
     * the first version starts on $firstFrom (default: the day the agent was added). Returns versions created.
     */
    public function syncFromAgent(\App\Models\Agent $agent, ?string $firstFrom = null, ?string $reason = null): int
    {
        $created = 0;
        $agent->loadMissing('commissionTiers');
        $method = self::LEGACY_METHODS[$agent->commission_type] ?? null;
        $wanted = [
            'agent' => $method ? [
                'method' => $method,
                'rate' => $method === 'flat_per_tenant' ? 0.0 : (float) $agent->commission_rate,
                'flat_amount' => $method === 'flat_per_tenant' ? (float) $agent->commission_rate : null,
                'tiers' => $method === 'tiered' ? $agent->commissionTiers->sortBy('min_value')->map(fn ($t) => ['min' => (float) $t->min_value, 'max' => $t->max_value === null ? null : (float) $t->max_value, 'rate' => (float) $t->rate])->values()->all() : null,
                'sharing' => 'separate',
            ] : null,
            'primary_on_delegated' => (float) $agent->override_rate > 0
                ? ['method' => 'percent_collected', 'rate' => (float) $agent->override_rate, 'flat_amount' => null, 'tiers' => null, 'sharing' => 'separate'] : null,
        ];

        foreach ($wanted as $entitlement => $terms) {
            $current = CommissionAgreement::where('business_id', $agent->created_by)->where('scope', 'agent')->where('agent_id', $agent->id)
                ->where('entitlement', $entitlement)->whereNull('effective_to')->latest('effective_from')->latest('id')->first();
            $same = $current && $terms && $current->method === $terms['method'] && abs((float) $current->rate - $terms['rate']) < 0.0005
                && (float) $current->flat_amount === (float) $terms['flat_amount'] && ($current->tiers ?: null) == ($terms['tiers'] ?: null);
            if ($same || (!$current && !$terms)) {
                continue;
            }
            $today = now()->toDateString();
            if ($current && $current->effective_from->toDateString() >= $today) {
                // Changed again on the day it started: replace that version's terms (nothing earned under it yet would differ in date).
                if ($terms) {
                    $current->update($terms);
                } elseif (\App\Models\CommissionEntry::where('agreement_id', $current->id)->exists()) {
                    $this->end($current, $today);
                } else {
                    $current->delete();
                }
                $this->forgetCache();
                continue;
            }
            if ($current) {
                $this->end($current, now()->subDay()->toDateString());
            }
            if ($terms) {
                CommissionAgreement::create($terms + [
                    'business_id' => $agent->created_by, 'scope' => 'agent', 'agent_id' => $agent->id, 'entitlement' => $entitlement,
                    'name' => $entitlement === 'agent' ? 'From the agent profile' : 'Override from the agent profile',
                    'funding' => 'company_fee', 'applies_to' => ['rent'],
                    'effective_from' => $current ? $today : ($firstFrom ?? optional($agent->created_at)->toDateString() ?? $today),
                    'replaces_id' => $current?->id, 'change_reason' => $reason ?? ($current ? 'Changed on the agent profile' : 'Set on the agent profile'),
                    'created_by' => auth()->id(),
                ]);
                $created++;
            }
            $this->forgetCache();
        }
        return $created;
    }

    /** End an agreement from a date (it stays on record). */
    public function end(CommissionAgreement $agreement, string $lastDay): void
    {
        $agreement->forceFill(['effective_to' => $lastDay, 'ended_by' => auth()->id()])->save();
        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        $this->cache = [];
    }

    private function normalise(array $data): array
    {
        $data['method'] ??= 'percent_collected';
        $data['funding'] ??= 'company_fee';
        $data['sharing'] ??= 'separate';
        $data['applies_to'] = array_values(array_intersect($data['applies_to'] ?? ['rent'], ['rent', 'deposit', 'service_charge', 'late_fee'])) ?: ['rent'];
        // Sales commission is its own entitlement: a sale method, on house sales, paid by the business.
        if (($data['entitlement'] === 'sale') !== in_array($data['method'], CommissionAgreement::SALE_METHODS, true)) {
            throw ValidationException::withMessages(['method' => $data['entitlement'] === 'sale'
                ? 'Sales commission is a percentage of the sale price or a flat amount per house sold.'
                : 'Percentage of the sale price and flat per house sold are for sales commission only.']);
        }
        if ($data['entitlement'] === 'sale') {
            $data['applies_to'] = ['sale'];
            $data['funding'] = 'additional_cost';
            $data['sharing'] = 'separate';
            if ($data['method'] === 'flat_per_sale' && !((float) ($data['flat_amount'] ?? 0) > 0)) {
                throw ValidationException::withMessages(['flat_amount' => 'Enter the amount per house sold.']);
            }
        }
        if ($data['entitlement'] === 'management_fee') {
            $data['agent_id'] = null;
            $data['funding'] = 'company_fee';
        }
        if ($data['method'] === 'tiered') {
            $tiers = collect($data['tiers'] ?? [])->map(fn ($t) => ['min' => (float) ($t['min'] ?? 0), 'max' => ($t['max'] ?? '') === '' || $t['max'] === null ? null : (float) $t['max'], 'rate' => (float) ($t['rate'] ?? 0)])
                ->sortBy('min')->values()->all();
            if (!$tiers) {
                throw ValidationException::withMessages(['tiers' => 'Add at least one tier.']);
            }
            $data['tiers'] = $tiers;
        } else {
            $data['tiers'] = null;
        }
        return $data;
    }

    /**
     * Under "out of the company's fee", an agent's percentage cannot be more than the company's fee for the
     * same rent (one agent rate plus the primary's share on delegated units).
     */
    private function validateFunding(int $businessId, array $data): void
    {
        if ($data['entitlement'] === 'management_fee' || $data['funding'] !== 'company_fee' || !in_array($data['method'], ['percent_collected', 'percent_net'], true)) {
            return;
        }
        $fee = $this->resolve($businessId, 'management_fee', ['complex_id' => $data['complex_id'] ?? null], $data['effective_from']);
        if (!$fee || $fee->method !== 'percent_collected') {
            return; // no percentage fee to draw from: entries are recorded as an additional cost and flagged
        }
        $own = (float) $data['rate'];
        $other = 0.0;
        if ($data['entitlement'] === 'agent') {
            $other = (float) ($this->resolve($businessId, 'primary_on_delegated', $data, $data['effective_from'])?->rate ?? 0);
        } elseif ($data['sharing'] !== 'share_of_pool') {
            $other = (float) ($this->resolve($businessId, 'agent', $data, $data['effective_from'])?->rate ?? 0);
        }
        if ($own + $other > (float) $fee->rate + 0.0001) {
            throw ValidationException::withMessages(['rate' => 'Paid out of the company\'s fee, agent commission here would be ' . ($own + $other) . '% but the company\'s fee is ' . (float) $fee->rate . '%. Lower the rate, or choose "additional cost".']);
        }
    }
}
