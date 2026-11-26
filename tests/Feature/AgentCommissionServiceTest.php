<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Services\Commission\AgentCommissionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/** Agent commission: the agent's own figure matches the business report, and staff-entered tenancies count. */
class AgentCommissionServiceTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'commission_test', 'database.connections.commission_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Schema::create('agents', function (Blueprint $t) {
            $t->id(); $t->integer('user_id')->nullable(); $t->string('agent_type')->default('employee'); $t->string('first_name'); $t->string('last_name');
            $t->string('email'); $t->string('phone')->default('1'); $t->string('commission_type')->nullable(); $t->decimal('commission_rate')->default(0);
            $t->decimal('base_salary')->default(0); $t->decimal('sales_target')->default(0); $t->string('status')->default('active'); $t->boolean('is_active')->default(true);
            $t->integer('reports_to_agent_id')->nullable(); $t->decimal('override_rate')->default(0);
            $t->integer('created_by'); $t->integer('updated_by')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        Schema::create('agent_commission_tiers', function (Blueprint $t) { $t->id(); $t->integer('agent_id'); $t->string('label')->nullable(); $t->decimal('min_value'); $t->decimal('max_value')->nullable(); $t->decimal('rate'); $t->timestamps(); });
        Schema::create('tenant_management', function (Blueprint $t) { $t->id(); $t->integer('complex_id')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->integer('managed_by'); $t->integer('created_by')->nullable(); $t->timestamps(); });
        // Commission reads rent allocations: money applied to rent invoices, with the agent recorded at the time.
        Schema::create('tenant_invoice_payment_allocations', function (Blueprint $t) {
            $t->id(); $t->integer('invoice_id')->default(0); $t->integer('payment_id')->nullable(); $t->integer('tenant_id'); $t->decimal('allocated_amount');
            $t->string('source')->default('payment'); $t->string('invoice_kind')->default('rent'); $t->integer('agent_id')->nullable(); $t->integer('override_agent_id')->nullable();
            $t->date('allocation_date'); $t->timestamp('reversed_at')->nullable(); $t->timestamps();
        });
        Schema::create('complexes', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('title')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('complex_expenses', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->decimal('amount'); $t->date('expense_date'); $t->string('workflow_status')->nullable(); $t->timestamps(); });

        DB::table('agents')->insert([
            ['id' => 1, 'first_name' => 'Grace', 'last_name' => 'W', 'email' => 'g@x.test', 'commission_type' => 'sales_value', 'commission_rate' => 10, 'created_by' => 10],
            ['id' => 2, 'first_name' => 'Kevin', 'last_name' => 'M', 'email' => 'k@x.test', 'commission_type' => 'net_profit', 'commission_rate' => 5, 'created_by' => 10],
        ]);
        // Tenancy 1 was entered by a team member (created_by 11) for business 10; tenancy 2 by the owner.
        DB::table('tenant_management')->insert([
            ['id' => 1, 'complex_id' => 7, 'assigned_agent_id' => 1, 'managed_by' => 10, 'created_by' => 11],
            ['id' => 2, 'complex_id' => 8, 'assigned_agent_id' => 2, 'managed_by' => 10, 'created_by' => 10],
        ]);
        DB::table('tenant_invoice_payment_allocations')->insert([
            ['tenant_id' => 1, 'agent_id' => 1, 'allocated_amount' => 10000, 'allocation_date' => '2026-11-05'],
            ['tenant_id' => 1, 'agent_id' => 1, 'allocated_amount' => 5000, 'allocation_date' => '2026-10-05'],
            ['tenant_id' => 2, 'agent_id' => 2, 'allocated_amount' => 20000, 'allocation_date' => '2026-11-06'],
        ]);
        // Kevin is responsible for Mombasa Road, so its approved expenses reduce his net-profit commission.
        DB::table('complexes')->insert([['id' => 7, 'user_id' => 10, 'title' => 'Riverside', 'assigned_agent_id' => null], ['id' => 8, 'user_id' => 10, 'title' => 'Mombasa Road', 'assigned_agent_id' => 2]]);
        DB::table('complex_expenses')->insert([['complex_id' => 8, 'amount' => 4000, 'expense_date' => '2026-11-07']]);
        $this->createCommissionSchema();
    }

    /**
     * Record the commission ledger for the rent above, as collected under the current set-up: agreements from
     * the agents' profiles, properties with a primary manager are individually managed, period commission
     * (Kevin's net profit) calculated and approved.
     */
    private function record(): void
    {
        DB::table('complexes')->whereNotNull('assigned_agent_id')->update(['management_type' => 'individual']);
        foreach (Agent::all() as $agent) {
            app(\App\Services\Commission\CommissionAgreementService::class)->syncFromAgent($agent, '2026-01-01');
        }
        app(\App\Services\Commission\CommissionLedgerService::class)->backfill(10);
        app(\App\Services\Commission\CommissionPeriodService::class)->calculate(10, '2026-11-01', '2026-11-30');
        app(\App\Services\Commission\CommissionLedgerService::class)->approve(\App\Models\CommissionEntry::where('status', 'pending')->pluck('id')->all(), 10);
    }

    public function test_staff_entered_tenancies_count_towards_commission(): void
    {
        $this->record();
        $row = app(AgentCommissionService::class)->forAgent(Agent::find(1), '2026-11-01', '2026-11-30');
        $this->assertEquals(10000, $row['total_payments']);
        $this->assertSame(1, $row['tenant_count']);
        $this->assertEquals(1000, $row['default_commission']);
    }

    public function test_an_agents_own_figure_matches_the_business_report(): void
    {
        $this->record();
        $service = app(AgentCommissionService::class);
        $report = collect($service->getAgentCommissions(10, '2026-11-01', '2026-11-30')['agents'])->keyBy('agent_id');
        foreach ([1, 2] as $id) {
            $this->assertEquals($report[$id], $service->forAgent(Agent::find($id), '2026-11-01', '2026-11-30'), "agent $id");
        }
        $this->assertEquals(800, $report[2]['default_commission'], 'net profit: (20,000 − 4,000) × 5%');
    }

    public function test_a_lead_agent_earns_an_override_on_sub_agents_tenancies_in_their_property(): void
    {
        // Jane leads property 7 with a 2% override; Grace (agent 1) reports to Jane and placed tenancy 1 there.
        DB::table('agents')->insert(['id' => 3, 'first_name' => 'Jane', 'last_name' => 'F', 'email' => 'j@x.test', 'commission_type' => 'sales_value', 'commission_rate' => 5, 'override_rate' => 2, 'created_by' => 10]);
        DB::table('agents')->where('id', 1)->update(['reports_to_agent_id' => 3]);
        DB::table('complexes')->where('id', 7)->update(['assigned_agent_id' => 3]);
        // Grace's rent at Jane's property is a delegated unit: Jane earns her override on it.
        $this->record();

        $service = app(AgentCommissionService::class);
        $jane = $service->forAgent(Agent::find(3), '2026-11-01', '2026-11-30');
        $this->assertEquals(10000, $jane['override_payments'], 'only the sub-agent\'s collections at her property');
        $this->assertEquals(200, $jane['override_commission']);
        $this->assertEquals(200, $jane['total_commission'], 'no own tenancies, so the override is all she earns');

        $grace = $service->forAgent(Agent::find(1), '2026-11-01', '2026-11-30');
        $this->assertEquals(1000, $grace['default_commission'], 'the sub-agent keeps their full commission');
        $this->assertEquals(0, $grace['override_commission']);
        $this->assertEquals(collect($service->getAgentCommissions(10, '2026-11-01', '2026-11-30')['agents'])->firstWhere('agent_id', 3), $jane);
    }

    public function test_reporting_periods(): void
    {
        $this->travelTo('2026-11-10 12:00:00');
        $this->assertSame(['this_month', '2026-11-01', '2026-11-30'], array_values(array_intersect_key(AgentCommissionService::period(null), array_flip(['key', 'from', 'to']))));
        $last = AgentCommissionService::period('last_month');
        $this->assertSame(['2026-10-01', '2026-10-31'], [$last['from'], $last['to']]);
        $custom = AgentCommissionService::period('custom', '2026-11-20', '2026-11-02');
        $this->assertSame(['custom', '2026-11-02', '2026-11-20'], [$custom['key'], $custom['from'], $custom['to']], 'a reversed range is swapped');
        $this->assertSame('this_month', AgentCommissionService::period('custom', 'not a date', null)['key']);
    }
}
