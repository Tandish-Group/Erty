<?php

namespace Tests\Feature;

use App\Models\{Agent, AssignmentHistory, BusinessRole, CommercialUnit, CommissionAgreement, CommissionEntry, Complex, ComplexResidentialUnit, TenantManagement, User};
use App\Services\Agents\AgentAssignmentService;
use App\Services\Commission\AgentCommissionService;
use App\Services\Agents\AssignmentHistoryService;
use App\Services\Commission\CommissionAgreementService;
use App\Services\Commission\CommissionLedgerService;
use App\Services\Commission\CommissionPeriodService;
use App\Services\Commission\CommissionReportService;
use App\Services\Access\PropertyAccess;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Property and unit responsibility, and the commission ledger built on it.
 *
 * Mlimani (complex 1) is managed by Grace (agent 1) as primary manager; Westlands (complex 2) by the company.
 * Kevin (agent 2) and Jane (agent 3) are agents who sign in. Rent is 35,000 a month.
 */
class PropertyAssignmentCommissionTest extends TestCase
{
    use \Tests\Concerns\TenantAccountsSchema;
    use \Tests\Concerns\CreatesAssignmentSchema;

    private TenantAccountService $account;
    private TenantChargesService $charges;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'assign_test', 'database.connections.assign_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Mail::fake();
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->schema();
        $this->createCommissionSchema();
        \Illuminate\Support\Facades\Schema::create('complex_changes', function (\Illuminate\Database\Schema\Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->string('field_name'); $t->text('old_value')->nullable(); $t->text('new_value'); $t->integer('user_id'); $t->string('user_ip')->nullable(); $t->timestamps();
        });

        DB::table('users')->insert([
            ['id' => 10, 'first_name' => 'Owner', 'last_name' => 'One', 'email' => 'owner@x.test', 'is_sub_account' => false, 'main_account_user_id' => null, 'business_permissions' => null],
            ['id' => 11, 'first_name' => 'Grace', 'last_name' => 'PM', 'email' => 'grace@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_permissions' => json_encode(['complex_management' => ['view', 'edit'], 'tenant_management' => ['view']])],
            ['id' => 12, 'first_name' => 'Kevin', 'last_name' => 'Agent', 'email' => 'kevin@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_permissions' => json_encode(['complex_management' => ['view'], 'tenant_management' => ['view']])],
            ['id' => 13, 'first_name' => 'Jane', 'last_name' => 'Agent', 'email' => 'jane@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_permissions' => json_encode(['complex_management' => ['view']])],
        ]);
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Tandish']);
        DB::table('complexes')->insert([
            ['id' => 1, 'user_id' => 10, 'title' => 'Mlimani', 'assigned_agent_id' => 1, 'management_type' => 'individual'],
            ['id' => 2, 'user_id' => 10, 'title' => 'Westlands', 'assigned_agent_id' => null, 'management_type' => 'company'],
        ]);
        DB::table('agents')->insert([
            ['id' => 1, 'created_by' => 10, 'user_id' => 11, 'first_name' => 'Grace', 'last_name' => 'PM', 'email' => 'grace@x.test', 'is_active' => true],
            ['id' => 2, 'created_by' => 10, 'user_id' => 12, 'first_name' => 'Kevin', 'last_name' => 'Agent', 'email' => 'kevin@x.test', 'is_active' => true],
            ['id' => 3, 'created_by' => 10, 'user_id' => 13, 'first_name' => 'Jane', 'last_name' => 'Agent', 'email' => 'jane@x.test', 'is_active' => true],
        ]);
        DB::table('complex_residential_units')->insert([
            ['id' => 1, 'complex_id' => 1, 'unit_number' => 'A-1', 'status' => 'available'], ['id' => 2, 'complex_id' => 1, 'unit_number' => 'B-1', 'status' => 'available'],
            ['id' => 3, 'complex_id' => 1, 'unit_number' => 'C-1', 'status' => 'available'],
        ]);
        DB::table('commercial_units')->insert([
            ['id' => 1, 'complex_id' => 2, 'unit_number' => 'G-01', 'unit_sq_ft' => 1000, 'status' => 'Available'], ['id' => 2, 'complex_id' => 2, 'unit_number' => 'G-02', 'unit_sq_ft' => 1000, 'status' => 'Available'],
        ]);
        DB::table('tenant_profiles')->insert([
            ['id' => 1, 'tenant_type' => 'Individual', 'tenant_name' => 'Amani', 'managed_by' => 10], ['id' => 2, 'tenant_type' => 'Individual', 'tenant_name' => 'Baraka', 'managed_by' => 10],
            ['id' => 3, 'tenant_type' => 'Business', 'tenant_name' => 'Duka Ltd', 'managed_by' => 10],
        ]);
        $this->actingAs(User::find(10));
        $this->account = app(TenantAccountService::class);
        $this->charges = app(TenantChargesService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function agreement(array $data, string $from = '2026-01-01'): CommissionAgreement
    {
        return app(CommissionAgreementService::class)->save(10, $data + ['effective_from' => $from, 'method' => 'percent_collected']);
    }

    private function assignUnits(string $kind, array $ids, ?int $agentId, int $as = 10): int
    {
        $complex = Complex::withoutGlobalScopes()->find($kind === 'commercial' ? 2 : 1);
        return app(AgentAssignmentService::class)->assignUnits(User::find($as), $complex, $kind, $ids, $agentId);
    }

    /** A paying tenancy (no deposit) at a unit, with its first rent invoiced. */
    private function lease(string $kind, int $unitId, array $extra = []): TenantManagement
    {
        $t = $this->tenancy(($kind === 'commercial'
            ? ['complex_id' => 2, 'complex_name' => 'Westlands', 'listing_type' => 'Commercial Unit', 'residential_unit_id' => null, 'commercial_unit_id' => $unitId, 'unit_number' => 'G-0' . $unitId, 'tenant_profile_id' => 3]
            : ['residential_unit_id' => $unitId, 'complex_name' => 'Mlimani', 'unit_number' => 'U' . $unitId]) + ['security_deposit' => 0] + $extra);
        $this->charges->startBilling($t);
        return $t;
    }

    private function entries(array $where = [])
    {
        return CommissionEntry::where($where)->orderBy('id')->get();
    }

    private function member(int $id, string $scope = 'assigned'): User
    {
        $user = User::find($id);
        $user->setRelation('businessRole', new BusinessRole(['property_scope' => $scope, 'permissions' => []]));
        return $user;
    }

    // ─── Property-level assignment ──────────────────────────────────────────

    public function test_a_property_can_be_given_to_an_individual_or_the_company_and_each_change_is_recorded(): void
    {
        $service = app(AgentAssignmentService::class);
        $westlands = Complex::find(2);

        $service->setManagement(User::find(10), $westlands, 'individual', 3, 'Jane leads it');
        $this->assertSame([Complex::MANAGED_BY_INDIVIDUAL, 3], [$westlands->fresh()->management_type, (int) $westlands->fresh()->assigned_agent_id]);

        Carbon::setTestNow('2026-11-20 10:00');
        $service->setManagement(User::find(10), $westlands->fresh(), 'company', null);
        $this->assertTrue($westlands->fresh()->isCompanyManaged());
        $this->assertNull($westlands->fresh()->assigned_agent_id);

        $rows = AssignmentHistory::where('complex_id', 2)->where('scope', 'property')->orderBy('id')->get();
        $this->assertSame([['agent', 3, 'primary'], ['company', null, 'company']], $rows->map(fn ($r) => [$r->assignee_type, $r->agent_id, $r->role])->all());
        $this->assertNotNull($rows[0]->ends_at, 'the earlier arrangement is closed, not deleted');
        $this->assertSame('Jane leads it', $rows[0]->reason);
        $this->assertSame(3, app(AssignmentHistoryService::class)->primaryOn(2, '2026-11-16'), 'who managed it on a past date');
        $this->assertNull(app(AssignmentHistoryService::class)->primaryOn(2, '2026-11-21'));
    }

    public function test_individual_management_needs_an_agent_who_signs_in_and_only_administrators_change_it(): void
    {
        $service = app(AgentAssignmentService::class);
        try { $service->setManagement(User::find(10), Complex::find(2), 'individual', null); $this->fail('no primary'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('agent_id', $e->errors()); }

        DB::table('agents')->insert(['id' => 9, 'created_by' => 10, 'user_id' => null, 'first_name' => 'External', 'last_name' => 'Agency', 'email' => 'ext@x.test', 'is_active' => true]);
        try { $service->setManagement(User::find(10), Complex::find(2), 'individual', 9); $this->fail('external agent'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('agent_id', $e->errors()); }

        $this->actingAs(User::find(11));
        try { $service->setManagement(User::find(11), Complex::find(2), 'company', null); $this->fail('not an administrator'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    // ─── Access ─────────────────────────────────────────────────────────────

    public function test_company_properties_are_open_to_the_team_and_individual_ones_only_to_their_people(): void
    {
        $this->assignUnits('residential', [2], 2); // Kevin works unit B-1 at Grace's Mlimani
        $a = $this->lease('residential', 1);
        $b = $this->lease('residential', 2, ['tenant_profile_id' => 2]);
        $w = $this->lease('commercial', 1);

        $access = app(PropertyAccess::class);
        $access->activate($this->member(13)); // Jane: no assignments at all
        $this->assertSame([2], $access->complexIds(), 'company-managed Westlands only');
        $this->assertSame([$w->id], TenantManagement::pluck('id')->all());

        $access->activate($this->member(11)); // Grace: primary manager of Mlimani
        $this->assertEqualsCanonicalizing([1, 2], $access->complexIds());
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $w->id], TenantManagement::pluck('id')->all(), 'the primary oversees every unit');

        $access->activate($this->member(12)); // Kevin: only his unit at Mlimani
        $this->assertSame([1 => ['residential' => [2], 'commercial' => []]], $access->unitScope());
        $this->assertEqualsCanonicalizing([$b->id, $w->id], TenantManagement::pluck('id')->all(), 'his unit\'s tenancy, not the rest of Mlimani');
        $this->assertSame([2], ComplexResidentialUnit::pluck('id')->all());
        $this->assertEqualsCanonicalizing([$b->id, $w->id], \App\Models\TenantInvoice::pluck('tenancy_id')->unique()->values()->all(), 'and only their bills');
        $this->assertFalse($access->allowsUnit(1, 'residential', 1));
        $this->assertTrue($access->allowsUnit(1, 'residential', 2));

        $access->activate($this->member(12, 'all')); // an "all properties" role is not limited
        $this->assertNull($access->complexIds());
        $access->deactivate();
    }

    // ─── Unit-level assignment ──────────────────────────────────────────────

    public function test_units_are_assigned_reassigned_and_removed_with_history_and_their_tenancies_follow(): void
    {
        $t = $this->lease('residential', 2, ['assigned_agent_id' => 1]);

        $this->assertSame(2, $this->assignUnits('residential', [2, 3], 2), 'one agent, several units');
        $this->assignUnits('residential', [1], 1);                                // Grace keeps A-1 herself
        $this->assertSame(2, (int) $t->fresh()->assigned_agent_id, 'the tenancy follows its unit');

        Carbon::setTestNow('2026-11-20 10:00');
        $this->assignUnits('residential', [2], 3);                                // B-1 moves to Jane
        $this->assignUnits('residential', [3], null);                             // C-1 unassigned
        $this->assertSame(3, (int) $t->fresh()->assigned_agent_id);

        $this->assertSame([1 => 1, 2 => 3, 3 => null], ComplexResidentialUnit::orderBy('id')->pluck('assigned_agent_id', 'id')->map(fn ($v) => $v ? (int) $v : null)->all());
        $rows = AssignmentHistory::where('scope', 'residential_unit')->where('unit_id', 2)->orderBy('id')->get();
        $this->assertSame([[2, 'sub_agent'], [3, 'sub_agent']], $rows->map(fn ($r) => [(int) $r->agent_id, $r->role])->all());
        $this->assertSame('primary', AssignmentHistory::where('scope', 'residential_unit')->where('unit_id', 1)->value('role'));
        $this->assertSame(2, app(AssignmentHistoryService::class)->unitAgentOn('residential', 2, '2026-11-16'));
        $this->assertNull(app(AssignmentHistoryService::class)->unitAgentOn('residential', 3, '2026-11-21'), 'unassigned since the 20th');
        $this->assertSame(1, ComplexResidentialUnit::whereNull('assigned_agent_id')->count(), 'unassigned units are easy to find');
    }

    public function test_who_may_delegate_units_on_individual_and_company_properties(): void
    {
        $service = app(AgentAssignmentService::class);
        $mlimani = Complex::find(1);
        $westlands = Complex::find(2);

        $this->actingAs(User::find(11)); // Grace: primary of Mlimani, can edit properties
        app(PropertyAccess::class)->activate($this->member(11));
        $this->assertTrue($service->canAssignUnits(User::find(11), $mlimani), 'the primary delegates her property\'s units');
        $this->assertTrue($service->canAssignUnits(User::find(11), $westlands), 'a property editor delegates company property units');
        $this->assertSame(1, $service->assignUnits(User::find(11), $westlands, 'commercial', [1], 2));

        $this->actingAs(User::find(12)); // Kevin: view only
        app(PropertyAccess::class)->activate($this->member(12));
        $this->assertFalse($service->canAssignUnits(User::find(12), $westlands));
        $this->expectException(HttpException::class);
        $service->assignUnits(User::find(12), $westlands, 'commercial', [2], 2);
    }

    public function test_an_agent_who_stops_working_loses_units_and_their_property_becomes_company_managed(): void
    {
        $this->assignUnits('residential', [1], 1);
        Agent::find(1)->update(['is_active' => false]); // Grace leaves

        $this->assertTrue(Complex::find(1)->isCompanyManaged(), 'the property is still managed: by the company now');
        $this->assertNull(ComplexResidentialUnit::find(1)->assigned_agent_id);
        $this->assertSame('company', AssignmentHistory::where('complex_id', 1)->where('scope', 'property')->whereNull('ends_at')->value('assignee_type'));
        $this->assertSame('Agent switched off', AssignmentHistory::where('scope', 'residential_unit')->where('unit_id', 1)->value('reason'));
    }

    // ─── Commission ─────────────────────────────────────────────────────────

    public function test_company_fee_and_primary_commission_on_an_individually_managed_property(): void
    {
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 10]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 1, 'rate' => 3]);
        $this->account->record($this->lease('residential', 1), $this->pay(35000));

        $e = $this->entries();
        $this->assertSame([['company', null, 'management_fee', 3500.0], ['agent', 1, 'agent', 1050.0]], $e->map(fn ($x) => [$x->beneficiary_type, $x->agent_id, $x->entitlement, $x->amount])->all());
        $this->assertSame('company_fee', $e[1]->funding);
        $this->assertSame(['individual', 1], [$e[0]->management_type, (int) $e[0]->primary_agent_id]);
        $s = app(CommissionReportService::class)->summary(10, ['from' => '2026-11-01', 'to' => '2026-11-30']);
        $this->assertSame([3500.0, 1050.0, 2450.0], [$s['gross_fee'], $s['agent_from_fee'], $s['company_net']], 'company keeps 10% − 3%');
    }

    public function test_sub_agents_earn_on_their_units_and_the_primary_earns_on_delegated_units_without_double_counting(): void
    {
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 10]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'company', 'rate' => 3]);                  // every agent 3%
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 3, 'rate' => 4]);    // Jane 4%
        $this->agreement(['entitlement' => 'primary_on_delegated', 'scope' => 'agent', 'agent_id' => 1, 'rate' => 1]); // Grace 1% on delegated units
        $this->assignUnits('residential', [1], 1);
        $this->assignUnits('residential', [2], 2);
        $this->assignUnits('residential', [3], 3);
        $this->account->record($this->lease('residential', 1), $this->pay(35000));
        $this->account->record($this->lease('residential', 2, ['tenant_profile_id' => 2]), $this->pay(35000));
        $this->account->record($this->lease('residential', 3, ['tenant_profile_id' => 3]), $this->pay(20000)); // a partial payment

        $by = fn ($agent, $ent) => (float) CommissionEntry::where('agent_id', $agent)->where('entitlement', $ent)->sum('amount');
        $this->assertSame(1050.0, $by(1, 'agent'), 'Grace 3% on her own unit');
        $this->assertSame(1050.0, $by(2, 'agent'), 'Kevin 3% on B-1');
        $this->assertSame(800.0, $by(3, 'agent'), 'Jane 4% on the 20,000 collected so far');
        $this->assertSame(550.0, $by(1, 'primary_on_delegated'), 'Grace 1% on the delegated 35,000 + 20,000 only');
        $this->assertSame(0, CommissionEntry::where('agent_id', 1)->where('entitlement', 'primary_on_delegated')->whereIn('unit_id', [1])->count(), 'never on her own unit');
        $s = app(CommissionReportService::class)->summary(10, ['from' => '2026-11-01', 'to' => '2026-11-30']);
        $this->assertSame([1050.0, 1850.0, 550.0], [$s['own_units'], $s['sub_agents'], $s['primary']], 'the breakdown adds up to every agent shilling');
        $this->assertSame($s['agent_total'], round($s['own_units'] + $s['sub_agents'] + $s['primary'], 2));
    }

    public function test_share_of_pool_splits_one_rate_between_primary_and_sub_agent(): void
    {
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 10]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 2, 'rate' => 5]); // ignored where the pool applies
        $this->agreement(['entitlement' => 'primary_on_delegated', 'scope' => 'property', 'complex_id' => 1, 'rate' => 4, 'sharing' => 'share_of_pool', 'sub_share_pct' => 75]);
        $this->assignUnits('residential', [2], 2);
        $this->account->record($this->lease('residential', 2), $this->pay(10000));

        $rows = $this->entries(['beneficiary_type' => 'agent'])->map(fn ($e) => [(int) $e->agent_id, $e->entitlement, $e->rate, $e->amount])->all();
        $this->assertSame([[2, 'agent', 3.0, 300.0], [1, 'primary_on_delegated', 1.0, 100.0]], $rows, '4% pool: 3% to Kevin, 1% to Grace');
    }

    public function test_a_company_property_earns_company_commission_with_unit_agents_and_no_primary(): void
    {
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'property', 'complex_id' => 2, 'rate' => 8]);
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 10]); // the property rate wins at Westlands
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 2, 'rate' => 3]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 3, 'rate' => 2, 'funding' => 'additional_cost']);
        $this->assignUnits('commercial', [1], 2);
        $this->assignUnits('commercial', [2], 3);
        $this->account->record($this->lease('commercial', 1), $this->pay(35000));
        $this->account->record($this->lease('commercial', 2, ['tenant_profile_id' => 2]), $this->pay(35000));
        $this->account->record($this->lease('residential', 1), $this->pay(35000)); // Mlimani: company default fee

        $fees = CommissionEntry::where('entitlement', 'management_fee')->orderBy('id')->pluck('amount', 'complex_id')->all();
        $this->assertSame([2 => 2800.0, 1 => 3500.0], array_map('floatval', $fees));
        $this->assertSame(0, CommissionEntry::where('entitlement', 'primary_on_delegated')->count(), 'no primary manager to share with');
        $this->assertNull(CommissionEntry::where('complex_id', 2)->value('primary_agent_id'));
        $s = app(CommissionReportService::class)->summary(10, ['from' => '2026-11-01', 'to' => '2026-11-30', 'complex_id' => 2]);
        $this->assertSame([5600.0, 1050.0, 700.0, 4550.0], [$s['gross_fee'], $s['agent_from_fee'], $s['additional_cost'], $s['company_net']],
            '8% of 70,000; Kevin\'s 3% comes out of the fee; Jane\'s 2% is an additional cost on top');
    }

    public function test_commission_beyond_the_company_fee_waits_for_approval_and_no_fee_means_additional_cost(): void
    {
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 2, 'rate' => 3]);
        $this->assignUnits('commercial', [1], 2);
        $this->account->record($this->lease('commercial', 1), $this->pay(10000));
        $first = CommissionEntry::sole();
        $this->assertSame(['additional_cost', 'earned'], [$first->funding, $first->status], 'no company fee: recorded as an additional cost');
        $this->assertStringContainsString('No company fee', $first->breakdown['note']);

        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 2], '2026-11-15');
        try { $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 3, 'rate' => 5], '2026-11-15'); $this->fail('more than the fee'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('rate', $e->errors()); }

        // An older agreement still over the fee: the entry waits for approval.
        $this->account->record($this->lease('commercial', 2, ['tenant_profile_id' => 2, 'assigned_agent_id' => 2]), $this->pay(10000));
        $over = CommissionEntry::where('allocation_id', '!=', $first->allocation_id)->where('beneficiary_type', 'agent')->sole();
        $this->assertSame('pending', $over->status);
        app(CommissionLedgerService::class)->approve([$over->id], 10);
        $this->assertSame('approved', $over->fresh()->status);
    }

    public function test_multiple_payments_on_one_invoice_and_arrears_keep_their_rental_period(): void
    {
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 10]);
        $t = $this->lease('residential', 1);
        $this->account->record($t, $this->pay(20000));
        Carbon::setTestNow('2026-12-20 09:00');
        $this->charges->billUpTo($t->fresh(), Carbon::now());
        $this->account->record($t, $this->pay(30000, ['payment_date' => '2026-12-20'])); // 15,000 November balance + 15,000 of December

        $fee = CommissionEntry::where('entitlement', 'management_fee')->orderBy('id')->get();
        $this->assertSame([2000.0, 1500.0, 1500.0], $fee->pluck('amount')->all(), 'commission only on what was collected, each time');
        $this->assertSame(['2026-11-15', '2026-12-20', '2026-12-20'], $fee->map(fn ($e) => $e->collected_on->toDateString())->all());
        $this->assertSame($fee[0]->rental_period, $fee[1]->rental_period, 'November arrears paid in December keep November as their rental period');
        $this->assertNotSame($fee[1]->rental_period, $fee[2]->rental_period);
    }

    public function test_reversals_cancel_unpaid_commission_and_claw_back_paid_commission(): void
    {
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 2, 'rate' => 5]);
        $this->assignUnits('commercial', [1, 2], 2);
        $bounced = $this->account->record($this->lease('commercial', 1), $this->pay(10000));
        $this->account->reverse($bounced, 'Cheque bounced');
        $original = CommissionEntry::where('kind', 'payment')->sole();
        $reversal = CommissionEntry::where('kind', 'reversal')->sole();
        $this->assertSame(['reversed', 'reversed', -500.0, $original->id], [$original->status, $reversal->status, $reversal->amount, (int) $reversal->reverses_entry_id]);

        $paid = $this->account->record($this->lease('commercial', 2, ['tenant_profile_id' => 2]), $this->pay(10000));
        $service = app(AgentCommissionService::class);
        $service->recordPayout(Agent::find(2), '2026-11-01', '2026-11-30', ['method' => 'bank_transfer']);
        $this->account->reverse($paid, 'Entered against the wrong tenant');
        $claw = CommissionEntry::where('kind', 'reversal')->latest('id')->first();
        $this->assertSame(['earned', -500.0], [$claw->status, $claw->amount], 'clawed back from the next payout');
        $this->assertSame('paid', CommissionEntry::find($claw->reverses_entry_id)->status, 'the paid entry stays as it was');
        $this->assertSame(500.0, $service->statement(Agent::find(2), '2026-12-01', '2026-12-31')['due']['clawback']);
        $this->assertSame(1, CommissionEntry::where('kind', 'reversal')->where('reverses_entry_id', $claw->reverses_entry_id)->count(), 'one reversal per entry');
    }

    public function test_reassigning_a_unit_does_not_move_commission_already_earned_and_backdated_rent_follows_the_date(): void
    {
        $this->agreement(['entitlement' => 'agent', 'scope' => 'company', 'rate' => 5]);
        Carbon::setTestNow('2027-01-02 09:00');
        $this->assignUnits('residential', [2], 2); // Kevin from 2 January
        $t = $this->lease('residential', 2, ['move_in_date' => '2027-01-01', 'lease_start_date' => '2027-01-01']);
        $this->account->record($t, $this->pay(20000, ['payment_date' => '2027-01-10']));

        Carbon::setTestNow('2027-02-01 09:00');
        $this->assignUnits('residential', [2], 3); // Jane from 1 February
        $this->charges->billUpTo($t->fresh(), Carbon::now());
        $this->account->record($t, $this->pay(15000, ['payment_date' => '2027-01-31'])); // January arrears paid on 31 January, recorded later
        $this->account->record($t, $this->pay(35000, ['payment_date' => '2027-02-01']));

        $by = fn ($agent) => (float) CommissionEntry::where('agent_id', $agent)->sum('amount');
        $this->assertSame(1750.0, $by(2), 'Kevin: 20,000 + 15,000 collected while he held the unit');
        $this->assertSame(1750.0, $by(3), 'Jane: February\'s 35,000');
    }

    public function test_changing_an_agreement_keeps_history_and_precedence_picks_the_most_specific(): void
    {
        $agreements = app(CommissionAgreementService::class);
        $company = $this->agreement(['entitlement' => 'agent', 'scope' => 'company', 'rate' => 2]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 2, 'rate' => 3]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'property', 'complex_id' => 2, 'rate' => 4]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent_property', 'agent_id' => 2, 'complex_id' => 2, 'rate' => 5]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'unit', 'complex_id' => 2, 'unit_type' => 'commercial', 'unit_id' => 2, 'rate' => 6]);
        $rate = fn ($ctx) => $agreements->resolve(10, 'agent', $ctx, '2026-11-15')?->rate;
        $this->assertSame(6.0, $rate(['agent_id' => 2, 'complex_id' => 2, 'unit_type' => 'commercial', 'unit_id' => 2]));
        $this->assertSame(5.0, $rate(['agent_id' => 2, 'complex_id' => 2, 'unit_type' => 'commercial', 'unit_id' => 1]));
        $this->assertSame(4.0, $rate(['agent_id' => 3, 'complex_id' => 2]));
        $this->assertSame(3.0, $rate(['agent_id' => 2, 'complex_id' => 1]));
        $this->assertSame(2.0, $rate(['agent_id' => 3, 'complex_id' => 1]));

        $this->assignUnits('residential', [1], 3);
        $this->account->record($this->lease('residential', 1), $this->pay(10000)); // Jane at Mlimani: company default 2%
        $new = $agreements->save(10, ['entitlement' => 'agent', 'scope' => 'company', 'rate' => 2.5, 'method' => 'percent_collected', 'effective_from' => '2026-12-01'], $company, 'Raised from December');

        $this->assertSame('2026-11-30', $company->fresh()->effective_to->toDateString());
        $this->assertSame([2.0, 200.0], [CommissionEntry::sole()->rate, CommissionEntry::sole()->amount], 'earned commission keeps its rate');
        $this->assertSame($company->id, (int) CommissionEntry::sole()->agreement_id);
        $this->assertSame(2.5, $agreements->resolve(10, 'agent', ['agent_id' => 3, 'complex_id' => 1], '2026-12-02')->rate);
        $this->assertSame((int) $company->id, (int) $new->replaces_id);
    }

    public function test_the_same_collection_is_never_recorded_twice_and_reports_reconcile_with_rent(): void
    {
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 10]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'company', 'rate' => 2]);
        $this->assignUnits('residential', [1], 2);
        $this->account->record($this->lease('residential', 1), $this->pay(35000));
        $this->account->record($this->lease('commercial', 1), $this->pay(12000));

        $ledger = app(CommissionLedgerService::class);
        $count = CommissionEntry::count();
        foreach (\App\Models\TenantInvoicePaymentAllocation::all() as $allocation) {
            $this->assertSame(0, $ledger->recordAllocation($allocation));
        }
        $this->assertSame(0, $ledger->backfill(10));
        $this->assertSame($count, CommissionEntry::count());

        $s = app(CommissionReportService::class)->summary(10, ['from' => '2026-11-01', 'to' => '2026-11-30']);
        $this->assertSame(47000.0, $s['rent_collected']);
        $this->assertSame($s['rent_collected'], $s['rent_recorded'], 'every shilling of rent is in the ledger');
        $this->assertSame(4700.0, $s['gross_fee']);
    }

    public function test_period_methods_are_calculated_for_approval_and_adjusted_after_approval(): void
    {
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 2, 'method' => 'flat_per_tenant', 'flat_amount' => 1000]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'agent', 'agent_id' => 3, 'method' => 'tiered', 'tiers' => [['min' => 0, 'max' => 30000, 'rate' => 2], ['min' => 30000, 'max' => null, 'rate' => 4]]]);
        $this->assignUnits('commercial', [1, 2], 2);
        $this->assignUnits('residential', [1], 3);
        $this->account->record($this->lease('commercial', 1), $this->pay(35000));
        $second = $this->account->record($this->lease('commercial', 2, ['tenant_profile_id' => 2]), $this->pay(35000));
        $this->account->record($this->lease('residential', 1), $this->pay(35000));
        $this->assertSame(0, CommissionEntry::where('kind', 'payment')->count(), 'period methods are not recorded per payment');

        $periods = app(CommissionPeriodService::class);
        $periods->calculate(10, '2026-11-01', '2026-11-30');
        $kevin = CommissionEntry::where('agent_id', 2)->where('kind', 'period')->sole();
        $jane = CommissionEntry::where('agent_id', 3)->where('kind', 'period')->sole();
        $this->assertSame([2000.0, 'pending'], [$kevin->amount, $kevin->status], 'two paying tenants × 1,000');
        $this->assertSame(1400.0, $jane->amount, '35,000 falls in the 4% tier');
        $periods->calculate(10, '2026-11-01', '2026-11-30');
        $this->assertSame(2, CommissionEntry::where('kind', 'period')->count(), 'recalculating replaces, never duplicates');

        app(CommissionLedgerService::class)->approve([$kevin->id, $jane->id], 10);
        $this->account->reverse($second, 'Bounced');
        $periods->calculate(10, '2026-11-01', '2026-11-30');
        $this->assertSame(2000.0, $kevin->fresh()->amount, 'an approved entry is never edited');
        $adjustment = CommissionEntry::where('agent_id', 2)->where('kind', 'adjustment')->sole();
        $this->assertSame([-1000.0, 'pending'], [$adjustment->amount, $adjustment->status], 'the bounced tenant comes off as an adjustment');
    }

    // ─── Who sees which commission ──────────────────────────────────────────

    /** Kevin (agent 2) works B-1 at Grace's Mlimani; Grace keeps A-1; commission is recorded for both and the company. */
    private function commissionForTwoAgents(): void
    {
        $this->agreement(['entitlement' => 'management_fee', 'scope' => 'company', 'rate' => 10]);
        $this->agreement(['entitlement' => 'agent', 'scope' => 'company', 'rate' => 3]);
        $this->agreement(['entitlement' => 'primary_on_delegated', 'scope' => 'agent', 'agent_id' => 1, 'rate' => 1]);
        $this->assignUnits('residential', [1], 1);
        $this->assignUnits('residential', [2], 2);
        $this->account->record($this->lease('residential', 1), $this->pay(35000));
        $this->account->record($this->lease('residential', 2, ['tenant_profile_id' => 2]), $this->pay(20000));
        $this->account->record($this->lease('commercial', 1), $this->pay(10000)); // Westlands: company-managed, nobody responsible
    }

    /** Kevin signed in with an agent's view-only role limited to assigned properties. */
    private function signInAsKevin(): User
    {
        DB::table('users')->where('id', 12)->update(['business_permissions' => json_encode(['complex_management' => ['view'], 'tenant_management' => ['view'], 'agent_management' => ['view']])]);
        $kevin = User::find(12);
        $kevin->setRelation('businessRole', new BusinessRole(['property_scope' => 'assigned', 'permissions' => [], 'is_active' => true]));
        $this->actingAs($kevin);
        app(PropertyAccess::class)->activate($kevin);
        return $kevin;
    }

    public function test_a_unit_only_viewer_sees_commission_and_rent_for_their_units_only(): void
    {
        $this->commissionForTwoAgents();
        $this->signInAsKevin();
        $reports = app(CommissionReportService::class);
        $f = ['from' => '2026-11-01', 'to' => '2026-11-30'];

        $lines = $reports->lines(10, $f)->get();
        $this->assertEqualsCanonicalizing(['residential 2', 'commercial 1'], $lines->map(fn ($l) => $l->unit_type . ' ' . $l->unit_id)->unique()->values()->all(),
            'B-1 at Mlimani and company-managed Westlands: not Grace\'s A-1');
        $this->assertSame(0, $lines->where('unit_type', 'residential')->where('unit_id', 1)->count());
        $s = $reports->summary(10, $f);
        $this->assertSame([30000.0, 30000.0, 3000.0], [$s['rent_collected'], $s['rent_recorded'], $s['gross_fee']], 'rent and the ledger still reconcile within his scope');
        $this->assertEquals([1 => 20000.0, 2 => 10000.0], $reports->grouped(10, $f, 'property')->mapWithKeys(fn ($r) => [$r->complex_id => (float) $r->rent])->all());
        app(PropertyAccess::class)->deactivate();
    }

    public function test_an_agent_with_view_only_access_sees_only_their_own_commission(): void
    {
        $this->commissionForTwoAgents();
        $this->signInAsKevin();
        $controller = app(\App\Http\Controllers\Business\Agents\AgentCommissionController::class);

        $data = $controller->index(\Illuminate\Http\Request::create('/agents/commissions', 'GET', ['tab' => 'ledger']))->getData();
        $this->assertSame(2, $data['ownAgent']->id);
        $this->assertSame(['summary', 'ledger', 'properties', 'units'], array_keys($data['tabs']));
        $this->assertSame([[2, 'agent', 600.0]], collect($data['lines']->items())->map(fn ($l) => [(int) $l->agent_id, $l->entitlement, (float) $l->amount])->all(),
            'his 3% of 20,000: not the company fee, not Grace\'s commission');
        $sneaky = $controller->index(\Illuminate\Http\Request::create('/agents/commissions', 'GET', ['tab' => 'ledger', 'agent_id' => 1, 'beneficiary' => 'company']))->getData();
        $this->assertSame([2], collect($sneaky['lines']->items())->pluck('agent_id')->map(fn ($v) => (int) $v)->unique()->values()->all(), 'filters cannot widen it');
        $this->assertSame(600.0, $controller->index(\Illuminate\Http\Request::create('/agents/commissions', 'GET'))->getData()['summary']['agent_total']);

        foreach (['agreements', 'periods', 'agents'] as $tab) {
            try { $controller->index(\Illuminate\Http\Request::create('/agents/commissions', 'GET', ['tab' => $tab])); $this->fail("$tab must be refused"); }
            catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
        try { $controller->statement(\Illuminate\Http\Request::create('/agents/commissions/1/statement'), 1); $this->fail('Grace\'s statement must be refused'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->assertSame(2, $controller->statement(\Illuminate\Http\Request::create('/agents/commissions/2/statement'), 2)->getData()['agent']->id);
        app(PropertyAccess::class)->deactivate();
    }

    public function test_the_owner_and_agent_editors_still_see_all_commission(): void
    {
        $this->commissionForTwoAgents();
        $controller = app(\App\Http\Controllers\Business\Agents\AgentCommissionController::class);
        $data = $controller->index(\Illuminate\Http\Request::create('/agents/commissions', 'GET', ['tab' => 'ledger']))->getData();
        $this->assertNull($data['ownAgent']);
        $this->assertCount(7, $data['tabs']);
        $this->assertEqualsCanonicalizing(['residential 1', 'residential 2', 'commercial 1'], collect($data['lines']->items())->map(fn ($l) => $l->unit_type . ' ' . $l->unit_id)->unique()->values()->all());
        $this->assertEqualsCanonicalizing([0, 1, 2], collect($data['lines']->items())->map(fn ($l) => (int) $l->agent_id)->unique()->values()->all(), 'the company, Grace and Kevin');
    }

    public function test_a_unit_only_agent_logs_repairs_only_for_their_units_and_tenancies(): void
    {
        $this->assignUnits('residential', [2], 2); // Kevin: B-1 only
        $grace = $this->lease('residential', 1);
        $this->lease('residential', 2, ['tenant_profile_id' => 2]);
        $this->signInAsKevin();
        DB::table('users')->where('id', 12)->update(['business_permissions' => json_encode(['complex_management' => ['view'], 'tenant_management' => ['view', 'create'], 'maintenance_management' => ['view', 'log']])]);
        auth()->user()->refresh()->setRelation('businessRole', new BusinessRole(['property_scope' => 'assigned', 'permissions' => [], 'is_active' => true]));
        $controller = app(\App\Http\Controllers\Business\Maintenance\MaintenanceRequestController::class);
        $log = fn (array $extra) => $controller->store(\Illuminate\Http\Request::create('/maintenance', 'POST', $extra + ['complex_id' => 1, 'title' => 'Leaking tap', 'category' => 'plumbing', 'priority' => 'normal']));

        foreach ([['unit' => 'r:1'], ['unit' => 'r:2', 'tenancy_id' => $grace->id]] as $attempt) {
            try { $log($attempt); $this->fail('Grace\'s unit or tenancy must be refused: ' . json_encode($attempt)); }
            catch (HttpException $e) { $this->assertSame(422, $e->getStatusCode()); }
        }
        $this->assertSame(0, DB::table('maintenance_requests')->count());
        $log(['unit' => 'r:2']);
        $this->assertSame([2], DB::table('maintenance_requests')->pluck('residential_unit_id')->map(fn ($v) => (int) $v)->all(), 'his own unit works');
        app(PropertyAccess::class)->deactivate();
    }
}
