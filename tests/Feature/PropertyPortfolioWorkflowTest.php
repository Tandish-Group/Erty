<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Properties\ComplexWorkflowController;
use App\Models\{Complex, User};
use App\Services\Properties\ComplexSetupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PropertyPortfolioWorkflowTest extends TestCase
{
    use \Tests\Concerns\CreatesAssignmentSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'portfolio_test', 'database.connections.portfolio_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Schema::create('complexes', function (Blueprint $table) {
            $table->id(); $table->integer('user_id');
            foreach (['title', 'ref_code', 'email', 'mobile', 'county', 'county_specific', 'location_description', 'description', 'slug', 'type', 'class', 'setup_status'] as $field) $table->string($field)->nullable();
            $table->double('latitude'); $table->double('longitude'); $table->boolean('principal'); $table->boolean('available')->default(false);
            $table->timestamp('setup_confirmed_at')->nullable(); $table->integer('setup_confirmed_by')->nullable(); $table->timestamps();
        });
        Schema::create('complex_changes', function (Blueprint $table) {
            $table->id(); $table->integer('complex_id'); $table->string('field_name'); $table->text('old_value')->nullable(); $table->text('new_value');
            $table->integer('user_id'); $table->string('user_ip')->nullable(); $table->timestamps();
        });
        foreach (['complex_residential_units', 'commercial_units'] as $name) Schema::create($name, function (Blueprint $table) { $table->id(); $table->integer('complex_id'); });
        $this->asUser();
        $this->mock(ComplexSetupService::class, function ($mock) {
            $mock->shouldReceive('steps')->andReturnUsing(fn ($complex) => [
                'identity' => ['valid' => true, 'fingerprint' => $this->fingerprint($complex), 'review' => null],
                'structure' => ['valid' => true, 'fingerprint' => hash('sha256', $complex->type), 'review' => null],
                'facilities' => ['valid' => true, 'optional' => true, 'fingerprint' => str_repeat('b', 64), 'review' => null],
            ]);
        });
        $this->createAssignmentSchema();
    }

    private function asUser(bool $employee = false, array $permissions = []): void
    {
        $user = new User;
        $user->forceFill(['id' => $employee ? 11 : 10, 'is_sub_account' => $employee, 'main_account_user_id' => 10, 'business_permissions' => $permissions]);
        $user->setRelation('businessRole', null);
        $this->actingAs($user);
    }

    private function data(): array
    {
        return ['title' => 'Map test property', 'ref_code' => 'TEST-MAP', 'email' => 'qa@example.test', 'mobile' => '+254700000000',
            'county' => 'Nairobi', 'county_specific' => 'Westlands', 'location_description' => 'Test entrance', 'description' => 'Synthetic property',
            'latitude' => -1.26, 'longitude' => 36.8, 'principal' => 1, 'composition' => 'combined'];
    }

    private function property(): Complex
    {
        return Complex::create(array_diff_key($this->data(), ['composition' => true]) + ['user_id' => 10, 'slug' => 'map-test', 'type' => 'Apartment, Commercial Unit', 'setup_status' => 'ready', 'setup_confirmed_at' => now(), 'setup_confirmed_by' => 10]);
    }

    private function fingerprint(Complex $complex): string
    {
        return hash('sha256', json_encode($complex->only(['title', 'latitude', 'longitude'])));
    }

    private function request(array $data, bool $json = true): Request
    {
        $request = Request::create('/complex-workflow', 'POST', $data);
        if ($json) $request->headers->set('Accept', 'application/json');
        return $request;
    }

    public function test_draft_json_returns_saved_location_and_setup_destination(): void
    {
        $response = (new ComplexWorkflowController)->draft($this->request($this->data()));
        $result = $response->getData(true);
        $property = Complex::firstOrFail();
        $this->assertTrue($result['success']);
        $this->assertEquals(-1.26, $property->latitude);
        $this->assertEquals(36.8, $property->longitude);
        $this->assertSame('draft', $property->setup_status);
        $this->assertSame(route('complex.setup', [$property->slug, 'step' => 'identity']), $result['redirect']);
    }

    public function test_map_edit_invalidates_readiness_audits_coordinates_and_accepts_next_save(): void
    {
        $property = $this->property();
        $data = $this->data() + ['fingerprint' => $this->fingerprint($property)];
        $data['latitude'] = 0; $data['longitude'] = 0;
        $controller = new ComplexWorkflowController;
        $response = $controller->details($this->request($data), $property->slug);
        $this->assertTrue($response->getData(true)['success']);
        $property->refresh();
        $this->assertSame('draft', $property->setup_status);
        $this->assertNull($property->setup_confirmed_at);
        $this->assertEquals(0, $property->latitude);
        $this->assertEquals(0, $property->longitude);
        $this->assertSame(2, DB::table('complex_changes')->count());
        $data['fingerprint'] = $this->fingerprint($property); $data['title'] = 'Updated title';
        $this->assertTrue($controller->details($this->request($data), $property->slug)->getData(true)['success']);
    }

    public function test_stale_edit_cannot_overwrite_location(): void
    {
        $property = $this->property();
        $data = $this->data() + ['fingerprint' => str_repeat('a', 64)];
        try { (new ComplexWorkflowController)->details($this->request($data), $property->slug); $this->fail('Expected stale edit failure'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('details', $error->errors()); }
        $this->assertEquals(-1.26, $property->fresh()->latitude);
        $this->assertSame(0, DB::table('complex_changes')->count());
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $data = $this->data(); $data['latitude'] = 91; $data['longitude'] = -181;
        try { (new ComplexWorkflowController)->draft($this->request($data)); $this->fail('Expected invalid coordinates'); }
        catch (ValidationException $error) {
            $this->assertArrayHasKey('latitude', $error->errors()); $this->assertArrayHasKey('longitude', $error->errors());
        }
        $this->assertSame(0, Complex::count());
    }

    public function test_view_only_employee_cannot_save_and_foreign_property_is_hidden(): void
    {
        $property = $this->property();
        $data = $this->data() + ['fingerprint' => $this->fingerprint($property)];
        $this->asUser(true, ['complex_management' => ['view']]);
        try { (new ComplexWorkflowController)->details($this->request($data), $property->slug); $this->fail('Expected denial'); }
        catch (HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        $this->asUser(); $property->update(['user_id' => 20]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        (new ComplexWorkflowController)->details($this->request($data), $property->slug);
    }

    public function test_review_json_hands_off_to_next_step_and_requires_deferral_reason(): void
    {
        $property = $this->property();
        $controller = new ComplexWorkflowController;
        $response = $controller->review($this->request(['acknowledged' => 1, 'fingerprint' => $this->fingerprint($property), 'decision' => 'confirmed']), $property->slug, 'identity');
        $this->assertSame(route('complex.setup', [$property->slug, 'step' => 'structure']), $response->getData(true)['redirect']);
        try { $controller->review($this->request(['acknowledged' => 1, 'fingerprint' => str_repeat('b', 64), 'decision' => 'deferred']), $property->slug, 'facilities'); $this->fail('Expected deferral reason'); }
        catch (ValidationException $error) { $this->assertArrayHasKey('review', $error->errors()); }
        $this->assertSame(1, DB::table('complex_changes')->count());
    }

    public function test_composition_cannot_hide_existing_inventory_and_can_add_a_type(): void
    {
        $property = $this->property();
        DB::table('complex_residential_units')->insert(['complex_id' => $property->id]);
        $controller = new ComplexWorkflowController;
        try {
            $controller->composition($this->request(['composition' => 'commercial', 'fingerprint' => hash('sha256', $property->type)]), $property->slug);
            $this->fail('Expected inventory protection');
        } catch (ValidationException $error) { $this->assertArrayHasKey('composition', $error->errors()); }
        $this->assertSame('Apartment, Commercial Unit', $property->fresh()->type);
        $property->update(['type' => 'Apartment']);
        $response = $controller->composition($this->request(['composition' => 'combined', 'fingerprint' => hash('sha256', 'Apartment')]), $property->slug);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertSame('Apartment, Commercial Unit', $property->fresh()->type);
        $this->assertSame('draft', $property->fresh()->setup_status);
        $this->assertSame(1, DB::table('complex_changes')->where('field_name', 'type')->count());
    }

    public function test_normal_form_submission_keeps_redirect_fallback(): void
    {
        $response = (new ComplexWorkflowController)->draft($this->request($this->data(), false));
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('step=identity', $response->getTargetUrl());
    }
}
