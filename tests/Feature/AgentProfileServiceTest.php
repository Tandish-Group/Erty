<?php

namespace Tests\Feature;

use App\Models\{Agent, User};
use App\Services\Agents\AgentProfileService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** A sign-in (owner or team member) can also work as a CRM agent through a linked agent profile. */
class AgentProfileServiceTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'agent_test', 'database.connections.agent_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Schema::create('agents', function (Blueprint $t) {
            $t->id(); $t->integer('user_id')->nullable()->unique(); $t->string('agent_type')->default('employee');
            $t->string('first_name'); $t->string('last_name'); $t->string('email')->unique(); $t->string('phone');
            $t->string('employment_type')->nullable(); $t->date('start_date')->nullable(); $t->string('status')->default('active');
            $t->boolean('is_active')->default(true); $t->decimal('commission_rate')->default(0);
            $t->integer('created_by')->nullable(); $t->integer('updated_by')->nullable(); $t->softDeletes(); $t->timestamps();
        });
        // Business 10 already has an external agent and an agent record for Grace's email address.
        DB::table('agents')->insert([
            ['id' => 1, 'first_name' => 'External', 'last_name' => 'Agency', 'email' => 'agency@example.test', 'phone' => '1', 'created_by' => 10, 'user_id' => null],
            ['id' => 2, 'first_name' => 'Grace', 'last_name' => 'W', 'email' => 'grace@example.test', 'phone' => '2', 'created_by' => 10, 'user_id' => null],
            ['id' => 3, 'first_name' => 'Other', 'last_name' => 'Biz', 'email' => 'brian@example.test', 'phone' => '3', 'created_by' => 20, 'user_id' => null],
        ]);
        // Switching an agent off ends their unit and property assignments.
        foreach (['complex_residential_units', 'commercial_units'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->integer('assigned_agent_id')->nullable(); $t->string('unit_number')->nullable(); $t->timestamps(); });
        }
        Schema::create('complexes', function (Blueprint $t) { $t->id(); $t->integer('user_id'); $t->string('title')->nullable(); $t->integer('assigned_agent_id')->nullable(); $t->timestamps(); });
        Schema::create('agent_commission_tiers', function (Blueprint $t) { $t->id(); $t->integer('agent_id'); $t->decimal('min_value'); $t->decimal('max_value')->nullable(); $t->decimal('rate'); $t->timestamps(); });
        $this->createCommissionSchema();
    }

    private function member(int $id, string $email): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'first_name' => 'Team', 'last_name' => 'Member', 'email' => $email, 'mobile' => '+254700', 'is_sub_account' => true, 'main_account_user_id' => 10]);
        $user->exists = true;
        return $user;
    }

    public function test_an_existing_agent_with_the_same_email_becomes_the_members_profile(): void
    {
        $grace = $this->member(11, 'Grace@example.test');
        $agent = app(AgentProfileService::class)->ensureFor($grace);
        $this->assertSame(2, $agent->id);
        $this->assertSame(11, (int) $agent->user_id);
        $this->assertSame(2, app(AgentProfileService::class)->ensureFor($grace)->id, 'running it again keeps the same profile');
    }

    public function test_a_new_profile_is_created_for_the_business_otherwise(): void
    {
        $agent = app(AgentProfileService::class)->ensureFor($this->member(12, 'new.agent@example.test'));
        $this->assertSame(10, (int) $agent->created_by, 'agents belong to the business owner');
        $this->assertTrue($agent->is_active);
        $this->assertSame(4, Agent::count());
    }

    public function test_an_email_used_by_another_business_agent_is_refused(): void
    {
        $this->expectException(ValidationException::class);
        app(AgentProfileService::class)->ensureFor($this->member(13, 'brian@example.test'));
    }

    public function test_releasing_keeps_the_profile_but_deactivates_it(): void
    {
        $grace = $this->member(11, 'grace@example.test');
        app(AgentProfileService::class)->ensureFor($grace);
        app(AgentProfileService::class)->release($grace);
        $agent = Agent::find(2);
        $this->assertFalse($agent->is_active);
        $this->assertSame(11, (int) $agent->user_id);
        $this->assertTrue(app(AgentProfileService::class)->ensureFor($grace)->is_active, 'switching it back on reactivates it');
    }

    public function test_an_existing_agent_can_be_linked_and_unlinked(): void
    {
        $service = app(AgentProfileService::class);
        $kevin = $this->member(12, 'kevin@example.test');
        $agent = $service->link($kevin, Agent::find(1));
        $this->assertSame(12, (int) $agent->user_id);
        $this->assertSame(1, AgentProfileService::current($kevin)->id);

        $service->unlink($agent->fresh());
        $this->assertNull(Agent::find(1)->user_id, 'the agent stays, without a sign-in');
        $this->assertNull(AgentProfileService::current($kevin));
    }

    public function test_linking_refuses_other_businesses_and_double_links(): void
    {
        $service = app(AgentProfileService::class);
        try {
            $service->link($this->member(12, 'kevin@example.test'), Agent::find(3));
            $this->fail('An agent of another business must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('agent_id', $e->errors());
        }

        $service->link($this->member(12, 'kevin@example.test'), Agent::find(1));
        $this->expectException(ValidationException::class);
        $service->link($this->member(13, 'other@example.test'), Agent::find(1));
    }

    public function test_a_person_with_an_agent_profile_cannot_link_a_second_one(): void
    {
        $service = app(AgentProfileService::class);
        $grace = $this->member(11, 'grace@example.test');
        $service->ensureFor($grace);
        $this->expectException(ValidationException::class);
        $service->link($grace, Agent::find(1));
    }

    public function test_the_matching_unlinked_agent_is_suggested(): void
    {
        $this->assertSame(2, AgentProfileService::suggestedFor($this->member(11, 'GRACE@example.test'))->id);
        $this->assertNull(AgentProfileService::suggestedFor($this->member(12, 'nobody@example.test')));
    }

    public function test_my_agent_is_the_signed_in_persons_profile_not_the_first_agent(): void
    {
        $grace = $this->member(11, 'grace@example.test');
        $this->assertNull(AgentProfileService::current($grace), 'no profile until linked (previously the first agent was used)');
        app(AgentProfileService::class)->ensureFor($grace);
        $this->assertSame(2, AgentProfileService::current($grace)->id);
        $this->assertNull(AgentProfileService::current($this->member(12, 'x@example.test')));
    }
}
