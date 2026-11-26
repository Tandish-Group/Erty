<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\TenantInvoice;
use App\Models\TenantManagement;
use App\Models\TenantMovement;
use App\Models\TenantPayment;
use App\Models\User;
use App\Services\Commission\AgentCommissionService;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use App\Services\Tenancy\TenantMovementService;
use App\Services\Rent\TenantReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Tenant payments end to end: rent billed in advance, oldest-first allocation with credit, pending and
 * reversed payments, late fees, deposits, transfers, move-out settlement, commission and payouts.
 */
class TenantPaymentsAndCommissionTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    use \Tests\Concerns\TenantAccountsSchema;

    private TenantAccountService $account;
    private TenantChargesService $charges;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'payments_test', 'database.connections.payments_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Mail::fake();
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->schema();

        DB::table('users')->insert(['id' => 10, 'first_name' => 'Owner', 'last_name' => 'One', 'email' => 'owner@x.test']);
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Erty Homes']);
        DB::table('complexes')->insert([
            ['id' => 1, 'user_id' => 10, 'title' => 'Riverside', 'assigned_agent_id' => 1],
            ['id' => 2, 'user_id' => 10, 'title' => 'Mombasa Road', 'assigned_agent_id' => null],
        ]);
        DB::table('agents')->insert([
            ['id' => 1, 'created_by' => 10, 'first_name' => 'Jane', 'last_name' => 'Lead', 'email' => 'j@x.test', 'commission_type' => 'sales_value', 'commission_rate' => 10, 'override_rate' => 2, 'reports_to_agent_id' => null],
            ['id' => 2, 'created_by' => 10, 'first_name' => 'Kevin', 'last_name' => 'Sub', 'email' => 'k@x.test', 'commission_type' => 'sales_value', 'commission_rate' => 5, 'override_rate' => 0, 'reports_to_agent_id' => 1],
        ]);
        DB::table('complex_residential_units')->insert([
            ['id' => 1, 'complex_id' => 1, 'unit_number' => 'A-101', 'status' => 'occupied'],
            ['id' => 2, 'complex_id' => 1, 'unit_number' => 'A-102', 'status' => 'available'],
        ]);
        DB::table('commercial_units')->insert(['id' => 1, 'complex_id' => 2, 'unit_number' => 'G-01', 'unit_sq_ft' => 1000, 'min_sq_ft' => 100, 'max_subdivisions' => 4, 'status' => 'Available']);
        DB::table('tenant_profiles')->insert([
            ['id' => 1, 'tenant_type' => 'Individual', 'tenant_name' => 'Amani Otieno', 'business_name' => null, 'tenant_email' => 'amani@x.test', 'business_email' => null, 'managed_by' => 10],
            ['id' => 2, 'tenant_type' => 'Business', 'tenant_name' => 'Duka', 'business_name' => 'Duka Bora Ltd', 'tenant_email' => null, 'business_email' => 'duka@x.test', 'managed_by' => 10],
        ]);
        $this->actingAs(User::find(10));
        $this->account = app(TenantAccountService::class);
        $this->charges = app(TenantChargesService::class);
        $this->createCommissionSchema();
        // Jane is Riverside's primary manager; both agents' pay comes from their agent profiles.
        DB::table('complexes')->where('id', 1)->update(['management_type' => 'individual']);
        foreach (Agent::all() as $agent) {
            app(\App\Services\Commission\CommissionAgreementService::class)->syncFromAgent($agent, '2026-01-01');
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Charges ──────────────────────────────────────────────────────────

    public function test_move_in_bills_the_deposit_and_first_rent_in_advance(): void
    {
        $t = $this->tenancy(['grace_period_days' => 5]);
        $issued = $this->charges->startBilling($t);

        $this->assertSame(['deposit', 'rent'], $issued->pluck('kind')->all());
        $rent = $issued->firstWhere('kind', 'rent');
        $this->assertSame('2026-11-15', $rent->billing_period_start->toDateString());
        $this->assertSame('2026-12-14', $rent->billing_period_end->toDateString());
        $this->assertSame('2026-11-20', $rent->due_date->toDateString(), 'due = period start + grace days');
        $this->assertEquals(35000, $rent->amount);
        $this->assertEquals(70000, $this->account->balance($t));
        Mail::assertSent(\App\Mail\TenantChargesMail::class);

        // Next period is issued 4 days before it starts, once.
        $this->assertCount(0, $this->charges->billUpTo($t, Carbon::parse('2026-12-10')));
        $next = $this->charges->billUpTo($t, Carbon::parse('2026-12-11'));
        $this->assertCount(1, $next);
        $this->assertSame('2026-12-15', $next->first()->billing_period_start->toDateString());
        $this->assertCount(0, $this->charges->billUpTo($t, Carbon::parse('2026-12-12')));
    }

    public function test_a_quarterly_tenancy_is_billed_three_months_in_advance(): void
    {
        $t = $this->tenancy(['payment_frequency' => 'Quarterly', 'tenancy_quarterly_payment_type' => 'pre_pay', 'security_deposit' => 0]);
        $rent = $this->charges->startBilling($t)->first();
        $this->assertEquals(105000, $rent->amount);
        $this->assertSame('2027-02-14', $rent->billing_period_end->toDateString());
        $this->assertSame('2026-11-15', $rent->due_date->toDateString());
    }

    public function test_a_tenant_added_after_moving_in_owes_only_periods_after_rent_paid_up_to(): void
    {
        $t = $this->tenancy(['move_in_date' => '2026-07-15', 'lease_start_date' => '2026-07-15', 'rent_paid_up_to' => '2026-10-14', 'security_deposit' => 0]);
        $issued = $this->charges->startBilling($t);
        // Jul, Aug, Sep periods are settled before Erty; Oct 15 and Nov 15 periods are invoiced.
        $this->assertSame(['2026-10-15', '2026-11-15'], $issued->map(fn ($i) => $i->billing_period_start->toDateString())->all());
        $this->assertSame(3, DB::table('tenant_tenancy_billings')->where('tenancy_id', $t->id)->where('status', 'Settled before Erty')->count());
        $this->assertEquals(70000, $this->account->balance($t));
    }

    public function test_a_rent_change_applies_from_the_next_invoice(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        $this->charges->startBilling($t);
        $t->update(['monthly_payment' => 40000]);
        $next = $this->charges->billUpTo($t->fresh(), Carbon::parse('2026-12-15'));
        $this->assertEquals(40000, $next->first()->amount);
        $this->assertEquals(35000, TenantInvoice::where('tenancy_id', $t->id)->orderBy('id')->first()->amount);
    }

    public function test_a_commercial_tenancy_gets_a_separate_service_charge_invoice(): void
    {
        $t = $this->tenancy(['complex_id' => 2, 'complex_name' => 'Mombasa Road', 'residential_unit_id' => null, 'commercial_unit_id' => 1, 'unit_number' => 'G-01',
            'occupied_sq_area' => 500, 'service_charge_per_sq_ft' => 10, 'monthly_payment' => 60000, 'security_deposit' => 0, 'tenant_profile_id' => 2, 'assigned_agent_id' => 2]);
        $issued = $this->charges->startBilling($t);
        $this->assertSame(['rent', 'service_charge'], $issued->pluck('kind')->all());
        $this->assertEquals(5000, $issued->firstWhere('kind', 'service_charge')->amount);

        $this->account->record($t, $this->pay(65000));
        $row = app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-11-30');
        $this->assertEquals(60000, $row['total_payments'], 'commission is on rent only, not the service charge');
    }

    // ─── Payments ─────────────────────────────────────────────────────────

    public function test_full_partial_and_oldest_first_payments(): void
    {
        $t = $this->tenancy(['assigned_agent_id' => 2]);
        [$deposit, $rent] = $this->charges->startBilling($t)->all();

        $this->account->record($t, $this->pay(20000));
        $this->assertSame(TenantInvoice::PARTIAL, $deposit->fresh()->status, 'oldest first: the deposit is due on move-in');
        $this->assertSame(TenantInvoice::UNPAID, $rent->fresh()->status);

        $this->account->record($t, $this->pay(50000));
        $this->assertSame(TenantInvoice::PAID, $deposit->fresh()->status);
        $this->assertSame(TenantInvoice::PAID, $rent->fresh()->status);
        $this->assertEquals(35000, $rent->fresh()->paid_amount);
        $this->assertEquals(0, $this->account->balance($t));
        $this->assertEquals(35000, $t->fresh()->deposit_paid);

        $allocation = DB::table('tenant_invoice_payment_allocations')->where('invoice_id', $rent->id)->first();
        $this->assertSame(2, (int) $allocation->agent_id);
        $this->assertSame(1, (int) $allocation->override_agent_id, 'Kevin reports to Jane, the lead agent of Riverside');
    }

    public function test_a_payment_for_several_months_is_held_as_credit_and_used_on_the_next_invoices(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        $this->charges->startBilling($t);
        $payment = $this->account->record($t, $this->pay(105000)); // three months
        $this->assertEquals(70000, $payment->unallocated_amount);
        $this->assertEquals(-70000, $this->account->balance($t));

        Carbon::setTestNow('2026-12-11 08:00');
        $dec = $this->charges->billUpTo($t, Carbon::now())->first();
        $this->assertSame(TenantInvoice::PAID, $dec->fresh()->status, 'credit settles the new invoice as it is issued');
        $this->assertEquals(35000, $this->account->credit($t));
    }

    public function test_a_pending_cheque_counts_only_once_confirmed_and_a_bounced_one_is_reversed(): void
    {
        $t = $this->tenancy(['security_deposit' => 0, 'assigned_agent_id' => 2]);
        $rent = $this->charges->startBilling($t)->first();
        $cheque = $this->account->record($t, $this->pay(35000, ['payment_method' => 'check', 'pending' => true]));
        $this->assertSame(TenantInvoice::UNPAID, $rent->fresh()->status);
        $this->assertEquals(0, app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-11-30')['total_payments']);

        $this->account->confirm($cheque);
        $this->assertSame(TenantInvoice::PAID, $rent->fresh()->status);
        $this->assertEquals(35000, app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-11-30')['total_payments']);

        $this->account->reverse($cheque->fresh(), 'Cheque bounced');
        $this->assertSame(TenantInvoice::UNPAID, $rent->fresh()->status);
        $this->assertEquals(35000, $this->account->balance($t));
        $this->assertEquals(0, app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-11-30')['total_payments']);

        $this->expectException(ValidationException::class);
        $this->account->reverse($cheque->fresh(), 'again');
    }

    public function test_a_late_fee_is_added_once_after_the_due_date_and_can_be_waived(): void
    {
        $t = $this->tenancy(['security_deposit' => 0, 'grace_period_days' => 5, 'late_fee_amount' => 2000]);
        $rent = $this->charges->startBilling($t)->first();
        $this->assertCount(0, $this->charges->applyLateFees($t, Carbon::parse('2026-11-20')), 'not late on the due date');
        $fees = $this->charges->applyLateFees($t, Carbon::parse('2026-11-21'));
        $this->assertCount(1, $fees);
        $this->assertSame($rent->id, $fees->first()->related_invoice_id);
        $this->assertCount(0, $this->charges->applyLateFees($t, Carbon::parse('2026-11-25')), 'only once per invoice');
        $this->assertEquals(37000, $this->account->balance($t));

        $this->account->void($fees->first(), 'First time, goodwill');
        $this->assertSame('Waived', $fees->first()->fresh()->display_status);
        $this->assertEquals(35000, $this->account->balance($t));
    }

    public function test_a_deposit_paid_in_two_parts(): void
    {
        $t = $this->tenancy();
        [$deposit] = $this->charges->startBilling($t)->all();
        $this->account->record($t, $this->pay(15000, ['first_invoice_id' => $deposit->id]));
        $this->assertEquals(15000, $this->account->depositHeld($t));
        $this->account->record($t, $this->pay(20000, ['first_invoice_id' => $deposit->id]));
        $this->assertSame(TenantInvoice::PAID, $deposit->fresh()->status);
        $this->assertEquals(35000, $t->fresh()->deposit_paid);
        $this->assertEquals(35000, $this->account->balance($t), 'only the rent is still owed');
    }

    public function test_two_tenancies_of_one_tenant_have_separate_accounts(): void
    {
        $flat = $this->tenancy(['security_deposit' => 0]);
        $shop = $this->tenancy(['complex_id' => 2, 'residential_unit_id' => null, 'commercial_unit_id' => 1, 'occupied_sq_area' => 400, 'monthly_payment' => 40000, 'security_deposit' => 0]);
        $this->charges->startBilling($flat);
        $this->charges->startBilling($shop);
        $this->account->record($flat, $this->pay(35000));
        $this->assertEquals(0, $this->account->balance($flat));
        $this->assertEquals(40000, $this->account->balance($shop));
    }

    public function test_a_duplicate_reference_is_refused(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(1000, ['transaction_reference' => 'QJK12XYZ']));
        $this->expectException(ValidationException::class);
        $this->account->record($t, $this->pay(1000, ['transaction_reference' => 'QJK12XYZ']));
    }

    // ─── Transfers ────────────────────────────────────────────────────────

    public function test_a_mid_period_transfer_to_a_dearer_unit(): void
    {
        $t = $this->tenancy(['assigned_agent_id' => 2, 'grace_period_days' => 3, 'late_fee_amount' => 1000]);
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(50000)); // deposit paid and 15,000 of the rent; 20,000 still owed

        Carbon::setTestNow('2026-11-30 09:00');
        $movement = $this->movement($t, ['move_type' => 'mid_month', 'transfer_date' => '2026-11-30', 'new_rent' => 45000, 'new_deposit' => 45000]);
        $done = app(TenantMovementService::class)->executeMovement($movement);
        $new = $done->newTenancy;

        $this->assertSame('completed', $done->status);
        $this->assertStringStartsWith('TM', $new->tenancy_ref);
        $this->assertSame($t->id, (int) $new->previous_tenancy_id);
        $this->assertSame(2, (int) $new->assigned_agent_id, 'the agent stays with the tenant');
        $this->assertSame(3, (int) $new->grace_period_days);
        $this->assertSame('2026-12-15', $new->billing_start_date->toDateString(), 'same billing cycle');
        $this->assertSame('Transferred', $t->fresh()->status);
        $this->assertSame('available', DB::table('complex_residential_units')->where('id', 1)->value('status'));
        $this->assertSame('occupied', DB::table('complex_residential_units')->where('id', 2)->value('status'));

        // 15 of 30 days left: new rent 22,500 less unused old rent 17,500 = 5,000; deposit top-up 10,000.
        $adjust = TenantInvoice::where('tenancy_id', $new->id)->where('kind', 'adjustment')->first();
        $this->assertEquals(5000, $adjust->amount);
        $this->assertEquals(10000, TenantInvoice::where('tenancy_id', $new->id)->where('kind', 'deposit')->value('amount'));
        $this->assertEquals(20000 + 5000 + 10000, $this->account->balance($new), 'the old balance stays payable');
        $this->assertEquals(35000, $this->account->depositHeld($new), 'the deposit moved with the tenant');

        // A payment on the new tenancy settles the old unit's rent first.
        $this->account->record($new, $this->pay(20000));
        $this->assertSame(TenantInvoice::PAID, TenantInvoice::where('tenancy_id', $t->id)->where('kind', 'rent')->first()->status);
    }

    public function test_an_end_of_period_transfer_to_a_lower_deposit_leaves_credit(): void
    {
        $t = $this->tenancy();
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(70000));

        $service = app(TenantMovementService::class);
        $date = $service->calculateTransferDates('end_of_month', null, $t)['transfer_date'];
        $this->assertSame('2026-12-15', $date, 'the next period start');
        Carbon::setTestNow('2026-12-15 08:00');
        $done = $service->executeMovement($this->movement($t, ['move_type' => 'end_of_month', 'transfer_date' => $date, 'new_rent' => 30000, 'new_deposit' => 30000]));
        $new = $done->newTenancy;

        $this->assertSame(0, TenantInvoice::where('tenancy_id', $new->id)->where('kind', 'adjustment')->count(), 'no proration at a period boundary');
        $rent = TenantInvoice::where('tenancy_id', $new->id)->where('kind', 'rent')->first();
        $this->assertEquals(30000, $rent->amount);
        $this->assertSame(TenantInvoice::PARTIAL, $rent->fresh()->status, 'the 5,000 deposit difference was used on the new rent');
        $this->assertEquals(30000, $this->account->depositHeld($new));
        $this->assertEquals(25000, $this->account->balance($new));
    }

    public function test_a_future_dated_transfer_waits_for_its_date(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        $this->charges->startBilling($t);
        $service = app(TenantMovementService::class);
        $movement = $this->movement($t, ['move_type' => 'end_of_month', 'transfer_date' => '2026-12-15', 'new_rent' => 35000, 'new_deposit' => 0]);
        $this->assertSame('approved', $service->executeMovement($movement)->status);
        $this->assertSame('Active', $t->fresh()->status);

        $this->assertSame(0, $service->executeDue(Carbon::parse('2026-12-14')));
        Carbon::setTestNow('2026-12-15 06:00');
        $this->assertSame(1, $service->executeDue(Carbon::parse('2026-12-15')));
        $this->assertSame('completed', $movement->fresh()->status);
        $this->assertSame('Transferred', $t->fresh()->status);
    }

    public function test_a_transfer_to_a_unit_that_is_taken_is_refused(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        $this->tenancy(['residential_unit_id' => 2, 'unit_number' => 'A-102', 'tenant_profile_id' => 2]);
        $this->expectException(ValidationException::class);
        app(TenantMovementService::class)->executeMovement($this->movement($t, ['move_type' => 'mid_month', 'transfer_date' => '2026-11-15', 'new_rent' => 35000, 'new_deposit' => 0]));
    }

    // ─── Move-out ─────────────────────────────────────────────────────────

    public function test_the_deposit_settles_what_is_owed_and_the_rest_can_be_refunded(): void
    {
        $t = $this->tenancy(['assigned_agent_id' => 2]);
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(35000, ['first_invoice_id' => TenantInvoice::where('kind', 'deposit')->value('id')]));
        $this->charges->charge($t, 'adjustment', 5000, 'Damages at move-out');

        $owed = $this->account->outstanding($t); // 35,000 rent + 5,000 damages
        $this->assertEquals(40000, $owed);
        $this->account->releaseDeposit($t, min($this->account->depositHeld($t), $owed), '2026-11-30', 'Deposit applied at move-out');
        $this->assertEquals(0, $this->account->depositHeld($t));
        $this->assertEquals(5000, $this->account->balance($t), 'the shortfall stays on the statement');

        // Rent settled from the deposit counts as collected for the agent.
        $this->assertEquals(35000, app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-11-30')['total_payments']);

        $this->expectException(ValidationException::class);
        $this->account->refund($t, 1, 'deposit');
    }

    public function test_moving_out_settles_the_account_to_zero(): void
    {
        $t = $this->tenancy(['assigned_agent_id' => 2]);
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(70000)); // deposit and November rent
        Carbon::setTestNow('2026-12-11 08:00');
        $this->charges->billUpTo($t->fresh(), Carbon::now()); // December rent issued in advance, unpaid

        // A dry run changes nothing.
        $preview = $this->account->settleMoveOut($t, '2026-12-12', 4000, null, null, [], dryRun: true);
        $this->assertEquals(35000, $this->account->depositHeld($t));
        $this->assertEquals(35000, $this->account->outstanding($t));

        // Leaving on 12 Dec: the period from 15 Dec is cancelled; damages 4,000 come out of the deposit; 31,000 is owed back.
        $this->assertEquals(4000, $preview['owed_before_deposit']);
        $this->assertEquals(4000, $preview['deposit_applied']);
        $this->assertEquals(0, $preview['still_owed']);
        $this->assertEquals(31000, $preview['refundable']);

        // Leaving after the next period has started (16 Dec), with damages: the deposit pays the rent first.
        $settled = $this->account->settleMoveOut($t, '2026-12-16', 4000, 'Broken door', null, ['payment_method' => 'bank_transfer']);
        $this->assertEquals(39000, $settled['owed_before_deposit']);
        $this->assertEquals(35000, $settled['deposit_applied']);
        $this->assertEquals(4000, $settled['still_owed'], 'the shortfall stays on the statement');
        $this->assertEquals(0, $settled['refund']);
        $this->assertEquals(4000, $this->account->balance($t));
        $this->assertEquals(0, $this->account->depositHeld($t));
        $this->assertEquals(70000, app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-12-31')['total_payments'], 'December rent paid from the deposit counts as collected');
    }

    public function test_a_refund_cannot_exceed_what_is_held_and_credit_is_refundable(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(40000));
        $this->account->refund($t, 5000, 'credit', ['payment_method' => 'mobile_money']);
        $this->assertEquals(0, $this->account->credit($t));
        $this->assertEquals(0, $this->account->balance($t));
        $statement = $this->account->statement($t);
        $this->assertEquals(0, $statement['closing_balance']);

        $this->expectException(ValidationException::class);
        $this->account->refund($t, 1, 'credit');
    }

    // ─── Commission ───────────────────────────────────────────────────────

    public function test_commission_counts_only_rent_collected_and_credits_the_agent_at_the_time(): void
    {
        $t = $this->tenancy(['assigned_agent_id' => 2, 'late_fee_amount' => 2000]);
        $this->charges->startBilling($t);
        $this->charges->applyLateFees($t, Carbon::parse('2026-11-16'));
        $this->account->record($t, $this->pay(72000)); // deposit 35k + rent 35k + late fee 2k

        $kevin = app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-11-30');
        $this->assertEquals(35000, $kevin['total_payments'], 'not the deposit or the late fee');
        $this->assertEquals(1750, $kevin['default_commission']);
        $jane = app(AgentCommissionService::class)->forAgent(Agent::find(1), '2026-11-01', '2026-11-30');
        $this->assertEquals(35000, $jane['override_payments']);
        $this->assertEquals(700, $jane['override_commission']);

        // Reassigning the tenancy doesn't move money already collected.
        $t->update(['assigned_agent_id' => 1]);
        Carbon::setTestNow('2026-12-11 08:00');
        $this->charges->billUpTo($t->fresh(), Carbon::now());
        $this->account->record($t, $this->pay(35000, ['payment_date' => '2026-12-11']));
        $this->assertEquals(35000, app(AgentCommissionService::class)->forAgent(Agent::find(2), '2026-11-01', '2026-12-31')['total_payments']);
        $this->assertEquals(35000, app(AgentCommissionService::class)->forAgent(Agent::find(1), '2026-11-01', '2026-12-31')['total_payments']);
    }

    public function test_net_profit_only_counts_approved_expenses_of_the_agents_own_properties(): void
    {
        // Jane is paid on net profit for the whole year (period-based commission).
        DB::table('agents')->where('id', 1)->update(['commission_type' => 'net_profit']);
        \App\Models\CommissionAgreement::where('agent_id', 1)->where('entitlement', 'agent')->delete();
        app(\App\Services\Commission\CommissionAgreementService::class)->syncFromAgent(Agent::find(1), '2026-01-01');
        $t = $this->tenancy(['assigned_agent_id' => 1, 'security_deposit' => 0]);
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(35000));
        DB::table('complex_expenses')->insert([
            ['complex_id' => 1, 'amount' => 5000, 'expense_date' => '2026-11-10', 'workflow_status' => 'approved'],
            ['complex_id' => 1, 'amount' => 9000, 'expense_date' => '2026-11-10', 'workflow_status' => 'draft'],
            ['complex_id' => 2, 'amount' => 7000, 'expense_date' => '2026-11-10', 'workflow_status' => 'approved'],
        ]);
        // Period commission is calculated for the month and approved before it counts.
        $periods = app(\App\Services\Commission\CommissionPeriodService::class);
        $periods->calculate(10, '2026-11-01', '2026-11-30');
        $this->assertEquals(3000, app(AgentCommissionService::class)->forAgent(Agent::find(1), '2026-11-01', '2026-11-30')['pending_commission'], 'waiting for approval');
        app(\App\Services\Commission\CommissionLedgerService::class)->approve(\App\Models\CommissionEntry::where('kind', 'period')->pluck('id')->all(), 10);

        $row = app(AgentCommissionService::class)->forAgent(Agent::find(1), '2026-11-01', '2026-11-30');
        $this->assertEquals(5000, $row['total_expenses']);
        $this->assertEquals(3000, $row['default_commission']); // 10% of 30,000
    }

    public function test_a_payout_is_not_paid_twice_and_a_later_reversal_is_clawed_back(): void
    {
        $service = app(AgentCommissionService::class);
        $t = $this->tenancy(['assigned_agent_id' => 2, 'security_deposit' => 0]);
        $this->charges->startBilling($t);
        $payment = $this->account->record($t, $this->pay(35000));

        $statement = $service->statement(Agent::find(2), '2026-11-01', '2026-11-30');
        $this->assertEquals(1750, $statement['due']['total']);
        $payout = $service->recordPayout(Agent::find(2), '2026-11-01', '2026-11-30', ['method' => 'bank_transfer', 'reference' => 'PAY-1']);
        $this->assertEquals(1750, $payout->total);
        $this->assertEquals(0, $service->statement(Agent::find(2), '2026-11-01', '2026-11-30')['due']['total']);

        // Jane's override is paid separately.
        $this->assertEquals(700, $service->statement(Agent::find(1), '2026-11-01', '2026-11-30')['due']['override']);

        $this->account->reverse($payment, 'Entered against the wrong tenant');
        $after = $service->statement(Agent::find(2), '2026-12-01', '2026-12-31');
        $this->assertEquals(1750, $after['due']['clawback']);
        $this->assertEquals(-1750, $after['due']['total']);

        $this->expectException(ValidationException::class);
        $service->recordPayout(Agent::find(2), '2026-12-01', '2026-12-31', []);
    }

    // ─── Reports ──────────────────────────────────────────────────────────

    public function test_reports_separate_deposits_and_age_arrears(): void
    {
        $t = $this->tenancy(['grace_period_days' => 0]);
        $this->charges->startBilling($t);
        $this->account->record($t, $this->pay(35000, ['first_invoice_id' => TenantInvoice::where('kind', 'deposit')->value('id')]));
        Carbon::setTestNow('2026-12-20 09:00');

        $reports = app(TenantReportService::class);
        $summary = $reports->getSummary('2026-11-01', '2026-11-30');
        $this->assertEquals(35000, $summary['total_expected'], 'the deposit is not billed revenue');
        $this->assertEquals(0, $summary['total_collected']);
        $this->assertEquals(35000, $summary['deposits_held']);
        $this->assertEquals(35000, $summary['cash_received']);
        $this->assertEquals(35000, $summary['aging']['31–60 days']);
        $arrears = $reports->getArrears('2026-11-01', '2026-11-30');
        $this->assertSame('31–60 days', $arrears[0]['bucket']);
        $this->assertCount(1, $reports->getAccounts());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────
}
