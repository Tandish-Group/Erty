<?php

namespace App\Services\Sales;

use App\Services\Agents\AgentAssignmentService;
use App\Services\Commission\CommissionLedgerService;
use App\Services\NotificationCenter;
use App\Services\Properties\ComplexInventoryService;

use App\Models\{Agent, ComplexHistory, ComplexResidentialUnit, HistoryCategory, Lead, PropertySale, PropertySaleInstalment, PropertySalePayment, PropertySalePaymentAllocation, TenantManagement};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Selling a house, kept apart from tenant billing:
 * - start: buyer, price, agreement date and a payment plan (deposit + instalments). The house goes under offer.
 * - payments: each buyer payment gets a receipt and is spread over the plan oldest first; the agent on the sale
 *   earns sales commission as the money comes in (CommissionLedgerService::recordSalePayment).
 * - reverse: a payment entered in error or bounced is reversed (never edited), with its commission.
 * - complete: the house is sold (recorded in the property history); cancel: it goes back on the market.
 */
class PropertySaleService
{
    public function __construct(private CommissionLedgerService $ledger, private NotificationCenter $notify) {}

    /** Why this house cannot be sold right now, or null when it can. */
    public function blocker(ComplexResidentialUnit $unit): ?string
    {
        if (!$unit->isHouse()) return 'Only houses can be sold.';
        if (!$unit->forSale()) return 'This house is offered for rent only. Mark it for sale (or for rent or sale) first.';
        if ($unit->liveSale()->exists()) return 'This house already has a sale in progress.';
        if ($unit->status === 'sold') return 'This house has already been sold.';
        $tenancy = TenantManagement::withoutGlobalScopes()->where('residential_unit_id', $unit->id)->whereIn('status', ['Active', 'Pending', 'On Notice'])->first();
        if ($tenancy) return 'This house has a ' . strtolower($tenancy->status) . ' tenancy. End it first (give notice with the reason "property sale").';
        if ($unit->status !== 'available') return 'This house is recorded as ' . (ComplexInventoryService::RESIDENTIAL_STATUS_LABELS[$unit->status] ?? $unit->status) . '. Make it available before selling it.';
        return null;
    }

    /**
     * Payment plan: an optional deposit (sequence 0), then the balance in equal instalments (the last one takes the
     * rounding), or a custom list of [due_date, amount] rows that must add up to the balance.
     */
    public function schedule(float $price, float $deposit, string $depositDue, int $count, ?string $firstDue, ?array $custom = null, string $every = 'monthly'): array
    {
        if ($price <= 0) throw ValidationException::withMessages(['price' => 'Enter the sale price.']);
        if ($deposit < 0 || $deposit > $price) throw ValidationException::withMessages(['deposit_amount' => 'The deposit must be between zero and the sale price.']);
        $rows = [];
        if ($deposit > 0) {
            $rows[] = ['label' => 'Deposit', 'due_date' => Carbon::parse($depositDue)->toDateString(), 'amount' => round($deposit, 2)];
        }
        $balance = round($price - $deposit, 2);
        if ($balance > 0) {
            if ($custom) {
                $custom = array_values(array_filter($custom, fn ($r) => ($r['amount'] ?? '') !== '' && ($r['due_date'] ?? '') !== ''));
                $sum = round(array_sum(array_map(fn ($r) => (float) $r['amount'], $custom)), 2);
                if (abs($sum - $balance) > 0.009) {
                    throw ValidationException::withMessages(['schedule' => 'The instalments add up to ' . number_format($sum, 2) . ' but the balance after the deposit is ' . number_format($balance, 2) . '.']);
                }
                foreach ($custom as $i => $r) {
                    if ((float) $r['amount'] <= 0) throw ValidationException::withMessages(['schedule' => 'Each instalment must be more than zero.']);
                    $rows[] = ['label' => 'Instalment ' . ($i + 1), 'due_date' => Carbon::parse($r['due_date'])->toDateString(), 'amount' => round((float) $r['amount'], 2)];
                }
            } else {
                if ($count < 1 || $count > 360) throw ValidationException::withMessages(['instalments' => 'Choose between 1 and 360 instalments for the balance.']);
                if (!$firstDue) throw ValidationException::withMessages(['first_due_date' => 'Enter when the first instalment is due.']);
                $each = floor($balance / $count * 100) / 100;
                // Each due date counts from the first, so a 31st stays the last day of shorter months without drifting.
                $first = Carbon::parse($firstDue);
                $step = ['quarterly' => 3, 'yearly' => 12][$every] ?? 1;
                $lastDay = $first->day === $first->daysInMonth;
                for ($i = 1; $i <= $count; $i++) {
                    $amount = $i === $count ? round($balance - $each * ($count - 1), 2) : $each;
                    $due = $first->copy()->addMonthsNoOverflow(($i - 1) * $step);
                    if ($lastDay) $due = $due->endOfMonth();
                    $rows[] = ['label' => $count === 1 ? 'Balance' : 'Instalment ' . $i . ' of ' . $count, 'due_date' => $due->toDateString(), 'amount' => $amount];
                }
            }
        }
        usort($rows, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']));
        if ($deposit > 0 && $rows[0]['label'] !== 'Deposit') {
            throw ValidationException::withMessages(['schedule' => 'The deposit must be due before the first instalment.']);
        }
        return $rows;
    }

    /** @param array $data buyer_*, price, agreement_date, deposit_amount, deposit_due_date, instalments, first_due_date, frequency, schedule, agent_id, lead_id, notes */
    public function start(ComplexResidentialUnit $unit, array $data): PropertySale
    {
        return DB::transaction(function () use ($unit, $data) {
            $unit = ComplexResidentialUnit::withoutGlobalScopes()->lockForUpdate()->findOrFail($unit->id);
            if ($why = $this->blocker($unit)) throw ValidationException::withMessages(['unit' => $why]);
            $complex = $unit->complex()->withoutGlobalScopes()->firstOrFail();
            $ownerId = (int) $complex->user_id;

            $agentId = $data['agent_id'] ?? null;
            if ($agentId) {
                abort_unless(Agent::where('created_by', $ownerId)->whereKey($agentId)->exists(), 422, 'Choose one of your agents.');
            } else {
                $agentId = app(AgentAssignmentService::class)->defaultAgentFor($complex, $unit)?->id;
            }
            $lead = null;
            if (!empty($data['lead_id'])) {
                $lead = Lead::withoutGlobalScopes()->forBusiness($ownerId)->find($data['lead_id']);
                if (!$lead) throw ValidationException::withMessages(['lead_id' => 'That lead is not in this business.']);
                if ($lead->converted_tenant_id || $lead->converted_sale_id) throw ValidationException::withMessages(['lead_id' => 'That lead has already been converted.']);
            }

            $price = round((float) $data['price'], 2);
            $deposit = round((float) ($data['deposit_amount'] ?? 0), 2);
            $agreed = Carbon::parse($data['agreement_date'])->toDateString();
            $plan = $this->schedule($price, $deposit, $data['deposit_due_date'] ?? $agreed, (int) ($data['instalments'] ?? 0), $data['first_due_date'] ?? null,
                ($data['plan'] ?? 'equal') === 'custom' ? ($data['schedule'] ?? []) : null, $data['frequency'] ?? 'monthly');

            $sale = PropertySale::create([
                'business_id' => $ownerId, 'complex_id' => $complex->id, 'residential_unit_id' => $unit->id,
                'listing_id' => DB::table('listings')->where('complex_unit_id', $unit->complex_unit_id)->value('id'),
                'lead_id' => $lead?->id, 'agent_id' => $agentId, 'reference' => $this->reference(),
                'buyer_name' => trim($data['buyer_name']), 'buyer_phone' => $data['buyer_phone'] ?? null, 'buyer_email' => $data['buyer_email'] ?? null,
                'buyer_id_number' => $data['buyer_id_number'] ?? null, 'buyer_kra_pin' => $data['buyer_kra_pin'] ?? null,
                'price' => $price, 'currency' => default_currency(), 'agreement_date' => $agreed, 'deposit_amount' => $deposit,
                'status' => $deposit > 0 ? 'reserved' : 'agreed', 'notes' => $data['notes'] ?? null, 'created_by' => auth()->id(),
            ]);
            foreach ($plan as $i => $row) {
                PropertySaleInstalment::create($row + ['sale_id' => $sale->id, 'sequence' => $i, 'number' => $sale->reference . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
            }
            $unit->forceFill(['status' => 'under_offer'])->save();
            app(ComplexInventoryService::class)->audit($complex, 'house_sale:' . $unit->id, 'available', ['sale' => $sale->reference, 'buyer' => $sale->buyer_name, 'price' => $price]);
            if ($lead) {
                app(\App\Services\Crm\LeadConversionService::class)->markSold($lead, $sale);
            }
            $this->notify->saleStarted($sale);
            return $sale;
        });
    }

    /** Record a buyer payment, spread it over the plan oldest first, and earn the agent's sales commission on it. */
    public function recordPayment(PropertySale $sale, array $data): PropertySalePayment
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale = PropertySale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            if (!$sale->isLive()) throw ValidationException::withMessages(['amount' => 'This sale is ' . strtolower($sale->statusLabel()) . '; payments can only be recorded on a sale in progress.']);
            $amount = round((float) $data['amount'], 2);
            $open = $sale->instalments()->where('status', '!=', 'cancelled')->get()->filter(fn ($i) => $i->outstanding() > 0)->values();
            $balance = round($open->sum(fn ($i) => $i->outstanding()), 2);
            if ($amount <= 0) throw ValidationException::withMessages(['amount' => 'Enter the amount received.']);
            if ($amount > $balance + 0.004) throw ValidationException::withMessages(['amount' => 'That is more than the ' . number_format($balance, 2) . ' still owed on this sale.']);

            $count = PropertySalePayment::where('sale_id', $sale->id)->count();
            $payment = PropertySalePayment::create([
                'sale_id' => $sale->id, 'receipt_number' => $sale->reference . '-R' . str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT),
                'paid_on' => Carbon::parse($data['paid_on'])->toDateString(), 'amount' => $amount, 'method' => $data['method'],
                'reference' => $data['reference'] ?? null, 'status' => 'confirmed', 'recorded_by' => auth()->id(),
            ]);
            $left = $amount;
            foreach ($open as $instalment) {
                if ($left <= 0.004) break;
                $take = round(min($left, $instalment->outstanding()), 2);
                PropertySalePaymentAllocation::create(['payment_id' => $payment->id, 'instalment_id' => $instalment->id, 'amount' => $take]);
                $this->settle($instalment, $instalment->paid_amount + $take);
                $left = round($left - $take, 2);
            }
            // The deposit secures the house: once it is covered the sale is agreed.
            if ($sale->status === 'reserved' && !$sale->instalments()->where('sequence', 0)->where('label', 'Deposit')->whereColumn('paid_amount', '<', 'amount')->exists()) {
                $sale->forceFill(['status' => 'agreed'])->save();
            }
            $this->ledger->recordSalePayment($payment);
            $this->notify->salePaymentRecorded($sale, $payment);
            return $payment;
        });
    }

    /** Reverse a payment (bounced, entered in error): its allocations come off the plan and its commission is reversed. */
    public function reverse(PropertySalePayment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment = PropertySalePayment::lockForUpdate()->findOrFail($payment->id);
            $sale = PropertySale::withoutGlobalScopes()->lockForUpdate()->findOrFail($payment->sale_id);
            if ($payment->status !== 'confirmed') throw ValidationException::withMessages(['payment' => 'This payment has already been reversed.']);
            if ($sale->status === 'completed') throw ValidationException::withMessages(['payment' => 'This sale is completed. Record a correction with your accountant instead.']);
            foreach ($payment->allocations as $allocation) {
                $instalment = PropertySaleInstalment::lockForUpdate()->find($allocation->instalment_id);
                if ($instalment) $this->settle($instalment, max(0, $instalment->paid_amount - $allocation->amount), $instalment->status === 'cancelled');
            }
            $payment->forceFill(['status' => 'reversed', 'reversed_by' => auth()->id(), 'reversed_at' => now(), 'reversal_reason' => $reason])->save();
            $this->ledger->reverseSalePayment($payment, $reason);
            $this->notify->salePaymentReversed($sale, $payment, $reason);
        });
    }

    /** The house is sold. The balance must be paid, unless someone confirms it was settled outside Erty (e.g. a mortgage paid the seller directly). */
    public function complete(PropertySale $sale, ?string $note = null): PropertySale
    {
        return DB::transaction(function () use ($sale, $note) {
            $sale = PropertySale::withoutGlobalScopes()->with(['payments', 'instalments'])->lockForUpdate()->findOrFail($sale->id);
            if (!$sale->isLive()) throw ValidationException::withMessages(['sale' => 'Only a sale in progress can be completed.']);
            $balance = $sale->balance();
            if ($balance > 0.004 && mb_strlen(trim((string) $note)) < 10) {
                throw ValidationException::withMessages(['completion_note' => number_format($balance, 2) . ' is still owed. Explain how it was settled (at least 10 characters) to complete the sale anyway.']);
            }
            $sale->forceFill(['status' => 'completed', 'completed_at' => now(), 'completion_note' => $note ?: null])->save();
            $unit = ComplexResidentialUnit::withoutGlobalScopes()->lockForUpdate()->find($sale->residential_unit_id);
            $unit?->forceFill(['status' => 'sold'])->save();
            $complex = $sale->complex()->withoutGlobalScopes()->first();
            app(ComplexInventoryService::class)->audit($complex, 'house_sold:' . $sale->residential_unit_id, 'under_offer', ['sale' => $sale->reference, 'buyer' => $sale->buyer_name, 'price' => $sale->price]);
            // The property history keeps a sale record for later owners and buyers.
            if ($category = HistoryCategory::where('tag', 'sales_rental')->first()) {
                ComplexHistory::create([
                    'complex_id' => $sale->complex_id, 'complex_residential_unit_id' => $sale->residential_unit_id, 'history_category_id' => $category->id,
                    'category_tag' => 'sales_rental', 'created_by' => auth()->id(), 'files' => [],
                    'data' => ['record_type' => 'sale_record', 'price_rate' => $sale->price, 'date' => now()->toDateString(),
                        'tenant_buyer_info' => $sale->buyer_name, 'payment_method' => $sale->payments->where('status', 'confirmed')->pluck('method')->unique()->count() === 1 ? $sale->payments->firstWhere('status', 'confirmed')?->method : 'other',
                        'sale_reference' => $sale->reference],
                    'notes' => 'Recorded by Erty when sale ' . $sale->reference . ' completed.' . ($note ? ' ' . $note : ''),
                ]);
            }
            $this->notify->saleCompleted($sale);
            return $sale;
        });
    }

    public function titleTransferred(PropertySale $sale, string $date): void
    {
        if ($sale->status !== 'completed') throw ValidationException::withMessages(['title_transferred_at' => 'Complete the sale before recording the title transfer.']);
        $sale->forceFill(['title_transferred_at' => Carbon::parse($date)->toDateString()])->save();
    }

    /**
     * Cancel a sale: the house goes back on the market and open instalments are cancelled. With $refund, every
     * payment is reversed (the money was given back); otherwise payments stay on record (e.g. a forfeited deposit).
     */
    public function cancel(PropertySale $sale, string $reason, bool $refund = false): PropertySale
    {
        return DB::transaction(function () use ($sale, $reason, $refund) {
            $sale = PropertySale::withoutGlobalScopes()->lockForUpdate()->findOrFail($sale->id);
            if (!$sale->isLive()) throw ValidationException::withMessages(['sale' => 'Only a sale in progress can be cancelled.']);
            if ($refund) {
                foreach ($sale->payments()->where('status', 'confirmed')->get() as $payment) {
                    $this->reverse($payment, 'Refunded: sale cancelled. ' . $reason);
                }
            }
            $sale->instalments()->where('status', '!=', 'paid')->update(['status' => 'cancelled']);
            $sale->forceFill(['status' => 'cancelled', 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            $unit = ComplexResidentialUnit::withoutGlobalScopes()->lockForUpdate()->find($sale->residential_unit_id);
            if ($unit && $unit->status === 'under_offer') $unit->forceFill(['status' => 'available'])->save();
            if ($sale->lead_id && ($lead = Lead::withoutGlobalScopes()->find($sale->lead_id)) && (int) $lead->converted_sale_id === (int) $sale->id) {
                $lead->forceFill(['converted_sale_id' => null, 'converted_at' => null, 'status' => 'proposal'])->saveQuietly();
                $lead->addInteraction('sale_cancelled', ['sale_reference' => $sale->reference, 'reason' => $reason]);
            }
            $complex = $sale->complex()->withoutGlobalScopes()->first();
            app(ComplexInventoryService::class)->audit($complex, 'house_sale_cancelled:' . $sale->residential_unit_id, $sale->reference, ['reason' => $reason, 'refunded' => $refund]);
            $this->notify->saleCancelled($sale);
            return $sale;
        });
    }

    private function settle(PropertySaleInstalment $instalment, float $paid, bool $keepCancelled = false): void
    {
        $paid = round($paid, 2);
        $status = $keepCancelled ? 'cancelled' : ($paid >= $instalment->amount - 0.004 ? 'paid' : ($paid > 0 ? 'part_paid' : 'due'));
        $instalment->forceFill(['paid_amount' => $paid, 'status' => $status])->save();
    }

    private function reference(): string
    {
        do {
            $reference = 'SAL-' . now()->format('ymd') . '-' . Str::upper(Str::random(4));
        } while (PropertySale::withoutGlobalScopes()->where('reference', $reference)->exists());
        return $reference;
    }
}
