<?php

namespace Tests\Feature;

use App\Models\{Agent, CommissionEntry, ComplexResidentialUnit, PropertySale, User};
use App\Services\Commission\AgentCommissionService;
use App\Services\Commission\CommissionAgreementService;
use App\Services\Properties\ComplexInventoryService;
use App\Services\Access\PropertyAccess;
use App\Services\Sales\PropertySaleService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Selling houses end to end: who can be sold, the payment plan, oldest-first payments, the deposit agreeing the
 * sale, reversals, completion and cancellation, sales commission on the ledger and statements, and who sees a sale.
 */
class PropertySalesTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;
    use \Tests\Concerns\TenantAccountsSchema;

    private PropertySaleService $sales;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sales_test', 'database.connections.sales_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Mail::fake();
        Carbon::setTestNow('2026-11-18 09:00:00');
        $this->schema();
        $this->createSalesSchema();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_suspended')->default(false));
        Schema::create('business_roles', function (Blueprint $t) { $t->id(); $t->integer('business_id'); $t->string('name'); $t->string('department', 60)->nullable(); $t->json('permissions')->nullable(); $t->string('property_scope')->default('all'); $t->boolean('is_active')->default(true); $t->timestamps(); });
        Schema::create('notifications', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('type'); $t->morphs('notifiable'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });

        DB::table('users')->insert([
            ['id' => 10, 'first_name' => 'Owner', 'last_name' => 'One', 'email' => 'o@x.test', 'is_sub_account' => false, 'main_account_user_id' => null, 'business_role_id' => null],
            ['id' => 11, 'first_name' => 'Ann', 'last_name' => 'Agent', 'email' => 'a@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 1],
        ]);
        DB::table('business_roles')->insert(['id' => 1, 'business_id' => 10, 'name' => 'Agent (sales only)', 'property_scope' => 'assigned', 'permissions' => json_encode(['sales_management' => ['view', 'create']])]);
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Erty Homes']);
        DB::table('complexes')->insert([
            ['id' => 1, 'user_id' => 10, 'title' => 'Runda Estate', 'assigned_agent_id' => null],
            ['id' => 2, 'user_id' => 10, 'title' => 'Karen Villas', 'assigned_agent_id' => null],
        ]);
        DB::table('agents')->insert(['id' => 1, 'created_by' => 10, 'user_id' => 11, 'first_name' => 'Ann', 'last_name' => 'Agent', 'email' => 'a@x.test']);
        DB::table('complex_user')->insert(['complex_id' => 1, 'user_id' => 11]);
        DB::table('complex_residential_units')->insert([
            ['id' => 1, 'complex_id' => 1, 'unit_number' => '1', 'unit_category' => 'house', 'house_type' => 'Villa', 'offer' => 'sale', 'sale_price' => 30000000, 'status' => 'available', 'bedrooms' => 5, 'bathrooms' => 4, 'assigned_agent_id' => 1],
            ['id' => 2, 'complex_id' => 1, 'unit_number' => '2', 'unit_category' => 'house', 'house_type' => 'Maisonette', 'offer' => 'both', 'sale_price' => 20000000, 'status' => 'available', 'bedrooms' => 4, 'bathrooms' => 3, 'assigned_agent_id' => null],
            ['id' => 3, 'complex_id' => 1, 'unit_number' => '3', 'unit_category' => 'house', 'house_type' => 'Bungalow', 'offer' => 'rent', 'sale_price' => null, 'status' => 'available', 'bedrooms' => 3, 'bathrooms' => 2, 'assigned_agent_id' => null],
            ['id' => 4, 'complex_id' => 2, 'unit_number' => 'V1', 'unit_category' => 'house', 'house_type' => 'Villa', 'offer' => 'sale', 'sale_price' => 50000000, 'status' => 'available', 'bedrooms' => 6, 'bathrooms' => 5, 'assigned_agent_id' => null],
            ['id' => 5, 'complex_id' => 1, 'unit_number' => 'A1', 'unit_category' => 'apartment', 'house_type' => null, 'offer' => 'rent', 'sale_price' => null, 'status' => 'available', 'bedrooms' => 2, 'bathrooms' => 1, 'assigned_agent_id' => null],
        ]);
        DB::table('history_categories')->insert(['tag' => 'sales_rental', 'name' => 'Past Sale and Rental Records']);
        $this->actingAs(User::find(10));
        $this->sales = app(PropertySaleService::class);
    }

    protected function tearDown(): void
    {
        app(PropertyAccess::class)->deactivate();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function unit(int $id): ComplexResidentialUnit
    {
        return ComplexResidentialUnit::withoutGlobalScopes()->find($id);
    }

    private function start(int $unit = 1, array $over = []): PropertySale
    {
        return $this->sales->start($this->unit($unit), array_merge([
            'buyer_name' => 'Wanjiru Kamau', 'buyer_phone' => '+254711000000', 'price' => 30000000, 'agreement_date' => '2026-11-18',
            'deposit_amount' => 9000000, 'deposit_due_date' => '2026-11-18', 'plan' => 'equal', 'instalments' => 6, 'first_due_date' => '2026-12-18', 'frequency' => 'monthly',
        ], $over));
    }

    private function pay(PropertySale $sale, float $amount, string $on = '2026-11-18')
    {
        return $this->sales->recordPayment($sale, ['amount' => $amount, 'paid_on' => $on, 'method' => 'bank_transfer', 'reference' => 'BT' . random_int(100, 999)]);
    }

    private function agreement(array $terms): void
    {
        app(CommissionAgreementService::class)->save(10, $terms + ['entitlement' => 'sale', 'scope' => 'company', 'effective_from' => '2026-01-01']);
    }

    public function test_the_plan_adds_up_to_the_price_with_the_deposit_first_and_rounding_on_the_last_instalment(): void
    {
        $plan = $this->sales->schedule(10000001, 3000000, '2026-11-18', 6, '2026-12-31');
        $this->assertCount(7, $plan);
        $this->assertSame('Deposit', $plan[0]['label']);
        $this->assertEqualsWithDelta(10000001, array_sum(array_column($plan, 'amount')), 0.001);
        $this->assertSame(['2026-12-31', '2027-01-31', '2027-02-28', '2027-03-31'], array_column(array_slice($plan, 1, 4), 'due_date'), 'monthly steps from the first due date keep to month ends');
        $this->assertSame(1166666.83, $plan[1]['amount']);
        $this->assertSame(1166666.85, $plan[6]['amount'], 'the last instalment takes the rounding');

        $custom = $this->sales->schedule(1000, 200, '2026-11-18', 0, null, [['due_date' => '2027-01-01', 'amount' => 500], ['due_date' => '2027-02-01', 'amount' => 300]]);
        $this->assertSame([200.0, 500.0, 300.0], array_column($custom, 'amount'));
        $this->expectException(ValidationException::class);
        $this->sales->schedule(1000, 200, '2026-11-18', 0, null, [['due_date' => '2027-01-01', 'amount' => 500]]);
    }

    public function test_only_a_free_house_offered_for_sale_can_be_sold(): void
    {
        $this->assertNull($this->sales->blocker($this->unit(1)));
        $this->assertNull($this->sales->blocker($this->unit(2)), 'for rent or sale');
        $this->assertStringContainsString('rent only', $this->sales->blocker($this->unit(3)));
        $this->assertStringContainsString('Only houses', $this->sales->blocker($this->unit(5)));

        $this->tenancy(['residential_unit_id' => 2, 'unit_number' => '2', 'status' => 'Active']);
        $this->assertStringContainsString('tenancy', $this->sales->blocker($this->unit(2)), 'a let house is not sold over its tenant');

        $sale = $this->start();
        $this->assertSame('reserved', $sale->status, 'a deposit is due, so the house is reserved until it is paid');
        $this->assertSame('under_offer', $this->unit(1)->status);
        $this->assertSame(1, $sale->agent_id, 'the house\'s agent is on the sale by default');
        $this->assertCount(7, $sale->instalments);
        $this->assertStringContainsString('in progress', $this->sales->blocker($this->unit(1)));
        try {
            $this->start();
            $this->fail('A second sale of the same house must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('unit', $e->errors());
        }
    }

    public function test_payments_go_oldest_first_the_deposit_agrees_the_sale_and_a_reversal_puts_it_back(): void
    {
        $sale = $this->start();
        $this->pay($sale, 5000000);
        $this->assertSame('reserved', $sale->fresh()->status, 'deposit only part paid');
        $p2 = $this->pay($sale, 6000000);
        $sale->refresh()->load('instalments');
        $this->assertSame('agreed', $sale->status);
        $this->assertSame(['paid', 'part_paid', 'due'], $sale->instalments->take(3)->pluck('status')->all());
        $this->assertEqualsWithDelta(2000000, $sale->instalments[1]->paid_amount, 0.01, 'the rest went to instalment 1');
        $this->assertSame($sale->reference . '-R002', $p2->receipt_number);

        try {
            $this->pay($sale, 30000000);
            $this->fail('Paying more than the balance must be refused');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->sales->reverse($p2, 'Cheque bounced');
        $sale->refresh()->load('instalments');
        $this->assertSame(['part_paid', 'due'], $sale->instalments->take(2)->pluck('status')->all());
        $this->assertEqualsWithDelta(5000000, $sale->collected(), 0.01);
        $this->assertSame('agreed', $sale->status, 'an agreed sale is not un-agreed by a reversal');
    }

    public function test_sales_commission_is_earned_as_the_buyer_pays_and_reversed_with_the_payment(): void
    {
        $this->agreement(['method' => 'percent_of_sale', 'rate' => 2]);
        $sale = $this->start();
        $payment = $this->pay($sale, 9000000);
        $entry = CommissionEntry::where('sale_payment_id', $payment->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame([1, 'sale', 'earned', 'additional_cost'], [$entry->agent_id, $entry->entitlement, $entry->status, $entry->funding]);
        $this->assertEqualsWithDelta(180000, $entry->amount, 0.01, '2% of the 9m received');

        $statement = app(AgentCommissionService::class)->statement(Agent::find(1), '2026-11-01', '2026-11-30');
        $this->assertCount(1, $statement['sale_lines']);
        $this->assertEqualsWithDelta(180000, $statement['due']['sales'], 0.01);
        $this->assertEqualsWithDelta(180000, $statement['due']['total'], 0.01, 'sales commission is paid out with the rest');
        $this->assertEqualsWithDelta(180000, app(AgentCommissionService::class)->forAgent(Agent::find(1), '2026-11-01', '2026-11-30')['total_commission'], 0.01);

        $this->sales->reverse($payment, 'Entered twice');
        $this->assertSame('reversed', $entry->fresh()->status);
        $this->assertSame(1, CommissionEntry::where('kind', 'reversal')->where('reverses_entry_id', $entry->id)->count());
    }

    public function test_a_flat_sales_commission_builds_up_with_each_payment(): void
    {
        $this->agreement(['method' => 'flat_per_sale', 'flat_amount' => 300000]);
        $sale = $this->start();
        $this->pay($sale, 15000000);
        $this->pay($sale, 15000000, '2026-11-19');
        $this->assertEqualsWithDelta(300000, CommissionEntry::where('sale_id', $sale->id)->sum('amount'), 0.01, 'half the price, half the flat amount, twice');
    }

    public function test_sale_methods_are_only_for_sales_commission(): void
    {
        $this->expectException(ValidationException::class);
        app(CommissionAgreementService::class)->save(10, ['entitlement' => 'agent', 'scope' => 'company', 'method' => 'percent_of_sale', 'rate' => 2, 'effective_from' => '2026-01-01']);
    }

    public function test_completing_marks_the_house_sold_and_records_it_in_the_history(): void
    {
        $sale = $this->start(2, ['price' => 20000000, 'deposit_amount' => 0, 'instalments' => 1]);
        $this->assertSame('agreed', $sale->status, 'no deposit: agreed straight away');
        $this->pay($sale, 5000000);
        try {
            $this->sales->complete($sale);
            $this->fail('An unpaid balance needs an explanation');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('completion_note', $e->errors());
        }
        $this->sales->complete($sale, 'Mortgage paid the balance to the seller directly.');
        $this->assertSame('completed', $sale->fresh()->status);
        $this->assertSame('sold', $this->unit(2)->status);
        $this->assertSame(1, DB::table('complex_histories')->where('complex_residential_unit_id', 2)->where('category_tag', 'sales_rental')->count());

        app(ComplexInventoryService::class)->syncOccupancy(2);
        $this->assertSame('sold', $this->unit(2)->status, 'occupancy sync never turns a sold house back into available');
    }

    public function test_cancelling_puts_the_house_back_on_the_market_and_a_refund_reverses_the_money(): void
    {
        $sale = $this->start();
        $this->pay($sale, 9000000);
        $this->sales->cancel($sale, 'Buyer could not get a mortgage', true);
        $sale->refresh()->load(['instalments', 'payments']);
        $this->assertSame('cancelled', $sale->status);
        $this->assertSame('available', $this->unit(1)->status);
        $this->assertTrue($sale->payments->every(fn ($p) => $p->status === 'reversed'));
        $this->assertTrue($sale->instalments->every(fn ($i) => $i->status === 'cancelled'));
        $this->assertNull($this->sales->blocker($this->unit(1)), 'the house can be sold again');
    }

    public function test_a_limited_member_sees_only_sales_at_their_properties(): void
    {
        // Karen Villas is managed by another agent, so it is not open to the whole team.
        DB::table('agents')->insert(['id' => 2, 'created_by' => 10, 'first_name' => 'Ben', 'last_name' => 'Other', 'email' => 'b@x.test']);
        DB::table('complexes')->where('id', 2)->update(['management_type' => 'individual', 'assigned_agent_id' => 2]);
        $here = $this->start();
        $there = $this->start(4, ['price' => 50000000]);
        $this->assertSame(2, $there->agent_id, 'the property\'s primary manager is on the sale');
        $user = User::with('businessRole')->find(11);
        $this->actingAs($user);
        app(PropertyAccess::class)->activate($user);
        $this->assertSame([$here->id], PropertySale::pluck('id')->all(), 'Runda is assigned to Ann; Karen Villas is not');
        $this->assertNull(PropertySale::where('reference', $there->reference)->first());
    }

    public function test_sale_news_goes_to_the_agent_and_the_people_who_take_its_payments(): void
    {
        DB::table('business_roles')->insert([
            ['id' => 2, 'business_id' => 10, 'name' => 'Finance Officer', 'property_scope' => 'all', 'permissions' => json_encode(['sales_management' => ['view', 'payments']])],
            ['id' => 3, 'business_id' => 10, 'name' => 'Viewer', 'property_scope' => 'all', 'permissions' => json_encode(['sales_management' => ['view']])],
        ]);
        DB::table('users')->insert([
            ['id' => 12, 'first_name' => 'Fiona', 'last_name' => 'Finance', 'email' => 'f@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 2],
            ['id' => 13, 'first_name' => 'Vic', 'last_name' => 'Viewer', 'email' => 'v@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 3],
        ]);
        $sale = $this->start();
        $this->pay($sale, 1000000);
        $kinds = fn (int $user) => DB::table('notifications')->where('notifiable_id', $user)->pluck('data')->map(fn ($d) => json_decode($d, true)['kind'])->sort()->values()->all();
        $this->assertSame(['sale.payment', 'sale.started'], $kinds(11), 'the agent on the sale hears about it');
        $this->assertSame(['sale.payment'], $kinds(12), 'finance hears about payments');
        $this->assertSame([], $kinds(13), 'a read-only viewer is not notified');
        $this->assertSame([], $kinds(10), 'never the person acting');
    }
}
