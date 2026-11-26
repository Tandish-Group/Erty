<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Properties\{CommercialUnitController, ComplexController, ComplexWorkflowController};
use App\Models\{CommercialUnit, Complex, ComplexResidentialUnit, User};
use App\Services\Properties\ComplexInventoryService;
use App\Services\Properties\ComplexSetupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Full property setup lifecycle against the real ComplexSetupService (no mocks of the
 * review/fingerprint logic): residential, commercial and mixed-use creation, editing and confirmation.
 */
class PropertyLifecycleTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'lifecycle_test', 'database.connections.lifecycle_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->timestamps(); });
        Schema::create('complexes', function (Blueprint $t) {
            $t->id(); $t->integer('user_id');
            foreach (['title', 'ref_code', 'email', 'mobile', 'county', 'county_specific', 'location_description', 'description', 'slug', 'type', 'class', 'setup_status'] as $f) $t->string($f)->nullable();
            $t->double('latitude')->nullable(); $t->double('longitude')->nullable(); $t->boolean('principal')->default(true); $t->boolean('available')->default(false);
            $t->integer('assigned_agent_id')->nullable(); $t->timestamp('setup_confirmed_at')->nullable(); $t->integer('setup_confirmed_by')->nullable(); $t->timestamps();
        });
        Schema::create('complex_units', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->string('unit_category')->nullable(); $t->string('unit_type')->nullable();
            $t->integer('bedrooms')->nullable(); $t->integer('bathrooms')->nullable(); $t->integer('unit_subdivision')->nullable(); $t->integer('unit_count')->nullable(); $t->double('sq_ft')->nullable(); $t->timestamps();
            $t->integer('storeys')->nullable(); $t->double('plot_size')->nullable(); $t->string('plot_unit')->nullable(); $t->string('offer')->default('rent'); $t->double('sale_price')->nullable(); $t->json('features')->nullable();
        });
        Schema::create('complex_residential_units', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->integer('complex_unit_id')->nullable(); $t->string('unit_category')->nullable(); $t->string('unit_number')->nullable();
            $t->integer('floor')->nullable(); $t->integer('bedrooms')->nullable(); $t->integer('bathrooms')->nullable(); $t->double('sq_ft')->nullable(); $t->string('status')->default('available'); $t->timestamps();
            $t->string('house_type')->nullable(); $t->string('plot_number')->nullable(); $t->double('plot_size')->nullable(); $t->string('plot_unit')->nullable(); $t->string('title_number')->nullable();
            $t->string('offer')->default('rent'); $t->double('sale_price')->nullable();
        });
        Schema::create('commercial_units', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->string('unit_number')->nullable(); $t->double('unit_sq_ft')->nullable(); $t->integer('max_subdivisions')->nullable();
            $t->double('min_sq_ft')->nullable(); $t->string('floors')->nullable(); $t->string('custom_floors')->nullable(); $t->double('monthly_rent')->nullable();
            $t->string('status')->default('Available'); $t->text('description')->nullable(); $t->timestamps();
        });
        Schema::create('tenant_management', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id')->nullable(); $t->integer('residential_unit_id')->nullable(); $t->integer('commercial_unit_id')->nullable();
            $t->double('occupied_sq_area')->nullable(); $t->double('sq_area')->nullable(); $t->string('status')->default('Active'); $t->timestamps();
        });
        Schema::create('listings', function (Blueprint $t) {
            $t->id(); $t->text('name'); $t->string('ref')->unique(); $t->text('slug'); $t->text('category'); $t->text('type'); $t->string('bedrooms')->nullable();
            $t->integer('bathrooms')->nullable(); $t->integer('sq_area')->nullable(); $t->integer('amount')->nullable(); $t->integer('deposit')->nullable();
            $t->text('status')->default('Draft'); $t->integer('complex_id')->nullable(); $t->integer('complex_unit_id')->nullable()->unique(); $t->integer('commercial_unit_id')->nullable()->unique();
            $t->integer('user_id')->nullable(); $t->string('currency')->default('KES'); $t->integer('units')->default(1); $t->timestamps();
            $t->string('offer')->default('rent'); $t->decimal('sale_price')->nullable();
        });
        foreach (['complex_amenities' => ['amenity_type'], 'complex_parking_slots' => ['parking_id'], 'service_charges' => ['name'], 'complex_links' => ['link'], 'complex_floor_plans' => ['plan']] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($columns) { $t->id(); $t->integer('complex_id')->nullable(); foreach ($columns as $c) $t->string($c)->nullable(); $t->timestamps(); });
        }
        Schema::create('complex_changes', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->string('field_name'); $t->text('old_value')->nullable(); $t->text('new_value')->nullable();
            $t->integer('user_id')->nullable(); $t->string('user_ip')->nullable(); $t->timestamps();
        });
        Schema::create('agents', function (Blueprint $t) { $t->id(); $t->integer('created_by'); $t->integer('user_id')->nullable(); $t->boolean('is_active')->default(true); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->softDeletes(); $t->timestamps(); });

        DB::table('users')->insert(['id' => 10, 'first_name' => 'Owner']);
        $this->actAs(10);
        $this->createAssignmentSchema();
        $this->createSalesSchema();
    }

    private function actAs(int $id, bool $employee = false, array $permissions = []): void
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'is_sub_account' => $employee, 'main_account_user_id' => 10, 'business_permissions' => $permissions]);
        $user->setRelation('businessRole', null);
        $this->actingAs($user);
    }

    private function request(array $data = [], bool $json = true, string $method = 'POST'): Request
    {
        $request = Request::create('/test', $method, $data);
        if ($json) $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => auth()->user());
        return $request;
    }

    private function draft(string $composition, string $title): Complex
    {
        (new ComplexWorkflowController)->draft($this->request([
            'title' => $title, 'ref_code' => strtoupper(substr($composition, 0, 3)) . '-1', 'email' => 'qa@example.test', 'mobile' => '+254700000000',
            'county' => 'Nairobi', 'county_specific' => 'Westlands', 'location_description' => 'Gate B', 'description' => 'Synthetic property',
            'latitude' => -1.26, 'longitude' => 36.8, 'principal' => 1, 'composition' => $composition,
        ]));
        return Complex::where('title', $title)->firstOrFail();
    }

    private function step(Complex $complex, string $key): array
    {
        return app(ComplexSetupService::class)->steps($complex->fresh())[$key];
    }

    private function generate(Complex $complex, int $count, int $bedrooms = 2, float $area = 850): void
    {
        (new ComplexWorkflowController)->generate($this->request(['bedrooms' => $bedrooms, 'bathrooms' => 1, 'sq_ft' => $area, 'unit_count' => $count, 'fingerprint' => $this->step($complex, 'residential')['fingerprint']]), $complex->slug);
    }

    private function refineAll(Complex $complex, string $prefix): void
    {
        foreach (ComplexResidentialUnit::where('complex_id', $complex->id)->orderBy('id')->get() as $i => $unit) {
            (new ComplexWorkflowController)->refineResidential($this->request([
                'unit_number' => $prefix . ($i + 1), 'floor' => intdiv($i, 2), 'sq_ft' => $unit->sq_ft, 'fingerprint' => $this->step($complex, 'residential')['fingerprint'],
            ]), $complex->slug, $unit->id);
        }
    }

    private function addCommercial(Complex $complex, array $rows)
    {
        return (new CommercialUnitController)->store($this->request(['complex_id' => $complex->id, 'kt_commercial_units_repeater' => $rows]));
    }

    private function space(string $number, float $area = 1000, float $min = 200, int $subdivisions = 2): array
    {
        return ['unit_number' => $number, 'unit_sq_ft' => $area, 'min_sq_ft' => $min, 'max_subdivisions' => $subdivisions, 'floors' => 'Ground Floor', 'status' => 'Available'];
    }

    private function reviewAll(Complex $complex): void
    {
        foreach (app(ComplexSetupService::class)->steps($complex->fresh()) as $key => $step) {
            if (!$step['valid']) continue; // incomplete steps cannot be reviewed
            (new ComplexWorkflowController)->review($this->request(['acknowledged' => 1, 'fingerprint' => $step['fingerprint'], 'decision' => !empty($step['optional']) ? 'deferred' : 'confirmed', 'note' => 'Not applicable to this test property.']), $complex->slug, $key);
        }
    }

    private function confirm(Complex $complex)
    {
        return (new ComplexController())->confirmSetup($this->request(['review_acknowledged' => 1], false), $complex->slug);
    }

    public function test_residential_property_is_generated_refined_reviewed_and_confirmed(): void
    {
        $complex = $this->draft('residential', 'Riverside');
        $this->generate($complex, 4);
        $this->generate($complex, 2, 3, 1200);
        $units = ComplexResidentialUnit::where('complex_id', $complex->id)->get();
        $this->assertCount(6, $units);
        $this->assertSame(2, DB::table('complex_units')->where('complex_id', $complex->id)->count());
        $this->assertTrue($units->every(fn ($u) => $u->sq_ft > 0 && $u->status === 'available'), 'generated units carry the specification area');
        $this->assertFalse($this->step($complex, 'residential')['valid'], 'unnumbered units cannot be confirmed');

        $this->refineAll($complex, 'A-');
        $this->assertTrue($this->step($complex, 'residential')['valid']);
        $this->reviewAll($complex);
        $response = $this->confirm($complex);
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/complex/' . $complex->slug, $response->getTargetUrl());
        $this->assertSame('ready', $complex->fresh()->setup_status);
        $this->assertTrue(app(ComplexSetupService::class)->ready($complex->fresh()));
        $this->assertSame(2, DB::table('listings')->where('complex_id', $complex->id)->where('status', 'Draft')->count(), 'confirming creates a draft listing per unit type');
    }

    public function test_assigning_agents_does_not_reopen_the_setup_review(): void
    {
        Schema::table('complex_residential_units', fn (Blueprint $t) => $t->integer('assigned_agent_id')->nullable());
        $complex = $this->draft('residential', 'Agent Court');
        $this->generate($complex, 2);
        $this->refineAll($complex, 'G-');
        $this->reviewAll($complex);
        $this->confirm($complex);
        DB::table('agents')->insert(['id' => 8, 'created_by' => 10, 'user_id' => 80]);
        DB::table('complexes')->where('id', $complex->id)->update(['assigned_agent_id' => 8]);
        DB::table('complex_residential_units')->where('complex_id', $complex->id)->limit(1)->update(['assigned_agent_id' => 8]);
        $this->assertTrue(app(ComplexSetupService::class)->ready($complex->fresh()), 'property and unit agents are operational, not part of setup');
    }

    public function test_giving_a_parking_slot_to_a_tenant_does_not_reopen_the_setup_review(): void
    {
        Schema::table('complex_parking_slots', function (Blueprint $t) { $t->boolean('is_occupied')->default(false); $t->integer('occupied_tenant_id')->nullable(); });
        $complex = $this->draft('residential', 'Parking Court');
        $this->generate($complex, 2);
        $this->refineAll($complex, 'P-');
        DB::table('complex_parking_slots')->insert([['complex_id' => $complex->id, 'parking_id' => 'PC-01'], ['complex_id' => $complex->id, 'parking_id' => 'PC-02']]);
        $this->reviewAll($complex);
        $this->confirm($complex);
        $this->assertTrue(app(ComplexSetupService::class)->ready($complex->fresh()));

        // Onboarding a tenant with a parking slot marks it occupied by that tenancy.
        DB::table('complex_parking_slots')->where('parking_id', 'PC-01')->update(['is_occupied' => true, 'occupied_tenant_id' => 12]);
        $this->assertTrue(app(ComplexSetupService::class)->ready($complex->fresh()), 'parking occupancy is operational; the next tenant can still be onboarded');

        DB::table('complex_parking_slots')->where('parking_id', 'PC-02')->update(['parking_id' => 'PC-02B']);
        $this->assertFalse(app(ComplexSetupService::class)->ready($complex->fresh()), 'renaming a slot is a setup change');
    }

    public function test_status_change_keeps_setup_but_identity_change_requires_review(): void
    {
        $complex = $this->draft('residential', 'Status Court');
        $this->generate($complex, 2);
        $this->refineAll($complex, 'S-');
        $this->reviewAll($complex);
        $this->confirm($complex);
        $unit = ComplexResidentialUnit::where('complex_id', $complex->id)->orderBy('id')->first();

        (new ComplexWorkflowController)->refineResidential($this->request(['unit_number' => $unit->unit_number, 'floor' => $unit->floor, 'sq_ft' => $unit->sq_ft, 'status' => 'maintenance', 'fingerprint' => $this->step($complex, 'residential')['fingerprint']]), $complex->slug, $unit->id);
        $this->assertSame('maintenance', $unit->fresh()->status);
        $this->assertSame('ready', $complex->fresh()->setup_status);
        $this->assertTrue(app(ComplexSetupService::class)->ready($complex->fresh()), 'availability is not part of the setup review');

        (new ComplexWorkflowController)->refineResidential($this->request(['unit_number' => $unit->unit_number, 'floor' => $unit->floor, 'sq_ft' => $unit->sq_ft + 10, 'status' => 'maintenance', 'fingerprint' => $this->step($complex, 'residential')['fingerprint']]), $complex->slug, $unit->id);
        $this->assertSame('draft', $complex->fresh()->setup_status);
        $this->assertFalse(app(ComplexSetupService::class)->ready($complex->fresh()));
    }

    public function test_occupied_unit_status_cannot_be_changed_manually(): void
    {
        $complex = $this->draft('residential', 'Tenanted Court');
        $this->generate($complex, 1);
        $this->refineAll($complex, 'T-');
        $unit = ComplexResidentialUnit::where('complex_id', $complex->id)->first();
        $unit->update(['status' => 'occupied']);
        DB::table('tenant_management')->insert(['complex_id' => $complex->id, 'residential_unit_id' => $unit->id, 'status' => 'Active']);

        $this->expectException(ValidationException::class);
        (new ComplexWorkflowController)->refineResidential($this->request(['unit_number' => $unit->unit_number, 'floor' => $unit->floor, 'sq_ft' => $unit->sq_ft, 'status' => 'available', 'fingerprint' => $this->step($complex, 'residential')['fingerprint']]), $complex->slug, $unit->id);
    }

    public function test_commercial_spaces_are_validated_on_create_and_update(): void
    {
        $complex = $this->draft('commercial', 'Business Centre');
        $this->assertSame(200, $this->addCommercial($complex, [$this->space('G-01'), $this->space('G-02', 800, 400, 0)])->getStatusCode());
        $this->assertSame(2, CommercialUnit::where('complex_id', $complex->id)->count());

        $rejected = $this->addCommercial($complex, [['unit_number' => 'g-01', 'unit_sq_ft' => 100, 'min_sq_ft' => 200, 'max_subdivisions' => 1, 'floors' => '', 'status' => 'Occupied']]);
        $this->assertSame(422, $rejected->getStatusCode());
        $this->assertSame(2, CommercialUnit::where('complex_id', $complex->id)->count());
        $this->assertEqualsCanonicalizing(['unit_number', 'min_sq_ft', 'floors', 'status'], array_map(fn ($k) => explode('.', $k)[2], array_keys($rejected->getData(true)['errors'])));

        $space = CommercialUnit::where('complex_id', $complex->id)->where('unit_number', 'G-01')->first();
        DB::table('tenant_management')->insert(['complex_id' => $complex->id, 'commercial_unit_id' => $space->id, 'occupied_sq_area' => 600, 'status' => 'Active']);
        $space->update(['status' => 'Occupied']);
        $response = (new CommercialUnitController)->update($this->request(['unit_number' => 'G-01', 'unit_sq_ft' => 500, 'min_sq_ft' => 200, 'max_subdivisions' => 2, 'floors' => 'Ground Floor', 'status' => 'Occupied'], false, 'PUT'), encrypt($space->id));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertEquals(1000, $space->fresh()->unit_sq_ft, 'area cannot shrink below the 600 sq ft let to an active tenant');

        (new CommercialUnitController)->update($this->request(['unit_number' => 'G-01', 'unit_sq_ft' => 1100, 'min_sq_ft' => 200, 'max_subdivisions' => 2, 'floors' => 'Ground Floor', 'status' => 'Occupied'], false, 'PUT'), encrypt($space->id));
        $this->assertEquals(1100, $space->fresh()->unit_sq_ft);
        $this->assertTrue(DB::table('complex_changes')->where('field_name', 'commercial_unit:' . $space->id)->count() >= 2, 'creation and edit are audited');
    }

    public function test_mixed_use_needs_both_inventories_before_confirmation(): void
    {
        $complex = $this->draft('combined', 'Kilimani');
        $this->generate($complex, 2);
        $this->refineAll($complex, 'K-');
        $this->assertFalse($this->step($complex, 'commercial')['valid']);
        $this->reviewAll($complex);
        $this->confirm($complex);
        $this->assertSame('draft', $complex->fresh()->setup_status, 'cannot confirm without commercial spaces');

        $this->addCommercial($complex, [$this->space('S-01')]);
        $this->reviewAll($complex);
        $this->confirm($complex);
        $this->assertSame('ready', $complex->fresh()->setup_status);
        $this->assertSame(2, ComplexResidentialUnit::where('complex_id', $complex->id)->count());
        $this->assertSame(1, CommercialUnit::where('complex_id', $complex->id)->count());
    }

    public function test_advanced_settings_keep_slug_validate_links_and_scope(): void
    {
        $complex = $this->draft('residential', 'Advanced Court');
        $slug = $complex->slug;
        // Agent 5 signs in; agent 7 is an external agent (no sign-in); agent 6 is another business's.
        DB::table('agents')->insert([['id' => 5, 'created_by' => 10, 'user_id' => 50], ['id' => 6, 'created_by' => 99, 'user_id' => 60], ['id' => 7, 'created_by' => 10, 'user_id' => null]]);

        $response = (new ComplexWorkflowController)->advanced($this->request(['class' => 'Luxury', 'links' => "https://example.com/a\nhttps://example.com/b", 'assigned_agent_id' => 5]), $slug);
        $this->assertTrue($response->getData(true)['success']);
        $fresh = $complex->fresh();
        $this->assertSame([$slug, 'Luxury', 5], [$fresh->slug, $fresh->class, (int) $fresh->assigned_agent_id]);
        $this->assertSame(2, DB::table('complex_links')->where('complex_id', $complex->id)->count());

        try { (new ComplexWorkflowController)->advanced($this->request(['class' => 'Luxury', 'assigned_agent_id' => 6]), $slug); $this->fail('Foreign agent accepted'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('assigned_agent_id', $e->errors()); }
        try { (new ComplexWorkflowController)->advanced($this->request(['class' => 'Luxury', 'assigned_agent_id' => 7]), $slug); $this->fail('Agent without a sign-in accepted'); }
        catch (ValidationException $e) { $this->assertStringContainsString('signs in', $e->errors()['assigned_agent_id'][0]); }
        try { (new ComplexWorkflowController)->advanced($this->request(['class' => 'Luxury', 'links' => 'not a url']), $slug); $this->fail('Invalid link accepted'); }
        catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }

        $complex->update(['user_id' => 20]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        (new ComplexWorkflowController)->advanced($this->request(['class' => 'Regular']), $slug);
    }

    /** Prepare tables used by property assignments (team members, roles, agents). */
    private function teamTables(): void
    {
        Schema::table('users', function (Blueprint $t) { $t->boolean('is_sub_account')->default(false); $t->integer('main_account_user_id')->nullable(); $t->boolean('is_suspended')->default(false); $t->integer('business_role_id')->nullable(); });
        Schema::create('complex_user', function (Blueprint $t) { $t->id(); $t->integer('complex_id'); $t->integer('user_id'); $t->integer('assigned_by')->nullable(); $t->timestamps(); $t->unique(['complex_id', 'user_id']); });
        Schema::create('business_roles', function (Blueprint $t) { $t->id(); $t->integer('business_id'); $t->string('name')->nullable(); $t->string('department', 60)->nullable(); $t->json('permissions')->nullable(); $t->string('property_scope')->default('all'); $t->timestamps(); });
        DB::table('users')->insert([['id' => 21, 'first_name' => 'Grace', 'last_name' => 'W', 'is_sub_account' => true, 'main_account_user_id' => 10], ['id' => 22, 'first_name' => 'Brian', 'last_name' => 'O', 'is_sub_account' => true, 'main_account_user_id' => 10]]);
        DB::table('agents')->insert([['id' => 5, 'created_by' => 10, 'user_id' => 50, 'first_name' => 'Jane', 'last_name' => 'Field'], ['id' => 6, 'created_by' => 99, 'user_id' => 60, 'first_name' => 'Other', 'last_name' => 'Business']]);
    }

    private function draftWith(string $title, array $extra): void
    {
        (new ComplexWorkflowController)->draft($this->request([
            'title' => $title, 'ref_code' => 'TEAM-1', 'email' => 'qa@example.test', 'mobile' => '+254700000000',
            'county' => 'Nairobi', 'county_specific' => 'Westlands', 'location_description' => 'Gate B', 'description' => 'Synthetic property',
            'latitude' => -1.26, 'longitude' => 36.8, 'principal' => 1, 'composition' => 'residential',
        ] + $extra));
    }

    public function test_a_new_property_can_get_its_managers_and_responsible_agent(): void
    {
        $this->teamTables();
        $this->draftWith('Team Court', ['manager_ids' => [21, 22], 'assigned_agent_id' => 5]);
        $complex = Complex::where('title', 'Team Court')->firstOrFail();
        $this->assertSame(5, (int) $complex->assigned_agent_id);
        $this->assertEqualsCanonicalizing([21, 22], DB::table('complex_user')->where('complex_id', $complex->id)->pluck('user_id')->map(fn ($id) => (int) $id)->all());
        $this->assertEqualsCanonicalizing(['assigned_agent_id', 'managers'], DB::table('complex_changes')->where('complex_id', $complex->id)->pluck('field_name')->all(), 'both are recorded in the property history');
    }

    public function test_property_creation_refuses_foreign_agents_and_unpermitted_assignments(): void
    {
        $this->teamTables();
        try {
            $this->draftWith('Foreign Agent Court', ['assigned_agent_id' => 6]);
            $this->fail('An agent of another business must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('assigned_agent_id', $e->errors());
        }
        $this->actAs(23, true, ['complex_management' => ['view', 'create']]);
        try {
            $this->draftWith('No Assign Court', ['manager_ids' => [21]]);
            $this->fail('Assigning managers needs complex_management.assign.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('manager_ids', $e->errors());
        }
        $this->assertSame(0, Complex::whereIn('title', ['Foreign Agent Court', 'No Assign Court'])->count());
    }

    public function test_view_only_employee_cannot_change_inventory(): void
    {
        $complex = $this->draft('commercial', 'Read Only Plaza');
        $this->actAs(11, true, ['complex_management' => ['view']]);
        try { $this->addCommercial($complex, [$this->space('R-01')]); $this->fail('Employee without edit permission added a space'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->assertSame(0, CommercialUnit::count());
    }

    // ─── Houses ─────────────────────────────────────────────────────────────

    private function draftKinds(array $kinds, string $title): Complex
    {
        (new ComplexWorkflowController)->draft($this->request([
            'title' => $title, 'ref_code' => 'H-' . substr(md5($title), 0, 4), 'email' => 'qa@example.test', 'mobile' => '+254700000000',
            'county' => 'Nairobi', 'county_specific' => 'Runda', 'location_description' => 'Gate A', 'description' => 'Synthetic estate',
            'latitude' => -1.21, 'longitude' => 36.8, 'principal' => 1, 'kinds' => $kinds,
        ]));
        return Complex::where('title', $title)->firstOrFail();
    }

    private function houses(Complex $complex, array $spec): void
    {
        (new ComplexWorkflowController)->generate($this->request($spec + ['category' => 'house', 'bathrooms' => 3, 'sq_ft' => 2400, 'unit_count' => 1, 'offer' => 'rent',
            'fingerprint' => $this->step($complex, 'houses')['fingerprint']]), $complex->slug);
    }

    private function refineHouse(Complex $complex, ComplexResidentialUnit $house, array $data): void
    {
        (new ComplexWorkflowController)->refineResidential($this->request($data + ['unit_number' => $house->unit_number ?? 'H', 'sq_ft' => $house->sq_ft,
            'fingerprint' => $this->step($complex, 'houses')['fingerprint']]), $complex->slug, $house->id);
    }

    public function test_composition_tokens_are_exact_and_keep_their_order(): void
    {
        $this->assertSame('Apartment, House, Commercial Unit', ComplexInventoryService::typeFor(['commercial', 'houses', 'apartments']));
        $this->assertSame(['houses'], ComplexInventoryService::kindsOf('House'));
        $this->assertSame([], ComplexInventoryService::kindsOf('Townhouse'), '"House" never matches inside another word');
        $this->assertSame(['apartments', 'commercial'], ComplexInventoryService::kindsFromInput(['composition' => 'combined']), 'older forms still work');
        DB::table('complexes')->insert([['user_id' => 10, 'title' => 'A', 'type' => 'House'], ['user_id' => 10, 'title' => 'B', 'type' => 'Townhouse'], ['user_id' => 10, 'title' => 'C', 'type' => 'Apartment, House, Commercial Unit']]);
        $this->assertSame(['A', 'C'], ComplexInventoryService::whereKind(DB::table('complexes'), 'houses', false, 'type')->orderBy('title')->pluck('title')->all());
    }

    public function test_a_housing_estate_with_shops_is_set_up_with_houses_for_rent_and_for_sale(): void
    {
        $complex = $this->draftKinds(['houses', 'commercial'], 'Runda Estate');
        $this->assertSame('House, Commercial Unit', $complex->type);
        $steps = app(ComplexSetupService::class)->steps($complex);
        $this->assertArrayHasKey('houses', $steps);
        $this->assertArrayNotHasKey('residential', $steps, 'no apartments step without apartments');

        $this->houses($complex, ['house_type' => 'Maisonette', 'bedrooms' => 4, 'unit_count' => 3, 'offer' => 'both', 'sale_price' => 25000000, 'plot_size' => 0.25, 'plot_unit' => 'acres', 'storeys' => 2, 'features' => ['servant_quarter', 'garden']]);
        $this->houses($complex, ['house_type' => 'Villa', 'bedrooms' => 5, 'offer' => 'sale', 'sale_price' => 60000000]);
        $houses = ComplexResidentialUnit::where('complex_id', $complex->id)->orderBy('id')->get();
        $this->assertCount(4, $houses);
        $this->assertTrue($houses->every(fn ($h) => $h->unit_category === 'house'));
        $this->assertSame(['Maisonette', 'Maisonette', 'Maisonette', 'Villa'], $houses->pluck('house_type')->all());
        $this->assertSame(['both', 'both', 'both', 'sale'], $houses->pluck('offer')->all());
        $this->assertEquals(0.25, $houses[0]->plot_size);
        $this->assertFalse($this->step($complex, 'houses')['valid'], 'unnumbered houses cannot be confirmed');

        foreach ($houses as $i => $house) {
            $this->refineHouse($complex, $house, ['unit_number' => (string) ($i + 1), 'plot_number' => 'LR 1234/' . ($i + 1)]);
        }
        $this->assertTrue($this->step($complex, 'houses')['valid'], 'houses need a number but no floor');
        $fingerprint = $this->step($complex, 'houses')['fingerprint'];
        $this->refineHouse($complex, $houses[0]->fresh(), ['offer' => 'rent']);
        $this->assertSame('rent', $houses[0]->fresh()->offer);
        $this->assertSame($fingerprint, $this->step($complex, 'houses')['fingerprint'], 'changing what a house is offered for does not reopen setup');

        $this->addCommercial($complex, [$this->space('S1')]);
        $this->reviewAll($complex);
        $this->confirm($complex);
        $this->assertSame('ready', $complex->fresh()->setup_status);
        $listings = DB::table('listings')->where('complex_id', $complex->id)->whereNotNull('complex_unit_id')->orderBy('id')->get();
        $this->assertSame(['4-bedroom Maisonette', '5-bedroom Villa'], $listings->pluck('name')->all());
        $this->assertSame(['House', 'House'], $listings->pluck('type')->all());
        $this->assertSame(['both', 'sale'], $listings->pluck('offer')->all());
        $this->assertEquals(60000000, $listings[1]->sale_price);
    }

    public function test_a_single_house_and_the_rules_for_house_specifications(): void
    {
        $complex = $this->draftKinds(['houses'], 'Karen Cottage');
        try {
            $this->houses($complex, ['house_type' => 'Cottage', 'bedrooms' => 3, 'offer' => 'sale']);
            $this->fail('A house for sale needs an asking price');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('sale_price', $e->errors());
        }
        try {
            $this->houses($complex, ['house_type' => 'Castle', 'bedrooms' => 3]);
            $this->fail('Only known house types');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('house_type', $e->errors());
        }
        $this->houses($complex, ['house_type' => 'Cottage', 'bedrooms' => 3]);
        $this->assertSame('Single house', ComplexInventoryService::compositionLabel($complex->fresh()));
        try {
            (new ComplexWorkflowController)->generate($this->request(['bedrooms' => 2, 'bathrooms' => 1, 'sq_ft' => 800, 'unit_count' => 2, 'fingerprint' => str_repeat('a', 64)]), $complex->slug);
            $this->fail('Apartments cannot be generated on a houses-only property');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        try {
            (new ComplexWorkflowController)->composition($this->request(['kinds' => ['apartments'], 'fingerprint' => $this->step($complex, 'structure')['fingerprint']]), $complex->slug);
            $this->fail('Houses on record keep the houses kind');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('houses', $e->errors()['composition'][0]);
        }
        (new ComplexWorkflowController)->composition($this->request(['kinds' => ['houses', 'apartments'], 'fingerprint' => $this->step($complex, 'structure')['fingerprint']]), $complex->slug);
        $this->assertSame('Apartment, House', $complex->fresh()->type, 'apartments can be added beside the house');
        $this->assertArrayHasKey('residential', app(ComplexSetupService::class)->steps($complex->fresh()));
    }
}
