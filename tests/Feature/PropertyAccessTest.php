<?php

namespace Tests\Feature;

use App\Models\{BusinessRole, Complex, ComplexExpense, Lead, LeadTask, TenantInvoice, TenantManagement, TenantProfile, User};
use App\Services\Access\PropertyAccess;
use App\Services\Agents\PropertyAssignmentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Roles limited to "only properties assigned to the member": every property-linked record is
 * scoped to the member's assigned properties, only while PropertyAccess is activated.
 */
class PropertyAccessTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'access_test', 'database.connections.access_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();

        Schema::create('complexes', function (Blueprint $t) { $t->id(); $t->string('title'); $t->string('slug')->nullable(); $t->integer('user_id'); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('complex_user', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->integer('user_id'); $t->integer('assigned_by')->nullable(); $t->timestamps(); $t->unique(['complex_id', 'user_id']); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('email')->nullable(); $t->boolean('is_sub_account')->default(false); $t->integer('main_account_user_id')->nullable(); $t->boolean('is_suspended')->default(false); $t->timestamps(); });
        Schema::create('tenant_management', function (Blueprint $t) { $t->id(); $t->integer('complex_id')->nullable(); $t->integer('tenant_profile_id')->nullable(); $t->integer('managed_by'); $t->timestamps(); });
        Schema::create('tenant_invoices', function (Blueprint $t) { $t->id(); $t->integer('tenancy_id')->nullable(); $t->timestamps(); });
        Schema::create('tenant_profiles', function (Blueprint $t) { $t->id(); $t->string('tenant_name'); $t->integer('created_by')->nullable(); $t->timestamps(); });
        Schema::create('complex_expenses', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('description')->nullable(); $t->timestamps(); });
        Schema::create('agents', function (Blueprint $t) { $t->id(); $t->integer('user_id')->nullable(); $t->integer('created_by'); $t->softDeletes(); $t->timestamps(); });
        Schema::create('leads', function (Blueprint $t) { $t->id(); $t->integer('complex_id')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->softDeletes(); $t->timestamps(); });
        Schema::create('lead_tasks', function (Blueprint $t) { $t->id(); $t->integer('lead_id'); $t->integer('assigned_agent_id')->nullable(); $t->string('title')->nullable(); $t->softDeletes(); $t->timestamps(); });

        // Business 10 has three properties; Grace (11) manages property 1.
        DB::table('users')->insert([['id' => 10, 'is_sub_account' => false, 'main_account_user_id' => null], ['id' => 11, 'is_sub_account' => true, 'main_account_user_id' => 10], ['id' => 12, 'is_sub_account' => true, 'main_account_user_id' => 10]]);
        DB::table('complexes')->insert([['id' => 1, 'title' => 'Riverside', 'user_id' => 10], ['id' => 2, 'title' => 'Mombasa Road', 'user_id' => 10], ['id' => 3, 'title' => 'Kilimani', 'user_id' => 10]]);
        DB::table('complex_user')->insert(['complex_id' => 1, 'user_id' => 11]);
        DB::table('tenant_profiles')->insert([['id' => 1, 'tenant_name' => 'At Riverside', 'created_by' => null], ['id' => 2, 'tenant_name' => 'At Kilimani', 'created_by' => null], ['id' => 3, 'tenant_name' => 'Draft by Grace', 'created_by' => 11]]);
        DB::table('tenant_management')->insert([['id' => 1, 'complex_id' => 1, 'tenant_profile_id' => 1, 'managed_by' => 10], ['id' => 2, 'complex_id' => 3, 'tenant_profile_id' => 2, 'managed_by' => 10]]);
        DB::table('tenant_invoices')->insert([['id' => 1, 'tenancy_id' => 1], ['id' => 2, 'tenancy_id' => 2]]);
        DB::table('complex_expenses')->insert([['complex_id' => 1], ['complex_id' => 2], ['complex_id' => 3]]);
        DB::table('agents')->insert([['id' => 1, 'user_id' => null, 'created_by' => 10], ['id' => 5, 'user_id' => 11, 'created_by' => 10]]);
        // Lead 1: Riverside. Lead 2: Kilimani but assigned to Grace's agent profile. Lead 3: Kilimani, someone else.
        DB::table('leads')->insert([['id' => 1, 'complex_id' => 1, 'assigned_agent_id' => 1], ['id' => 2, 'complex_id' => 3, 'assigned_agent_id' => 5], ['id' => 3, 'complex_id' => 3, 'assigned_agent_id' => 1]]);
        DB::table('lead_tasks')->insert([['lead_id' => 1, 'assigned_agent_id' => 1], ['lead_id' => 2, 'assigned_agent_id' => 5], ['lead_id' => 3, 'assigned_agent_id' => 1]]);
        $this->createAssignmentSchema();
        // Individually managed by an external agent, so assigned-only roles see them only when assigned.
        DB::table('complexes')->update(['management_type' => 'individual', 'assigned_agent_id' => 1]);
    }

    private function member(int $id, string $scope): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'is_sub_account' => true, 'main_account_user_id' => 10, 'is_suspended' => false]);
        $user->exists = true;
        $user->setRelation('businessRole', new BusinessRole(['property_scope' => $scope, 'permissions' => []]));
        return $user;
    }

    private function owner(): User
    {
        $user = new User;
        $user->forceFill(['id' => 10, 'is_sub_account' => false]);
        $user->exists = true;
        return $user;
    }

    private function counts(): array
    {
        return [
            'complexes' => Complex::count(), 'tenancies' => TenantManagement::count(), 'invoices' => TenantInvoice::count(),
            'profiles' => TenantProfile::count(), 'expenses' => ComplexExpense::count(), 'leads' => Lead::count(), 'tasks' => LeadTask::count(),
        ];
    }

    private const EVERYTHING = ['complexes' => 3, 'tenancies' => 2, 'invoices' => 2, 'profiles' => 3, 'expenses' => 3, 'leads' => 3, 'tasks' => 3];

    public function test_nothing_is_limited_until_a_business_request_activates_it(): void
    {
        $this->actingAs($this->member(11, 'assigned'));
        $this->assertSame(self::EVERYTHING, $this->counts(), 'console, jobs and public pages are never limited');
    }

    public function test_owner_and_all_property_roles_see_everything(): void
    {
        app(PropertyAccess::class)->activate($this->owner());
        $this->assertSame(self::EVERYTHING, $this->counts());
        app(PropertyAccess::class)->activate($this->member(11, 'all'));
        $this->assertSame(self::EVERYTHING, $this->counts());
        $this->assertNull(app(PropertyAccess::class)->complexIds());
    }

    public function test_assigned_only_roles_see_their_properties_and_what_belongs_to_them(): void
    {
        $access = app(PropertyAccess::class);
        $access->activate($this->member(11, 'assigned'));

        $this->assertSame([1], $access->complexIds());
        $this->assertSame(5, $access->agentId());
        $this->assertSame(['Riverside'], Complex::pluck('title')->all());
        $this->assertSame([1], TenantManagement::pluck('id')->all());
        $this->assertSame([1], TenantInvoice::pluck('id')->all(), 'tenancy records follow the tenancy\'s property');
        $this->assertEqualsCanonicalizing([1, 3], TenantProfile::pluck('id')->all(), 'tenants at their property plus profiles they created');
        $this->assertSame(1, ComplexExpense::count());
        $this->assertEqualsCanonicalizing([1, 2], Lead::pluck('id')->all(), 'leads at their property plus leads assigned to them as agent');
        $this->assertEqualsCanonicalizing([1, 2], LeadTask::pluck('lead_id')->all());
        $this->assertNull(Complex::where('slug', null)->find(3), 'another property resolves to nothing (404)');
    }

    public function test_a_member_with_no_assignments_sees_no_properties(): void
    {
        app(PropertyAccess::class)->activate($this->member(12, 'assigned'));
        $this->assertSame(0, Complex::count());
        $this->assertSame(0, TenantManagement::count());
        $this->assertSame(0, Lead::count());
    }

    public function test_assert_complex_refuses_unassigned_properties(): void
    {
        $access = app(PropertyAccess::class);
        $access->activate($this->member(11, 'assigned'));
        $access->assertComplex(1);
        $access->assertComplex(null);
        try {
            $access->assertComplex(3);
            $this->fail('An unassigned property must be refused.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_internal_lookups_can_run_unrestricted(): void
    {
        $access = app(PropertyAccess::class);
        $access->activate($this->member(11, 'assigned'));
        $this->assertSame(3, $access->unrestricted(fn () => Complex::count()));
        $this->assertSame(1, Complex::count());
        $query = $access->constrain(DB::table('tenant_management'), 'complex_id');
        $this->assertSame(1, $query->count(), 'raw queries can apply the same limit');
    }

    public function test_a_limited_member_manages_the_property_they_create(): void
    {
        $access = app(PropertyAccess::class);
        $access->activate($this->member(11, 'assigned'));
        DB::table('complexes')->insert(['id' => 4, 'title' => 'New build', 'user_id' => 10, 'management_type' => 'individual', 'assigned_agent_id' => 1]);
        $access->claimCreated(4);
        $this->assertEqualsCanonicalizing([1, 4], $access->complexIds());
    }

    public function test_assignments_only_change_properties_the_actor_can_see(): void
    {
        DB::table('complex_user')->insert(['complex_id' => 3, 'user_id' => 12]);
        $actor = $this->member(11, 'assigned');
        $this->actingAs($actor);
        app(PropertyAccess::class)->activate($actor);

        // Grace (sees property 1 only) gives property 1 to member 12: their Kilimani assignment stays.
        app(PropertyAssignmentService::class)->setForMember($this->member(12, 'assigned'), [1]);
        $this->assertEqualsCanonicalizing([1, 3], DB::table('complex_user')->where('user_id', 12)->pluck('complex_id')->all());

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PropertyAssignmentService::class)->setForMember($this->member(12, 'assigned'), [2]);
    }
}
