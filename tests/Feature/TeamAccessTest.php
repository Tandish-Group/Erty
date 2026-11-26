<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthorizeBusinessRoute;
use App\Models\User;
use App\Services\Access\BusinessPermissionService;
use App\Services\Access\StarterRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Route, Schema};
use Tests\TestCase;

/**
 * Multi-user access: owner context, route-level permissions for team members,
 * suspension, landing pages, starter roles and privilege-escalation checks.
 */
class TeamAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'team_test', 'database.connections.team_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('businesses', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('business_name'); $t->timestamp('starter_roles_seeded_at')->nullable(); $t->json('starter_roles_added')->nullable(); $t->timestamp('suspended_at')->nullable(); $t->timestamps(); });
        Schema::create('business_roles', function (Blueprint $t) {
            $t->id(); $t->integer('business_id'); $t->integer('user_id'); $t->string('name'); $t->string('department', 60)->nullable(); $t->text('description')->nullable(); $t->string('color')->nullable();
            $t->boolean('is_active')->default(true); $t->json('permissions')->nullable(); $t->string('property_scope')->default('all'); $t->timestamps();
        });
        DB::table('businesses')->insert([['user_id' => 10, 'business_name' => 'Acme Estates'], ['user_id' => 20, 'business_name' => 'Other Co']]);
    }

    private function staff(array $permissions, bool $suspended = false): User
    {
        $user = new User;
        $user->forceFill(['id' => 11, 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_permissions' => $permissions, 'is_suspended' => $suspended]);
        $user->setRelation('businessRole', null);
        return $user;
    }

    private function owner(): User
    {
        $user = new User;
        $user->forceFill(['id' => 10, 'is_sub_account' => false, 'is_suspended' => false]);
        return $user;
    }

    /** Run the access middleware for a real route. */
    private function hit(User $user, string $method, string $uri, bool $json = true)
    {
        $request = Request::create('/' . ltrim($uri, '/'), $method);
        if ($json) $request->headers->set('Accept', 'application/json');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
        $request->setUserResolver(fn () => $user);
        $this->actingAs($user);
        return app(AuthorizeBusinessRoute::class)->handle($request, fn () => response('passed'));
    }

    public function test_staff_work_in_the_owners_business(): void
    {
        $staff = $this->staff([]);
        $this->assertSame(10, $staff->businessOwnerId());
        $this->assertSame(10, business_owner_id($staff));
        $this->assertSame('Acme Estates', $staff->business->business_name, 'staff resolve to the owner\'s business record');
        $this->assertSame(10, $this->owner()->businessOwnerId());
        $this->assertTrue($this->owner()->isBusinessOwner());
        $this->assertFalse($staff->isBusinessOwner());
    }

    public function test_owner_passes_every_mapped_route(): void
    {
        foreach ([['GET', 'tenant_management'], ['POST', 'storage/recalculate'], ['POST', 'business/modules'], ['DELETE', 'legal/1']] as [$method, $uri]) {
            $this->assertSame('passed', $this->hit($this->owner(), $method, $uri)->getContent(), "$method $uri");
        }
    }

    public function test_staff_need_the_mapped_permission(): void
    {
        $staff = $this->staff(['tenant_management' => ['view'], 'legal_documents' => ['view', 'download']]);
        $this->assertSame('passed', $this->hit($staff, 'GET', 'tenant_management')->getContent());
        $this->assertSame('passed', $this->hit($staff, 'GET', 'legal')->getContent());
        $this->assertSame('passed', $this->hit($staff, 'GET', 'download/legal_document/x.pdf')->getContent());

        $denied = $this->hit($staff, 'POST', 'tenant_management');
        $this->assertSame(403, $denied->getStatusCode());
        $this->assertStringContainsString('Tenants & tenancies', $denied->getData(true)['message']);
        $this->assertSame(403, $this->hit($staff, 'POST', 'upload/legal_document')->getStatusCode(), 'create needs legal_documents.create');
        $this->assertSame(403, $this->hit($staff, 'GET', 'complex')->getStatusCode(), 'no property permission');
    }

    public function test_owner_only_routes_are_refused_to_every_team_member(): void
    {
        $admin = $this->staff(collect(config('business_route_permissions'))->mapWithKeys(fn ($d) => [$d['group'] => $d['permissions'] ?? []])->all());
        foreach ([['POST', 'storage/recalculate'], ['POST', 'business/modules'], ['DELETE', 'business_account/x'], ['GET', 'payment-methods/list']] as [$method, $uri]) {
            $response = $this->hit($admin, $method, $uri);
            $this->assertSame(403, $response->getStatusCode(), "$method $uri");
            $this->assertStringContainsString('account owner', $response->getData(true)['message']);
        }
    }

    public function test_any_staff_routes_need_no_permission(): void
    {
        $staff = $this->staff([]);
        foreach ([['GET', 'my-profile'], ['PUT', 'my-profile'], ['POST', 'pass_reset'], ['POST', 'email_reset'], ['GET', 'notifications/unread']] as [$method, $uri]) {
            $this->assertSame('passed', $this->hit($staff, $method, $uri)->getContent(), "$method $uri");
        }
    }

    public function test_business_settings_need_the_business_settings_permission(): void
    {
        $this->assertSame(403, $this->hit($this->staff(['tenant_management' => ['view']]), 'GET', 'business_account')->getStatusCode());
        $this->assertSame(403, $this->hit($this->staff(['account_settings' => ['view']]), 'PUT', 'business_account/business')->getStatusCode(), 'editing needs account_settings.edit');
        $editor = $this->staff(['account_settings' => ['view', 'edit']]);
        $this->assertSame('passed', $this->hit($editor, 'GET', 'business_account')->getContent());
        $this->assertSame('passed', $this->hit($editor, 'PUT', 'business_account/business')->getContent());
        $this->assertSame('passed', $this->hit($editor, 'GET', 'storage/overview')->getContent());
        foreach ([['POST', 'business/modules'], ['GET', 'payment-methods/list'], ['DELETE', 'business_account/x'], ['POST', 'storage/recalculate']] as [$method, $uri]) {
            $this->assertSame(403, $this->hit($editor, $method, $uri)->getStatusCode(), "$method $uri stays with the owner");
        }
    }

    public function test_agents_section_and_my_agent_dashboard(): void
    {
        $plain = $this->staff(['tenant_management' => ['view']]);
        $this->assertSame('passed', $this->hit($plain, 'GET', 'my-agent')->getContent(), 'every sign-in can open their own agent dashboard');
        foreach ([['GET', 'agents'], ['GET', 'agents/commissions'], ['GET', 'agents/settings'], ['GET', 'agents/data']] as [$method, $uri]) {
            $this->assertSame(403, $this->hit($plain, $method, $uri)->getStatusCode(), "$method $uri needs the Agents permission");
        }
        $viewer = $this->staff(['agent_management' => ['view']]);
        $this->assertSame('passed', $this->hit($viewer, 'GET', 'agents')->getContent());
        $this->assertSame('passed', $this->hit($viewer, 'GET', 'agents/commissions')->getContent());
        $this->assertSame(403, $this->hit($viewer, 'POST', 'agents/profile/x/link')->getStatusCode(), 'linking a sign-in needs agents edit');
        $this->assertSame('passed', $this->hit($this->staff(['agent_management' => ['view', 'edit']]), 'POST', 'agents/profile/x/link')->getContent());
    }

    public function test_agent_tasks_live_in_the_agents_section(): void
    {
        $this->assertSame(403, $this->hit($this->staff(['lead_management' => ['view']]), 'GET', 'agents/tasks')->getStatusCode(), 'CRM lead access alone does not open agent tasks');
        $this->assertSame('passed', $this->hit($this->staff(['task_management' => ['view']]), 'GET', 'agents/tasks')->getContent());
        $this->assertSame('passed', $this->hit($this->staff(['task_management' => ['view']]), 'GET', 'agents/tasks/upcoming')->getContent(), 'upcoming is reachable (was shadowed by {id})');
        $this->assertSame('passed', $this->hit($this->staff([]), 'GET', 'agents/search')->getContent(), 'agent pickers work for every team member');
    }

    public function test_property_search_for_assignments_needs_the_assign_permission(): void
    {
        $this->assertSame(403, $this->hit($this->staff(['subaccount_management' => ['view', 'edit'], 'complex_management' => ['view']]), 'GET', 'business_account/team/properties')->getStatusCode());
        $this->assertSame('passed', $this->hit($this->staff(['complex_management' => ['view', 'assign']]), 'GET', 'business_account/team/properties')->getContent());
    }

    public function test_staff_with_no_section_land_on_their_profile(): void
    {
        $response = $this->hit($this->staff([]), 'GET', 'dashboard', false);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/my-profile', $response->getTargetUrl());
    }

    public function test_staff_without_dashboard_land_on_their_first_section(): void
    {
        $staff = $this->staff(['legal_documents' => ['view']]);
        $response = $this->hit($staff, 'GET', 'dashboard', false);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringEndsWith('/legal', $response->getTargetUrl());
    }

    public function test_suspended_staff_are_signed_out(): void
    {
        $response = $this->hit($this->staff(['tenant_management' => ['view']], true), 'GET', 'tenant_management');
        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse(auth()->check());
    }

    public function test_starter_roles_are_created_once_from_the_catalogue(): void
    {
        $this->assertSame(9, app(StarterRoles::class)->ensureFor(10));
        $this->assertSame(0, app(StarterRoles::class)->ensureFor(10), 'not recreated after the first time');
        $roles = DB::table('business_roles')->where('business_id', 10)->pluck('permissions', 'name')->map(fn ($p) => json_decode($p, true));
        $this->assertEqualsCanonicalizing(['Administrator', 'Property Manager', 'Leasing / CRM Agent', 'Finance Officer', 'Viewer',
            'Maintenance Coordinator', 'Caretaker', 'Accountant', 'Agent (sales only)'], $roles->keys()->all());
        $this->assertSame(['view', 'log', 'manage'], $roles['Maintenance Coordinator']['maintenance_management']);
        $this->assertSame(['view'], $roles['Maintenance Coordinator']['complex_management'], 'repairs without property set-up rights');
        $this->assertSame(['view', 'log'], $roles['Caretaker']['maintenance_management']);
        $this->assertArrayNotHasKey('tenant_management', array_filter($roles['Agent (sales only)']), 'sales agents cannot change tenancies');
        $this->assertSame(['view', 'payments', 'export'], $roles['Accountant']['tenant_management']);
        $this->assertSame(['view', 'financial', 'occupancy', 'agents', 'sales', 'maintenance', 'export'], $roles['Accountant']['reports'], 'reports have their own group');
        $this->assertSame(['view', 'occupancy', 'maintenance'], $roles['Caretaker']['reports'], 'no money reports for on-site staff');
        $this->assertSame(['view', 'edit'], $roles['Administrator']['account_settings'], 'administrators manage business settings');
        $this->assertContains('assign', $roles['Administrator']['complex_management']);
        $this->assertNotContains('assign', $roles['Property Manager']['complex_management']);
        $this->assertArrayNotHasKey('account_settings', $roles['Viewer'], 'viewers do not see business settings');
        $scopes = DB::table('business_roles')->where('business_id', 10)->pluck('property_scope', 'name');
        $this->assertSame('assigned', $scopes['Property Manager']);
        $this->assertSame('assigned', $scopes['Leasing / CRM Agent']);
        $this->assertSame('all', $scopes['Administrator']);
        $this->assertSame(['assigned', 'assigned', 'all', 'assigned'], [$scopes['Maintenance Coordinator'], $scopes['Caretaker'], $scopes['Accountant'], $scopes['Agent (sales only)']]);
        $this->assertContains('pay', $roles['Finance Officer']['expense_management']);
        $this->assertNotContains('delete', $roles['Finance Officer']['expense_management']);
        // Viewers only read: "view" everywhere, plus every report (reading and downloading reports changes nothing).
        foreach (array_diff_key($roles['Viewer'], ['reports' => true]) as $actions) $this->assertSame(['view'], $actions);
        $this->assertSame(config('business_route_permissions.reports.permissions'), $roles['Viewer']['reports']);
        $catalogue = collect(config('business_route_permissions'))->keyBy('group');
        foreach ($roles as $permissions) {
            foreach ($permissions as $group => $actions) {
                $this->assertEmpty(array_diff($actions, $catalogue[$group]['permissions']), "$group uses defined actions only");
            }
        }
    }

    public function test_new_standard_roles_reach_older_businesses_but_deleted_ones_stay_deleted(): void
    {
        // A business seeded with the original five, which later deleted its Viewer role.
        DB::table('businesses')->where('user_id', 10)->update(['starter_roles_seeded_at' => now(),
            'starter_roles_added' => json_encode(['Administrator', 'Property Manager', 'Leasing / CRM Agent', 'Finance Officer', 'Viewer'])]);

        $this->assertSame(4, app(StarterRoles::class)->ensureFor(10), 'only the four newer roles');
        $names = DB::table('business_roles')->where('business_id', 10)->pluck('name')->all();
        $this->assertNotContains('Viewer', $names, 'a deleted starter role is not brought back');
        $this->assertContains('Caretaker', $names);
        $this->assertSame(0, app(StarterRoles::class)->ensureFor(10));
    }

    public function test_team_members_cannot_grant_more_than_they_hold(): void
    {
        $service = app(BusinessPermissionService::class);
        $mine = ['tenant_management' => ['view', 'create'], 'legal_documents' => ['view']];
        $this->assertTrue($service->isSubset(['tenant_management' => ['view']], $mine));
        $this->assertFalse($service->isSubset(['tenant_management' => ['delete']], $mine));
        $this->assertFalse($service->isSubset(['expense_management' => ['view']], $mine));
        $this->assertTrue($service->isSubset(['expense_management' => ['pay']], $service->permissionsFor($this->owner())), 'owners may grant anything');
    }
}
