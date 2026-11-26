<?php

namespace Tests\Feature;

use App\Mail\TenancyAssignedMail;
use App\Models\{Agent, BusinessRole, CommercialUnit, Complex, ComplexResidentialUnit, Lead, LeadTask, TenantManagement, User};
use App\Services\Agents\AgentAssignmentService;
use App\Services\Properties\ComplexInventoryService;
use App\Services\Crm\LeadConversionService;
use App\Services\Access\PropertyAccess;
use App\Services\Tenancy\TenancyNotificationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Unit-level agents with a lead-agent hierarchy, occupancy kept in step with tenancies,
 * lead conversion and the agent "tenancy assigned" email.
 */
class TenancyAgentsAndOccupancyTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'tenancy_test', 'database.connections.tenancy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('email')->nullable(); $t->boolean('is_sub_account')->default(false); $t->integer('main_account_user_id')->nullable(); $t->integer('business_role_id')->nullable(); $t->json('business_permissions')->nullable(); $t->timestamps(); });
        Schema::create('businesses', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('business_name'); $t->timestamps(); });
        Schema::create('agents', function (Blueprint $t) { $t->id(); $t->integer('user_id')->nullable(); $t->integer('created_by'); $t->string('first_name'); $t->string('last_name'); $t->string('email')->nullable(); $t->boolean('is_active')->default(true); $t->integer('reports_to_agent_id')->nullable(); $t->decimal('override_rate')->default(0); $t->softDeletes(); $t->timestamps(); });
        Schema::create('complexes', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('title'); $t->string('slug')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('complex_user', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->integer('user_id'); $t->timestamps(); });
        Schema::create('complex_changes', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('field_name'); $t->text('old_value')->nullable(); $t->text('new_value')->nullable(); $t->integer('user_id')->nullable(); $t->string('user_ip')->nullable(); $t->timestamps(); });
        Schema::create('complex_residential_units', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('unit_number'); $t->string('status')->default('available'); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('commercial_units', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->string('unit_number'); $t->double('unit_sq_ft'); $t->double('min_sq_ft')->default(0); $t->integer('max_subdivisions')->default(1); $t->string('status')->default('Available'); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('tenant_profiles', function (Blueprint $t) { $t->id(); $t->string('tenant_type')->default('Individual'); $t->string('tenant_name')->nullable(); $t->string('business_name')->nullable(); $t->integer('managed_by')->nullable(); $t->integer('created_by')->nullable(); $t->timestamps(); });
        Schema::create('tenant_management', function (Blueprint $t) { $t->id(); $t->string('tenancy_ref')->nullable(); $t->integer('tenant_profile_id')->nullable(); $t->integer('complex_id')->nullable(); $t->string('complex_name')->nullable(); $t->integer('residential_unit_id')->nullable(); $t->integer('commercial_unit_id')->nullable(); $t->string('unit_number')->nullable(); $t->double('occupied_sq_area')->nullable(); $t->double('sq_area')->nullable(); $t->string('status')->default('Active'); $t->integer('assigned_agent_id')->nullable(); $t->integer('managed_by'); $t->decimal('monthly_payment')->default(0); $t->string('currency')->default('KES'); $t->date('move_in_date')->nullable(); $t->timestamps(); });
        Schema::create('leads', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->integer('complex_id')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->string('status')->default('new'); $t->string('workflow_phase')->nullable(); $t->timestamp('phase_started_at')->nullable(); $t->integer('converted_tenant_id')->nullable(); $t->timestamp('converted_at')->nullable(); $t->integer('converted_by')->nullable(); $t->json('interactions')->nullable(); $t->timestamp('last_contacted_at')->nullable(); $t->integer('contact_attempts')->default(0); $t->integer('created_by')->nullable(); $t->timestamp('details_confirmed_at')->nullable(); $t->integer('details_confirmed_by')->nullable(); $t->timestamp('qualification_confirmed_at')->nullable(); $t->integer('qualification_confirmed_by')->nullable(); $t->string('source')->nullable(); $t->integer('lead_profile_id')->nullable(); $t->softDeletes(); $t->timestamps(); });
        Schema::create('lead_tasks', function (Blueprint $t) { $t->id(); $t->integer('lead_id'); $t->integer('assigned_agent_id')->nullable(); $t->integer('template_id')->nullable(); $t->string('task_type')->default('system_assigned'); $t->string('phase'); $t->string('title'); $t->text('description')->nullable(); $t->string('status')->default('pending'); $t->integer('sequence_order')->default(1); $t->boolean('is_required')->default(true); $t->boolean('blocks_next_phase')->default(false); $t->integer('sla_hours')->nullable(); $t->timestamp('sla_breach_at')->nullable(); $t->boolean('is_overdue')->default(false); $t->timestamp('started_at')->nullable(); $t->timestamp('completed_at')->nullable(); $t->text('completion_notes')->nullable(); $t->json('completion_data')->nullable(); $t->integer('blocked_by_task_id')->nullable(); $t->string('block_reason')->nullable(); $t->integer('created_by')->nullable(); $t->integer('completed_by')->nullable(); $t->softDeletes(); $t->timestamps(); });
        Schema::create('lead_task_templates', function (Blueprint $t) { $t->id(); $t->string('task_type'); $t->string('phase'); $t->string('title'); $t->text('description')->nullable(); $t->text('instructions')->nullable(); $t->integer('sequence_order'); $t->integer('sla_hours')->nullable(); $t->boolean('is_required')->default(true); $t->boolean('blocks_next_phase')->default(false); $t->boolean('is_active')->default(true); $t->json('trigger_conditions')->nullable(); $t->json('completion_requirements')->nullable(); $t->timestamps(); });

        DB::table('users')->insert([['id' => 10, 'first_name' => 'Owner', 'email' => 'owner@x.test', 'is_sub_account' => false, 'main_account_user_id' => null], ['id' => 31, 'first_name' => 'Jane', 'email' => 'jane@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10], ['id' => 32, 'first_name' => 'Kevin', 'email' => 'kevin@x.test', 'is_sub_account' => true, 'main_account_user_id' => 10]]);
        DB::table('businesses')->insert(['user_id' => 10, 'business_name' => 'Acme']);
        // Jane (lead agent of Kilimani) and Kevin (reports to Jane) sign in; Tom does not; Lucy signs in but reports to no one.
        DB::table('agents')->insert([
            ['id' => 1, 'user_id' => 31, 'created_by' => 10, 'first_name' => 'Jane', 'last_name' => 'Field', 'email' => 'jane@x.test', 'reports_to_agent_id' => null],
            ['id' => 2, 'user_id' => 32, 'created_by' => 10, 'first_name' => 'Kevin', 'last_name' => 'M', 'email' => 'kevin@x.test', 'reports_to_agent_id' => 1],
            ['id' => 3, 'user_id' => null, 'created_by' => 10, 'first_name' => 'Tom', 'last_name' => 'External', 'email' => 'tom@x.test', 'reports_to_agent_id' => 1],
            ['id' => 4, 'user_id' => 33, 'created_by' => 10, 'first_name' => 'Lucy', 'last_name' => 'Other', 'email' => 'lucy@x.test', 'reports_to_agent_id' => null],
        ]);
        DB::table('complexes')->insert([['id' => 1, 'user_id' => 10, 'title' => 'Kilimani', 'slug' => 'kilimani', 'assigned_agent_id' => 1]]);
        $this->createAssignmentSchema();
        DB::table('complexes')->update(['management_type' => 'individual']);
        DB::table('complex_residential_units')->insert([['id' => 1, 'complex_id' => 1, 'unit_number' => 'A1'], ['id' => 2, 'complex_id' => 1, 'unit_number' => 'A2']]);
        DB::table('commercial_units')->insert([['id' => 1, 'complex_id' => 1, 'unit_number' => 'S1', 'unit_sq_ft' => 1000, 'min_sq_ft' => 200, 'max_subdivisions' => 2]]);
        $this->createAssignmentSchema();
    }

    private function signIn(int $id, bool $owner = false): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'is_sub_account' => !$owner, 'main_account_user_id' => $owner ? null : 10, 'business_permissions' => ['complex_management' => ['view']]]);
        $user->exists = true;
        $user->setRelation('businessRole', new BusinessRole(['property_scope' => 'all', 'permissions' => []]));
        $this->actingAs($user);
        return $user;
    }

    public function test_only_sign_in_agents_can_be_assigned_and_lead_agents_assign_their_team(): void
    {
        $service = app(AgentAssignmentService::class);
        $this->signIn(10, true);
        $this->assertTrue($service->isAssignable(1));
        $this->assertFalse($service->isAssignable(3), 'agents without a sign-in cannot be assigned');
        $complex = Complex::find(1);
        $this->assertEqualsCanonicalizing([1, 2, 4], $service->assignableForUnits(auth()->user(), $complex)->pluck('id')->all(), 'owners assign any sign-in agent');

        $jane = $this->signIn(31);
        $this->assertTrue($service->canAssignUnits($jane, $complex), 'the lead agent can assign units of their property');
        $this->assertEqualsCanonicalizing([1, 2], $service->assignableForUnits($jane, $complex)->pluck('id')->all(), 'themselves and agents who report to them');
        $this->assertSame(1, $service->assignUnits($jane, $complex, 'residential', [1], 2));
        $this->assertSame(2, (int) ComplexResidentialUnit::find(1)->assigned_agent_id);
        try {
            $service->assignUnits($jane, $complex, 'residential', [2], 4);
            $this->fail('A lead agent cannot assign agents outside their team.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('agent_id', $e->errors());
        }

        $kevin = $this->signIn(32);
        $this->assertFalse($service->canAssignUnits($kevin, $complex), 'unit agents cannot reassign units');
    }

    public function test_the_unit_agent_comes_first_then_the_property_agent(): void
    {
        DB::table('complex_residential_units')->where('id', 1)->update(['assigned_agent_id' => 2]);
        $service = app(AgentAssignmentService::class);
        $complex = Complex::find(1);
        $this->assertSame(2, $service->defaultAgentFor($complex, ComplexResidentialUnit::find(1))->id);
        $this->assertSame(1, $service->defaultAgentFor($complex, ComplexResidentialUnit::find(2))->id);
        $this->assertSame(1, $service->defaultAgentFor($complex)->id);
    }

    public function test_limited_agents_see_the_properties_they_are_assigned(): void
    {
        $user = $this->signIn(32);
        $user->setRelation('businessRole', new BusinessRole(['property_scope' => 'assigned', 'permissions' => []]));
        $access = app(PropertyAccess::class);
        $access->activate($user);
        $this->assertSame([], $access->complexIds(), 'nothing assigned yet');
        DB::table('commercial_units')->where('id', 1)->update(['assigned_agent_id' => 2]);
        $access->forget();
        $this->assertSame([1], $access->complexIds(), 'a unit assignment makes its property visible');
    }

    public function test_commercial_occupancy_counts_every_tenancy_type_and_enforces_capacity(): void
    {
        $inventory = app(ComplexInventoryService::class);
        $unit = CommercialUnit::find(1);
        // An individual tenant with only occupied_sq_area set (previously counted as 0 by the onboarding list).
        DB::table('tenant_management')->insert(['id' => 1, 'commercial_unit_id' => 1, 'complex_id' => 1, 'occupied_sq_area' => 600, 'managed_by' => 10]);
        $o = $inventory->commercialOccupancy($unit);
        $this->assertSame([600.0, 400.0, 1, 1], [$o['occupied_sq_ft'], $o['remaining_sq_ft'], $o['occupied_count'], $o['remaining_subdivisions']]);

        foreach ([[150, 'at least'], [450, 'still free']] as [$area, $message]) {
            try { $inventory->assertCommercialCapacity($unit, $area); $this->fail("$area sq ft accepted"); }
            catch (ValidationException $e) { $this->assertStringContainsString($message, collect($e->errors())->flatten()->implode(' ')); }
        }
        $inventory->assertCommercialCapacity($unit, 400);
        $inventory->assertCommercialCapacity($unit, 900, 1, 'occupied_sq_area');   // editing tenancy 1 itself
        DB::table('tenant_management')->insert(['id' => 2, 'commercial_unit_id' => 1, 'complex_id' => 1, 'occupied_sq_area' => 300, 'managed_by' => 10]);
        try { $inventory->assertCommercialCapacity($unit, 100); $this->fail('third subdivision accepted'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
    }

    public function test_unit_status_follows_active_tenancies(): void
    {
        $inventory = app(ComplexInventoryService::class);
        DB::table('tenant_management')->insert(['id' => 1, 'residential_unit_id' => 1, 'complex_id' => 1, 'managed_by' => 10, 'status' => 'Active']);
        $inventory->syncOccupancy(1, null);
        $this->assertSame('occupied', ComplexResidentialUnit::find(1)->status);
        DB::table('tenant_management')->where('id', 1)->update(['status' => 'Terminated']);
        $inventory->syncOccupancy(1, null);
        $this->assertSame('available', ComplexResidentialUnit::find(1)->status, 'ending the tenancy frees the unit');

        DB::table('complex_residential_units')->where('id', 2)->update(['status' => 'maintenance']);
        $inventory->syncOccupancy(2, null);
        $this->assertSame('maintenance', ComplexResidentialUnit::find(2)->status, 'manual statuses are kept');

        DB::table('tenant_management')->insert(['id' => 2, 'commercial_unit_id' => 1, 'complex_id' => 1, 'occupied_sq_area' => 300, 'managed_by' => 10]);
        $inventory->syncOccupancy(null, 1);
        $this->assertSame('Occupied', CommercialUnit::find(1)->status);
    }

    public function test_converting_a_lead_links_the_tenancy_and_moves_its_tasks_on(): void
    {
        $this->signIn(10, true);
        DB::table('lead_task_templates')->insert(['id' => 14, 'task_type' => 'system_assigned', 'phase' => 'finalization', 'title' => 'Activate Tenant Record', 'sequence_order' => 1, 'sla_hours' => 2]);
        DB::table('leads')->insert(['id' => 5, 'user_id' => 10, 'complex_id' => 1, 'assigned_agent_id' => 2, 'workflow_phase' => 'engagement']);
        DB::table('lead_tasks')->insert([['lead_id' => 5, 'assigned_agent_id' => 2, 'phase' => 'engagement', 'title' => 'Negotiate Terms', 'status' => 'pending'], ['lead_id' => 5, 'assigned_agent_id' => 2, 'phase' => 'qualification', 'title' => 'Old', 'status' => 'completed']]);
        DB::table('tenant_management')->insert(['id' => 9, 'tenancy_ref' => 'TN-TEST', 'residential_unit_id' => 2, 'complex_id' => 1, 'managed_by' => 10, 'assigned_agent_id' => 2]);

        app(LeadConversionService::class)->markConverted(Lead::find(5), TenantManagement::find(9));
        $lead = Lead::find(5);
        $this->assertSame([9, 'closed_won', 'finalization', 10], [(int) $lead->converted_tenant_id, $lead->status, $lead->workflow_phase, (int) $lead->converted_by]);
        $this->assertSame('completed', LeadTask::where('title', 'Negotiate Terms')->first()->status->value);
        $this->assertStringContainsString('TN-TEST', LeadTask::where('title', 'Negotiate Terms')->first()->completion_notes);
        $this->assertSame(1, LeadTask::where('lead_id', 5)->where('phase', 'finalization')->count(), 'finalization tasks start for the agent');

        $this->expectException(\Exception::class);
        app(LeadConversionService::class)->markConverted(Lead::find(5), TenantManagement::find(9));
    }

    public function test_a_lead_can_be_converted_once_details_and_qualification_are_confirmed(): void
    {
        DB::table('leads')->insert(['id' => 7, 'user_id' => 10]);
        $lead = Lead::find(7);
        $this->assertFalse($lead->readyToConvert());
        $this->assertSame(['confirm the lead details', 'confirm the qualification'], $lead->missingForConversion());
        DB::table('leads')->where('id', 7)->update(['details_confirmed_at' => now()]);
        $this->assertSame(['confirm the qualification'], Lead::find(7)->missingForConversion());
        DB::table('leads')->where('id', 7)->update(['qualification_confirmed_at' => now()]);
        $this->assertTrue(Lead::find(7)->readyToConvert());
        DB::table('leads')->where('id', 7)->update(['converted_tenant_id' => 9]);
        $this->assertFalse(Lead::find(7)->readyToConvert(), 'converted leads cannot be converted again');
    }

    public function test_a_lead_records_the_team_member_who_added_it(): void
    {
        $this->signIn(32);   // Kevin, a team member of business 10
        $controller = app(\App\Http\Controllers\Business\Crm\LeadManagementController::class);
        $method = new \ReflectionMethod($controller, 'prepareLeadData');
        $data = $method->invoke($controller, \Illuminate\Http\Request::create('/x', 'POST', ['source' => 'website', 'priority' => 'warm']), 99);
        $this->assertSame(10, (int) $data['user_id'], 'the lead belongs to the business');
        $this->assertSame(32, (int) $data['created_by'], 'and records who captured it');
    }

    public function test_agents_are_emailed_when_a_tenancy_is_assigned(): void
    {
        Mail::fake();
        $this->signIn(10, true);
        DB::table('tenant_profiles')->insert(['id' => 1, 'tenant_type' => 'Business', 'business_name' => 'Duka Ltd']);
        DB::table('tenant_management')->insert(['id' => 3, 'tenancy_ref' => 'TN-MAIL', 'tenant_profile_id' => 1, 'commercial_unit_id' => 1, 'complex_id' => 1, 'occupied_sq_area' => 300, 'managed_by' => 10, 'assigned_agent_id' => 2, 'monthly_payment' => 45000]);

        $sent = app(TenancyNotificationService::class)->send(TenantManagement::find(3));
        $this->assertSame(['kevin@x.test', 'jane@x.test'], $sent, 'the agent, and the lead agent they report to');
        Mail::assertSent(TenancyAssignedMail::class, fn ($mail) => $mail->hasTo('kevin@x.test') && !$mail->asLeadAgent && $mail->details['tenant_name'] === 'Duka Ltd' && str_contains($mail->details['unit'], 'S1'));
        Mail::assertSent(TenancyAssignedMail::class, fn ($mail) => $mail->hasTo('jane@x.test') && $mail->asLeadAgent && $mail->details['placed_by'] === 'Kevin M');
    }
}
