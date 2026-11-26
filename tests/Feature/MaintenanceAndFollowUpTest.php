<?php

namespace Tests\Feature;

use App\Models\BusinessRole;
use App\Models\ComplexExpense;
use App\Models\MaintenanceRequest;
use App\Models\TenantFollowUp;
use App\Models\TenantInvoice;
use App\Models\User;
use App\Services\Properties\MaintenanceService;
use App\Services\Access\PropertyAccess;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use App\Services\Rent\TenantReportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Maintenance requests from report to closure (cost booked as a property expense, optionally charged to the
 * tenant), follow-ups with promises to pay, and statement dates for tenants added after they moved in.
 */
class MaintenanceAndFollowUpTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    use \Tests\Concerns\TenantAccountsSchema;

    private MaintenanceService $maintenance;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'maintenance_test', 'database.connections.maintenance_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Mail::fake();
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->schema();

        DB::table('users')->insert([
            ['id' => 10, 'first_name' => 'Owner', 'last_name' => 'One', 'email' => 'owner@x.test', 'is_sub_account' => false, 'main_account_user_id' => null],
            ['id' => 11, 'first_name' => 'Kevin', 'last_name' => 'Fixer', 'email' => 'kevin@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10],
        ]);
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Erty Homes']);
        DB::table('complexes')->insert([['id' => 1, 'user_id' => 10, 'title' => 'Riverside'], ['id' => 2, 'user_id' => 10, 'title' => 'Mombasa Road']]);
        DB::table('complex_residential_units')->insert([['id' => 1, 'complex_id' => 1, 'unit_number' => 'A-101', 'status' => 'occupied'], ['id' => 2, 'complex_id' => 1, 'unit_number' => 'A-102', 'status' => 'available']]);
        DB::table('commercial_units')->insert(['id' => 1, 'complex_id' => 2, 'unit_number' => 'G-01', 'unit_sq_ft' => 1000]);
        DB::table('tenant_profiles')->insert(['id' => 1, 'tenant_type' => 'Individual', 'tenant_name' => 'Amani Otieno', 'tenant_email' => 'amani@x.test', 'managed_by' => 10]);
        DB::table('expense_types')->insert(['id' => 7, 'expense_type' => 'Maintenance and Repairs']);
        $this->actingAs(User::find(10));
        $this->maintenance = app(MaintenanceService::class);
        $this->createCommissionSchema();
        // Individually managed (by an external agent), so assigned-only roles see them only when assigned.
        DB::table('complexes')->update(['management_type' => 'individual', 'assigned_agent_id' => 999]);
    }

    protected function tearDown(): void
    {
        app(PropertyAccess::class)->deactivate();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_repair_paid_by_the_landlord_runs_from_report_to_a_draft_expense(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        $r = $this->maintenance->log(['complex_id' => 1, 'tenancy_id' => $t->id, 'title' => 'Kitchen tap leaking', 'category' => 'plumbing', 'priority' => 'high', 'reported_by' => 'Amani'], 10);

        $this->assertMatchesRegularExpression('/^MR-[A-Z0-9]{6}$/', $r->reference);
        $this->assertSame('open', $r->status);
        $this->assertSame(1, $r->residential_unit_id, 'the unit comes from the tenancy');

        $this->maintenance->assign($r, 11, 'Call the plumber');
        $this->assertSame('assigned', $r->fresh()->status);
        $this->maintenance->start($r->fresh());
        $this->maintenance->addNote($r->fresh(), 'Washer ordered');
        $this->maintenance->complete($r->fresh(), 'Replaced washer and valve', 2500, 'landlord');
        $this->assertSame('completed', $r->fresh()->status);
        $this->assertNull($r->fresh()->expense_id, 'nothing is booked until a manager closes it');

        $closed = $this->maintenance->close($r->fresh());
        $this->assertSame('closed', $closed->status);
        $expense = ComplexExpense::find($closed->expense_id);
        $this->assertEquals(2500, $expense->amount);
        $this->assertSame('residential_unit', $expense->scope);
        $this->assertSame(7, (int) $expense->type);
        $this->assertSame('draft', $expense->workflow_status, 'finance approves and pays it through the expense workflow');
        $this->assertSame(1, DB::table('property_expense_events')->where('expense_id', $expense->id)->where('action', 'created')->count());
        $this->assertNull($closed->tenant_invoice_id);
        $this->assertSame(['open', 'assigned', 'in_progress', null, 'completed', 'closed'], $closed->notes()->reorder('id')->pluck('status_to')->all());
    }

    public function test_a_repair_the_tenant_pays_for_is_charged_to_their_account_once(): void
    {
        $t = $this->tenancy(['security_deposit' => 0]);
        app(TenantChargesService::class)->startBilling($t);
        $before = app(TenantAccountService::class)->balance($t);

        $r = $this->maintenance->log(['complex_id' => 1, 'tenancy_id' => $t->id, 'title' => 'Broken window', 'category' => 'structural'], 10);
        $this->maintenance->complete($r, 'Glass replaced', 4000, 'tenant');
        $closed = $this->maintenance->close($r->fresh());

        $invoice = TenantInvoice::find($closed->tenant_invoice_id);
        $this->assertSame('adjustment', $invoice->kind);
        $this->assertEquals(4000, $invoice->amount);
        $this->assertStringContainsString($r->reference, $invoice->line_items[0]['description']);
        $this->assertEquals($before + 4000, app(TenantAccountService::class)->balance($t));
        $this->assertStringContainsString('recharged to tenant', ComplexExpense::find($closed->expense_id)->description, 'the landlord pays the contractor; the tenant repays');

        $this->expectException(ValidationException::class);
        $this->maintenance->close($closed->fresh());
    }

    public function test_the_order_of_steps_is_enforced(): void
    {
        $r = $this->maintenance->log(['complex_id' => 1, 'title' => 'Corridor light out', 'category' => 'electrical'], 10);
        $this->assertNull($r->tenancy_id);

        try { $this->maintenance->close($r); $this->fail('closed an open request'); } catch (ValidationException $e) { $this->assertArrayHasKey('status', $e->errors()); }
        try { $this->maintenance->complete($r, 'Bulb', 300, 'tenant'); $this->fail('charged a tenant on a common-area repair'); } catch (ValidationException $e) { $this->assertArrayHasKey('cost_borne_by', $e->errors()); }

        $this->maintenance->complete($r->fresh(), 'Bulb replaced', 0, null);
        $this->maintenance->reopen($r->fresh(), 'Flickering again');
        $this->assertSame('open', $r->fresh()->status);
        $this->maintenance->cancel($r->fresh(), 'Duplicate');
        try { $this->maintenance->assign($r->fresh(), 11); $this->fail('assigned a cancelled request'); } catch (ValidationException $e) { $this->assertArrayHasKey('status', $e->errors()); }

        $this->maintenance->reopen($r->fresh(), 'Not a duplicate after all');
        $this->maintenance->complete($r->fresh(), 'Fixed', null, null);
        $closed = $this->maintenance->close($r->fresh());
        $this->assertNull($closed->expense_id, 'no cost, no expense');
    }

    public function test_a_member_limited_to_assigned_properties_sees_their_properties_and_requests_assigned_to_them(): void
    {
        $atRiverside = $this->maintenance->log(['complex_id' => 1, 'title' => 'Gate motor'], 10);
        $atMombasa = $this->maintenance->log(['complex_id' => 2, 'title' => 'Aircon', 'commercial_unit_id' => 1], 10);
        $assigned = $this->maintenance->log(['complex_id' => 2, 'title' => 'Roof leak', 'assigned_user_id' => 11], 10);
        DB::table('complex_user')->insert(['complex_id' => 1, 'user_id' => 11]);

        $member = User::find(11);
        $member->setRelation('businessRole', new BusinessRole(['property_scope' => 'assigned', 'permissions' => []]));
        app(PropertyAccess::class)->activate($member);

        $seen = MaintenanceRequest::pluck('id')->all();
        $this->assertContains($atRiverside->id, $seen);
        $this->assertContains($assigned->id, $seen);
        $this->assertNotContains($atMombasa->id, $seen);
    }

    public function test_follow_ups_and_promises_show_on_the_accounts_list(): void
    {
        $t = $this->tenancy(['security_deposit' => 0, 'move_in_date' => '2026-09-15', 'lease_start_date' => '2026-09-15']);
        app(TenantChargesService::class)->startBilling($t);

        TenantFollowUp::create(['tenancy_id' => $t->id, 'user_id' => 10, 'method' => 'call', 'note' => 'No answer', 'contacted_on' => '2026-11-01']);
        TenantFollowUp::create(['tenancy_id' => $t->id, 'user_id' => 10, 'method' => 'visit', 'note' => 'Will pay half next week', 'contacted_on' => '2026-11-05',
            'promise_date' => '2026-11-12', 'promise_amount' => 35000, 'promise_status' => 'open']);

        $row = collect(app(TenantReportService::class)->getAccounts())->firstWhere('tenancy_id', $t->id);
        $this->assertSame('5 Nov 2026 · Visit', $row['last_follow_up']);
        $this->assertEquals(35000, $row['promise']['amount']);
        $this->assertTrue($row['promise']['passed'], 'the promised date has gone by');

        TenantFollowUp::where('promise_status', 'open')->update(['promise_status' => 'kept']);
        $row = collect(app(TenantReportService::class)->getAccounts())->firstWhere('tenancy_id', $t->id);
        $this->assertNull($row['promise']);
    }

    public function test_back_rent_for_a_tenant_added_late_is_dated_by_its_period_on_the_statement(): void
    {
        $t = $this->tenancy(['security_deposit' => 0, 'move_in_date' => '2026-08-15', 'lease_start_date' => '2026-08-15']);
        app(TenantChargesService::class)->startBilling($t); // issued today (15 Nov) for Aug, Sep, Oct and Nov
        app(TenantAccountService::class)->record($t, $this->pay(35000, ['payment_date' => '2026-09-01']));

        $lines = collect(app(TenantAccountService::class)->statement($t)['lines']);
        $this->assertSame(['2026-08-15', '2026-09-01', '2026-09-15', '2026-10-15', '2026-11-15'], $lines->pluck('date')->all(),
            'each charge sits at its period start and the September payment falls between them');
        $this->assertEquals(105000, $lines->last()['balance']);

        $september = app(TenantAccountService::class)->statement($t, '2026-09-01', '2026-09-30');
        $this->assertEquals(35000, $september['opening_balance'], 'August rent is carried in');
    }
}
