<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthorizeBusinessRoute;
use App\Models\{BusinessRole, User};
use App\Services\Access\StarterRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Route, Schema};
use Tests\TestCase;

/**
 * Every starter role against the key routes of each section, through the real access middleware:
 * a role opens a section only with the permission it needs. Also departments and the agent-role scope rule.
 */
class RoleAccessMatrixTest extends TestCase
{
    /** [method, uri, permission it needs] — the important routes of each section. */
    private const ROUTES = [
        ['GET', '/dashboard', 'dashboard_access.view'],
        ['GET', '/complex', 'complex_management.view'],
        ['PUT', '/complex/x/management', 'complex_management.assign'],
        ['GET', '/tenant_management', 'tenant_management.view'],
        ['GET', '/tenant_management/payment-reconciliation', 'tenant_management.payments'],
        ['GET', '/tenant_management/bulk-upload/mapping', 'tenant_management.import'],
        ['GET', '/customer_relationship/lead_management', 'lead_management.view'],
        ['GET', '/agents', 'agent_management.view'],
        ['GET', '/agents/commissions', 'agent_management.view'],
        ['GET', '/agents/commissions/1/statement', 'agent_management.view'],
        ['POST', '/agents/commissions/agreements', 'agent_management.edit'],
        ['GET', '/agents/tasks', 'task_management.view'],
        ['GET', '/complex-expenses', 'expense_management.view'],
        ['GET', '/legal', 'legal_documents.view'],
        ['GET', '/sales', 'sales_management.view'],
        ['POST', '/sales', 'sales_management.create'],
        ['POST', '/sales/SAL-261118-ABCD/payments', 'sales_management.payments'],
        ['POST', '/sales/SAL-261118-ABCD/complete', 'sales_management.complete'],
        ['POST', '/sales/SAL-261118-ABCD/cancel', 'sales_management.cancel'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'matrix_test', 'database.connections.matrix_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Schema::create('businesses', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('business_name'); $t->timestamp('starter_roles_seeded_at')->nullable(); $t->json('starter_roles_added')->nullable(); $t->timestamps(); });
        Schema::create('business_roles', function (Blueprint $t) {
            $t->id(); $t->integer('business_id'); $t->integer('user_id'); $t->string('name'); $t->string('department', 60)->nullable(); $t->text('description')->nullable(); $t->string('color')->nullable();
            $t->boolean('is_active')->default(true); $t->json('permissions')->nullable(); $t->string('property_scope')->default('all'); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->boolean('is_sub_account')->default(false); $t->integer('main_account_user_id')->nullable(); $t->integer('business_role_id')->nullable(); $t->json('business_permissions')->nullable(); $t->boolean('is_suspended')->default(false); $t->timestamps(); });
        Schema::create('agents', function (Blueprint $t) { $t->id(); $t->integer('user_id')->nullable(); $t->integer('created_by'); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->softDeletes(); $t->timestamps(); });
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Acme Estates']);
        DB::table('users')->insert(['id' => 10, 'first_name' => 'Owner', 'is_sub_account' => false]);
    }

    private function member(string $roleName): User
    {
        [, , $spec, $scope] = StarterRoles::definitions()[$roleName];
        $role = new BusinessRole(['name' => $roleName, 'permissions' => StarterRoles::permissions($spec), 'property_scope' => $scope, 'is_active' => true]);
        $user = new User;
        $user->forceFill(['id' => 11, 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_permissions' => null, 'is_suspended' => false]);
        $user->setRelation('businessRole', $role);
        return $user;
    }

    private function hit(User $user, string $method, string $uri): int
    {
        $request = Request::create($uri, $method);
        $request->headers->set('Accept', 'application/json');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
        $request->setUserResolver(fn () => $user);
        $this->actingAs($user);
        $response = app(AuthorizeBusinessRoute::class)->handle($request, fn () => response('passed'));
        return $response->getStatusCode();
    }

    public function test_every_starter_role_opens_exactly_the_sections_its_permissions_allow(): void
    {
        $checked = 0;
        foreach (array_keys(StarterRoles::definitions()) as $roleName) {
            $member = $this->member($roleName);
            $granted = $member->businessRole->permissions;
            foreach (self::ROUTES as [$method, $uri, $needs]) {
                [$group, $action] = explode('.', $needs);
                $should = in_array($action, $granted[$group] ?? [], true);
                $status = $this->hit($member, $method, $uri);
                $this->assertSame($should ? 200 : 403, $status, "{$roleName} → {$method} {$uri} (needs {$needs})");
                $checked++;
            }
        }
        $this->assertSame(count(StarterRoles::definitions()) * count(self::ROUTES), $checked);
    }

    public function test_what_each_kind_of_role_must_never_reach(): void
    {
        $refused = [
            'Caretaker' => ['/tenant_management/payment-reconciliation', '/agents/commissions', '/complex-expenses', '/customer_relationship/lead_management', '/sales'],
            'Agent (sales only)' => ['/tenant_management', '/agents/commissions', '/complex-expenses', '/tenant_management/payment-reconciliation'],
            'Leasing / CRM Agent' => ['/tenant_management/payment-reconciliation', '/complex-expenses', '/tenant_management/bulk-upload/mapping'],
            'Maintenance Coordinator' => ['/tenant_management/payment-reconciliation', '/agents/commissions', '/customer_relationship/lead_management', '/sales'],
            'Property Manager' => ['/tenant_management/payment-reconciliation', '/agents/commissions', '/tenant_management/bulk-upload/mapping'],
            'Finance Officer' => ['/customer_relationship/lead_management', '/agents/tasks'],
        ];
        foreach ($refused as $roleName => $uris) {
            foreach ($uris as $uri) {
                $this->assertSame(403, $this->hit($this->member($roleName), 'GET', $uri), "{$roleName} must not open {$uri}");
            }
        }
    }

    public function test_agents_and_on_site_roles_are_limited_to_assigned_properties(): void
    {
        foreach (['Property Manager', 'Leasing / CRM Agent', 'Maintenance Coordinator', 'Caretaker', 'Agent (sales only)'] as $roleName) {
            $this->assertSame('assigned', StarterRoles::definitions()[$roleName][3], $roleName);
        }
    }

    public function test_roles_are_grouped_into_departments(): void
    {
        $this->assertSame([
            'Administrator' => 'Administration', 'Property Manager' => 'Property operations', 'Leasing / CRM Agent' => 'Sales & leasing',
            'Finance Officer' => 'Finance', 'Viewer' => 'Oversight', 'Maintenance Coordinator' => 'Property operations',
            'Caretaker' => 'Property operations', 'Accountant' => 'Finance', 'Agent (sales only)' => 'Sales & leasing',
        ], collect(StarterRoles::definitions())->keys()->mapWithKeys(fn ($n) => [$n => StarterRoles::departmentFor($n)])->all());
        $this->assertSame('Sales & leasing', StarterRoles::departmentFor('Leasing Agent (assigned properties)'));
        $this->assertNull(StarterRoles::departmentFor('Security guard'));

        app(StarterRoles::class)->ensureFor(10);
        $this->assertSame('Finance', DB::table('business_roles')->where('name', 'Accountant')->value('department'));
    }

    public function test_only_the_owner_can_give_an_agent_role_every_property(): void
    {
        app(StarterRoles::class)->ensureFor(10);
        $agentRole = BusinessRole::where('name', 'Leasing / CRM Agent')->first();
        $admin = BusinessRole::where('name', 'Administrator')->first();
        DB::table('users')->insert(['id' => 12, 'first_name' => 'Ada', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => $admin->id]);
        $form = fn () => ['name' => $agentRole->name, 'description' => 'Agents', 'color' => 'info', 'property_scope' => 'all', 'department' => 'Sales & leasing',
            'permissions' => collect($agentRole->permissions)->map(fn ($a) => array_fill_keys($a, 'on'))->all()];
        $controller = app(\App\Http\Controllers\Business\Team\BusinessRoleController::class);

        $this->actingAs(User::find(12));
        $response = $controller->update(Request::create('/business-roles/' . $agentRole->id, 'PUT', $form()), $agentRole->id);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('assigned', $agentRole->fresh()->property_scope, 'an administrator cannot widen an agent role');

        $this->actingAs(User::find(10));
        $this->assertSame(200, $controller->update(Request::create('/business-roles/' . $agentRole->id, 'PUT', $form()), $agentRole->id)->getStatusCode());
        $this->assertSame('all', $agentRole->fresh()->property_scope, 'the owner can, deliberately');
    }
}
