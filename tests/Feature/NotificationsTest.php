<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Account\NotificationController;
use App\Models\{Complex, MaintenanceRequest, User};
use App\Services\Agents\AgentAssignmentService;
use App\Services\Properties\MaintenanceService;
use App\Services\NotificationCenter;
use App\Services\Rent\TenantAccountService;
use App\Services\Rent\TenantChargesService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Tests\TestCase;

/**
 * Who gets which notification. Business owner 10; Grace (11, Property Manager, assigned properties,
 * primary of Mlimani as agent 1); Kevin (12, agent 2, assigned properties, holds unit B-1 only);
 * Fiona (13, Finance, all properties); Carl (14, Caretaker, assigned properties, nothing assigned).
 */
class NotificationsTest extends TestCase
{
    use \Tests\Concerns\TenantAccountsSchema;
    use \Tests\Concerns\CreatesAssignmentSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'notify_test', 'database.connections.notify_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Mail::fake();
        Carbon::setTestNow('2026-11-15 09:00:00');
        $this->schema();
        $this->createCommissionSchema();
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_suspended')->default(false));
        Schema::create('business_roles', function (Blueprint $t) {
            $t->id(); $t->integer('business_id'); $t->string('name'); $t->string('department', 60)->nullable(); $t->json('permissions')->nullable(); $t->string('property_scope')->default('all'); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('notifications', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('type'); $t->morphs('notifiable'); $t->text('data'); $t->timestamp('read_at')->nullable(); $t->timestamps(); });
        Schema::create('complex_changes', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('field_name'); $t->text('old_value')->nullable(); $t->text('new_value'); $t->integer('user_id'); $t->string('user_ip')->nullable(); $t->timestamps(); });

        DB::table('business_roles')->insert([
            ['id' => 1, 'business_id' => 10, 'name' => 'Property Manager', 'property_scope' => 'assigned', 'permissions' => json_encode(['complex_management' => ['view', 'edit'], 'maintenance_management' => ['view', 'log', 'manage'], 'tenant_management' => ['view', 'notices']])],
            ['id' => 2, 'business_id' => 10, 'name' => 'Leasing Agent', 'property_scope' => 'assigned', 'permissions' => json_encode(['complex_management' => ['view'], 'tenant_management' => ['view', 'create'], 'agent_management' => ['view']])],
            ['id' => 3, 'business_id' => 10, 'name' => 'Finance', 'property_scope' => 'all', 'permissions' => json_encode(['tenant_management' => ['view', 'payments'], 'agent_management' => ['view', 'edit']])],
            ['id' => 4, 'business_id' => 10, 'name' => 'Caretaker', 'property_scope' => 'assigned', 'permissions' => json_encode(['complex_management' => ['view'], 'maintenance_management' => ['view', 'log']])],
        ]);
        DB::table('users')->insert([
            ['id' => 10, 'first_name' => 'Owner', 'last_name' => 'One', 'email' => 'o@x.test', 'is_sub_account' => false, 'main_account_user_id' => null, 'business_role_id' => null],
            ['id' => 11, 'first_name' => 'Grace', 'last_name' => 'PM', 'email' => 'g@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 1],
            ['id' => 12, 'first_name' => 'Kevin', 'last_name' => 'Agent', 'email' => 'k@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 2],
            ['id' => 13, 'first_name' => 'Fiona', 'last_name' => 'Finance', 'email' => 'f@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 3],
            ['id' => 14, 'first_name' => 'Carl', 'last_name' => 'Caretaker', 'email' => 'c@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10, 'business_role_id' => 4],
        ]);
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Tandish']);
        DB::table('complexes')->insert(['id' => 1, 'user_id' => 10, 'title' => 'Mlimani', 'slug' => 'mlimani', 'assigned_agent_id' => 1, 'management_type' => 'individual']);
        DB::table('agents')->insert([
            ['id' => 1, 'created_by' => 10, 'user_id' => 11, 'first_name' => 'Grace', 'last_name' => 'PM', 'email' => 'g@x.test', 'is_active' => true],
            ['id' => 2, 'created_by' => 10, 'user_id' => 12, 'first_name' => 'Kevin', 'last_name' => 'Agent', 'email' => 'k@x.test', 'is_active' => true],
        ]);
        DB::table('complex_residential_units')->insert([
            ['id' => 1, 'complex_id' => 1, 'unit_number' => 'A-1', 'status' => 'available', 'assigned_agent_id' => null],
            ['id' => 2, 'complex_id' => 1, 'unit_number' => 'B-1', 'status' => 'available', 'assigned_agent_id' => 2],
        ]);
        DB::table('tenant_profiles')->insert([['id' => 1, 'tenant_type' => 'Individual', 'tenant_name' => 'Amani', 'managed_by' => 10], ['id' => 2, 'tenant_type' => 'Individual', 'tenant_name' => 'Baraka', 'managed_by' => 10]]);
        $this->actingAs(User::find(10));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Titles each person received, by user id. */
    private function inbox(): array
    {
        return DB::table('notifications')->orderBy('created_at')->get()->groupBy('notifiable_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => json_decode($r->data, true)['kind'])->values()->all())->sortKeys()->all();
    }

    private function lease(int $unitId, int $profile = 1)
    {
        $t = $this->tenancy(['residential_unit_id' => $unitId, 'complex_name' => 'Mlimani', 'unit_number' => $unitId === 2 ? 'B-1' : 'A-1', 'tenant_profile_id' => $profile, 'security_deposit' => 0, 'assigned_agent_id' => null]);
        app(TenantChargesService::class)->startBilling($t);
        return $t;
    }

    public function test_an_agent_hears_about_units_given_to_and_taken_from_them_but_not_the_owner_who_did_it(): void
    {
        $service = app(AgentAssignmentService::class);
        $service->assignUnits(User::find(10), Complex::find(1), 'residential', [1], 2);   // A-1 to Kevin
        $service->assignUnits(User::find(10), Complex::find(1), 'residential', [2], 1);   // B-1 from Kevin to Grace

        $this->assertSame([11 => ['units.assigned'], 12 => ['units.assigned', 'units.removed']], $this->inbox());
        $data = json_decode(DB::table('notifications')->where('notifiable_id', 12)->orderBy('created_at')->value('data'), true);
        $this->assertStringContainsString('A-1', $data['body']);
        $this->assertStringContainsString('/complex/mlimani', $data['url']);
    }

    public function test_property_management_changes_reach_the_old_and_new_primary(): void
    {
        app(AgentAssignmentService::class)->setManagement(User::find(10), Complex::find(1), 'individual', 2, 'Kevin takes over');
        $this->assertSame([11 => ['property.primary_removed'], 12 => ['property.primary']], $this->inbox());
    }

    public function test_payments_reach_the_agent_for_the_unit_and_people_who_handle_payments_who_can_see_it(): void
    {
        $tenancy = $this->lease(2);
        app(TenantAccountService::class)->record($tenancy, $this->pay(35000));

        // Kevin (B-1's agent) and Fiona (payments, all properties). Not the owner (recorded it), not Grace or Carl (no payments permission).
        $this->assertSame([12 => ['payment.recorded'], 13 => ['payment.recorded']], $this->inbox());
        $this->assertSame('Payment received: KES 35,000', json_decode(DB::table('notifications')->where('notifiable_id', 12)->value('data'), true)['title']);
    }

    public function test_a_payment_on_someone_elses_unit_does_not_reach_a_unit_only_agent(): void
    {
        $this->actingAs(User::find(13)); // Fiona records it
        app(TenantAccountService::class)->record($this->lease(1), $this->pay(10000));
        // A-1 has no unit agent: Grace answers for it as primary manager; Kevin hears nothing; Fiona did it; the owner sees all.
        $this->assertSame([10 => ['payment.recorded'], 11 => ['payment.recorded']], $this->inbox());
    }

    public function test_repairs_reach_maintenance_managers_who_can_see_the_property_and_the_assignee(): void
    {
        DB::table('complex_user')->insert(['complex_id' => 1, 'user_id' => 11]);
        $this->actingAs(User::find(14)); // the caretaker logs it
        $request = app(MaintenanceService::class)->log(['complex_id' => 1, 'residential_unit_id' => 2, 'title' => 'Leaking tap', 'priority' => 'urgent', 'assigned_user_id' => 12], 10);

        // Owner and Grace (manages repairs), Kevin (B-1's agent, and the assignee). Never Carl (did it) or Fiona (no maintenance).
        $this->assertSame([10 => ['maintenance.logged'], 11 => ['maintenance.logged'], 12 => ['maintenance.logged', 'maintenance.assigned']], $this->inbox());
        $this->assertStringStartsWith('Urgent repair', json_decode(DB::table('notifications')->where('notifiable_id', 10)->value('data'), true)['title']);

        DB::table('notifications')->delete();
        $this->actingAs(User::find(12));
        app(MaintenanceService::class)->complete(MaintenanceRequest::find($request->id), 'Washer replaced', null, null);
        $this->assertSame([14 => ['maintenance.completed']], $this->inbox(), 'the person who logged it hears it is done');
    }

    public function test_suspended_people_and_agents_who_do_not_sign_in_get_nothing(): void
    {
        DB::table('users')->where('id', 13)->update(['is_suspended' => true]);
        DB::table('agents')->insert(['id' => 3, 'created_by' => 10, 'user_id' => null, 'first_name' => 'External', 'last_name' => 'Agency', 'is_active' => true]);
        app(AgentAssignmentService::class)->assignUnits(User::find(10), Complex::find(1), 'residential', [1], 2);
        app(TenantAccountService::class)->record($this->lease(1), $this->pay(10000));
        $this->assertArrayNotHasKey(13, $this->inbox());
        $this->assertNull(app(NotificationCenter::class)->agentUser(3));
    }

    public function test_reminders_with_a_key_are_sent_once(): void
    {
        $center = app(NotificationCenter::class);
        $payload = ['kind' => 'task.overdue', 'title' => 'Overdue: Call back', 'key' => 'task-overdue:7'];
        $this->assertSame(1, $center->deliver(collect([User::find(12)]), $payload, false));
        $this->assertSame(0, $center->deliver(collect([User::find(12)]), $payload, false));
        $this->assertSame(1, DB::table('notifications')->count());
    }

    public function test_people_see_and_open_only_their_own_notifications(): void
    {
        app(AgentAssignmentService::class)->assignUnits(User::find(10), Complex::find(1), 'residential', [1], 2);
        $this->actingAs(User::find(12));
        $controller = app(NotificationController::class);

        $list = $controller->index()->getData(true);
        $this->assertSame(1, $list['unread']);
        $this->assertSame('units.assigned', $list['items'][0]['kind']);
        $this->assertSame('door-open', $list['items'][0]['icon']);

        $open = $controller->open($list['items'][0]['id']);
        $this->assertStringContainsString('/complex/mlimani', $open->getTargetUrl());
        $this->assertSame(0, $controller->index()->getData(true)['unread']);

        $this->actingAs(User::find(11));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $controller->open($list['items'][0]['id']);
    }

    public function test_mark_all_as_read(): void
    {
        $service = app(AgentAssignmentService::class);
        $service->assignUnits(User::find(10), Complex::find(1), 'residential', [1], 2);
        $service->setManagement(User::find(10), Complex::find(1), 'individual', 2, null);
        $this->actingAs(User::find(12));
        $this->assertSame(2, app(NotificationController::class)->index()->getData(true)['unread']);
        app(NotificationController::class)->markAllAsRead();
        $this->assertSame(0, User::find(12)->unreadNotifications()->count());
    }
}
