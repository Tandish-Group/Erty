<?php

namespace Tests\Feature;

use App\Models\{Agent, BusinessRole, Lead, User};
use App\Services\Agents\AgentProfileService;
use App\Services\Access\PropertyAccess;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use App\Services\Rent\TenantReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Tests\TestCase;

/**
 * What people limited to assigned properties can see, for the places that read data outside Eloquent's
 * automatic limit (reports, raw queries, commission, viewings, lead contacts).
 *
 * Mlimani (1): units A-1, B-1; managed by an external agent. Westlands (2): unit W-1; also external.
 * Grace (11, Property Manager) is assigned Mlimani. Kevin (12, agent 2) holds only B-1. Fiona (13, Finance) sees all.
 */
class RoleVisibilityTest extends TestCase
{
    use \Tests\Concerns\TenantAccountsSchema;
    use \Tests\Concerns\CreatesAssignmentSchema;

    private array $t = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'visibility_test', 'database.connections.visibility_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Mail::fake();
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->schema();
        $this->createCommissionSchema();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_suspended')->default(false));
        Schema::create('business_roles', function (Blueprint $t) { $t->id(); $t->integer('business_id'); $t->string('name'); $t->string('department', 60)->nullable(); $t->json('permissions')->nullable(); $t->string('property_scope')->default('all'); $t->boolean('is_active')->default(true); $t->timestamps(); });
        Schema::create('notifications', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('type'); $t->morphs('notifiable'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
        Schema::create('leads', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('complex_id')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->integer('lead_profile_id')->nullable(); $t->string('status')->default('new'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('lead_profiles', function (Blueprint $t) { $t->id(); $t->string('lead_type')->default('Individual'); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->string('company_name')->nullable(); $t->string('business_email')->nullable(); $t->string('business_phone')->nullable(); $t->string('contact_person')->nullable(); $t->timestamps(); });
        Schema::create('listings', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('complex_id')->nullable(); $t->string('name')->nullable(); $t->string('slug')->nullable(); $t->timestamps(); });

        DB::table('business_roles')->insert([
            ['id' => 1, 'business_id' => 10, 'name' => 'Property Manager', 'property_scope' => 'assigned', 'permissions' => json_encode(['complex_management' => ['view', 'edit'], 'tenant_management' => ['view', 'reports'], 'lead_management' => ['view']])],
            ['id' => 2, 'business_id' => 10, 'name' => 'Leasing Agent', 'property_scope' => 'assigned', 'permissions' => json_encode(['complex_management' => ['view'], 'tenant_management' => ['view', 'reports'], 'lead_management' => ['view'], 'agent_management' => ['view']])],
            ['id' => 3, 'business_id' => 10, 'name' => 'Finance Officer', 'property_scope' => 'all', 'permissions' => json_encode(['tenant_management' => ['view', 'payments', 'reports'], 'agent_management' => ['view']])],
        ]);
        DB::table('users')->insert([
            ['id' => 10, 'first_name' => 'Owner', 'last_name' => 'One', 'email' => 'o@x.test', 'is_sub_account' => false, 'main_account_user_id' => null, 'business_role_id' => null],
            ['id' => 11, 'first_name' => 'Grace', 'last_name' => 'PM', 'email' => 'g@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 1],
            ['id' => 12, 'first_name' => 'Kevin', 'last_name' => 'Agent', 'email' => 'k@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 2],
            ['id' => 13, 'first_name' => 'Fiona', 'last_name' => 'Finance', 'email' => 'f@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 3],
        ]);
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Tandish']);
        DB::table('agents')->insert([
            ['id' => 2, 'created_by' => 10, 'user_id' => 12, 'first_name' => 'Kevin', 'last_name' => 'Agent', 'email' => 'k@x.test', 'is_active' => true],
            ['id' => 3, 'created_by' => 10, 'user_id' => null, 'first_name' => 'Jane', 'last_name' => 'Other', 'email' => 'j@x.test', 'is_active' => true],
            ['id' => 9, 'created_by' => 10, 'user_id' => null, 'first_name' => 'External', 'last_name' => 'Agency', 'email' => 'e@x.test', 'is_active' => true],
        ]);
        DB::table('complexes')->insert([
            ['id' => 1, 'user_id' => 10, 'title' => 'Mlimani', 'slug' => 'mlimani', 'assigned_agent_id' => 9, 'management_type' => 'individual'],
            ['id' => 2, 'user_id' => 10, 'title' => 'Westlands', 'slug' => 'westlands', 'assigned_agent_id' => 9, 'management_type' => 'individual'],
        ]);
        DB::table('complex_user')->insert(['complex_id' => 1, 'user_id' => 11]);
        DB::table('complex_residential_units')->insert([
            ['id' => 1, 'complex_id' => 1, 'unit_number' => 'A-1', 'status' => 'available', 'assigned_agent_id' => null],
            ['id' => 2, 'complex_id' => 1, 'unit_number' => 'B-1', 'status' => 'available', 'assigned_agent_id' => 2],
            ['id' => 3, 'complex_id' => 2, 'unit_number' => 'W-1', 'status' => 'available', 'assigned_agent_id' => null],
        ]);
        DB::table('tenant_profiles')->insert([['id' => 1, 'tenant_type' => 'Individual', 'tenant_name' => 'Amani', 'managed_by' => 10], ['id' => 2, 'tenant_type' => 'Individual', 'tenant_name' => 'Baraka', 'managed_by' => 10], ['id' => 3, 'tenant_type' => 'Individual', 'tenant_name' => 'Chebet', 'managed_by' => 10]]);

        $this->actingAs(User::find(10));
        foreach ([['a', 1, 1, 1], ['b', 1, 2, 2], ['w', 2, 3, 3]] as [$key, $complex, $unit, $profile]) {
            $tenancy = $this->tenancy(['complex_id' => $complex, 'residential_unit_id' => $unit, 'tenant_profile_id' => $profile, 'security_deposit' => 0, 'unit_number' => strtoupper($key) . '-1']);
            app(TenantChargesService::class)->startBilling($tenancy);
            app(TenantAccountService::class)->record($tenancy, $this->pay(10000));
            $this->t[$key] = $tenancy->fresh();
        }
    }

    protected function tearDown(): void
    {
        app(PropertyAccess::class)->deactivate();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function as(int $id): User
    {
        $user = User::with('businessRole')->find($id);
        $this->actingAs($user);
        app(PropertyAccess::class)->activate($user);
        return $user;
    }

    public function test_raw_queries_keep_a_unit_only_agent_to_their_units(): void
    {
        $this->as(12);
        $ids = app(PropertyAccess::class)->constrain(DB::table('tenant_management'), 'complex_id', ['residential' => 'residential_unit_id', 'commercial' => 'commercial_unit_id'])->pluck('id')->all();
        $this->assertSame([$this->t['b']->id], $ids, 'only B-1, not A-1 next door or Westlands');
        $this->assertCount(2, app(PropertyAccess::class)->constrain(DB::table('tenant_management'), 'complex_id')->get(), 'without unit columns it stays property-level');
    }

    public function test_the_money_report_shows_only_the_viewers_tenancies(): void
    {
        $refs = fn () => collect(app(TenantReportService::class)->getPayments('2026-01-01', '2026-12-31'))->pluck('tenancy_ref')->unique()->sort()->values()->all();
        $this->as(13);
        $this->assertCount(3, $refs(), 'finance sees every property');
        $this->as(11);
        $this->assertEqualsCanonicalizing([$this->t['a']->tenancy_ref, $this->t['b']->tenancy_ref], $refs(), 'Grace: Mlimani only');
        $this->as(12);
        $this->assertSame([$this->t['b']->tenancy_ref], $refs(), 'Kevin: B-1 only');
    }

    public function test_leads_on_a_property_where_someone_only_holds_units_are_theirs_alone(): void
    {
        DB::table('leads')->insert([
            ['id' => 1, 'user_id' => 10, 'complex_id' => 1, 'assigned_agent_id' => 3],
            ['id' => 2, 'user_id' => 10, 'complex_id' => 1, 'assigned_agent_id' => 2],
            ['id' => 3, 'user_id' => 10, 'complex_id' => 2, 'assigned_agent_id' => null],
        ]);
        $this->as(12);
        $this->assertSame([2], Lead::pluck('id')->all(), 'Kevin: his own lead, not Jane\'s lead at Mlimani');
        $this->as(11);
        $this->assertEqualsCanonicalizing([1, 2], Lead::pluck('id')->all(), 'Grace works Mlimani in full');
    }

    public function test_lead_contact_search_only_returns_contacts_of_leads_they_can_see(): void
    {
        DB::table('lead_profiles')->insert([['id' => 1, 'first_name' => 'Wanjiru', 'last_name' => 'A'], ['id' => 2, 'first_name' => 'Wambui', 'last_name' => 'B']]);
        DB::table('leads')->insert([['id' => 1, 'user_id' => 10, 'complex_id' => 1, 'assigned_agent_id' => 3, 'lead_profile_id' => 1], ['id' => 2, 'user_id' => 10, 'complex_id' => 1, 'assigned_agent_id' => 2, 'lead_profile_id' => 2]]);
        $this->as(12);
        $results = app(\App\Http\Controllers\Business\Crm\LeadManagementController::class)
            ->searchProfiles(\Illuminate\Http\Request::create('/x', 'GET', ['q' => 'Wa']))->getData(true)['results'];
        $this->assertSame([2], array_column($results, 'id'));
    }

    public function test_commission_of_others_is_only_for_people_who_see_every_property(): void
    {
        $kevin = Agent::find(2);
        $jane = Agent::find(3);
        $this->assertTrue(AgentProfileService::canSeeCommissionOf($jane, User::find(10)), 'owner');
        $this->assertTrue(AgentProfileService::canSeeCommissionOf($kevin, User::with('businessRole')->find(12)), 'their own');
        $this->assertFalse(AgentProfileService::canSeeCommissionOf($jane, User::with('businessRole')->find(12)), 'an agent: not someone else\'s');
        $this->assertTrue(AgentProfileService::canSeeCommissionOf($jane, User::with('businessRole')->find(13)), 'finance across all properties');
        DB::table('users')->where('id', 11)->update(['business_permissions' => json_encode(['agent_management' => ['view']])]);
        $this->assertFalse(AgentProfileService::canSeeCommissionOf($jane, User::with('businessRole')->find(11)), 'a property manager limited to some properties');
    }

    public function test_the_tenants_reports_skip_tenancies_the_viewer_cannot_see(): void
    {
        $this->as(12);
        $this->assertSame([$this->t['b']->tenancy_ref], collect(app(TenantReportService::class)->getAccounts())->pluck('tenancy_ref')->all());
    }
}
