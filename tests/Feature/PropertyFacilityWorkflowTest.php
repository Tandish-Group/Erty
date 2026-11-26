<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Properties\PropertyFacilityController;
use App\Models\{Complex, ComplexAmenity, ComplexParkingSlot, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class PropertyFacilityWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'facilities_test', 'database.connections.facilities_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        Schema::create('complexes', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('title'); $t->string('slug'); $t->string('setup_status')->default('ready');
            $t->timestamp('setup_confirmed_at')->nullable(); $t->integer('setup_confirmed_by')->nullable(); $t->timestamps();
        });
        Schema::create('complex_amenities', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->string('amenity_type'); $t->string('currency')->nullable(); $t->decimal('annual_price', 15, 2)->nullable();
            foreach (config('property_facilities') as $definition) foreach ($definition['fields'] as $name => $field) $t->text($name)->nullable();
            $t->timestamps();
        });
        Schema::create('complex_parking_slots', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->integer('amenity_id'); $t->string('parking_id')->unique(); $t->boolean('is_occupied')->default(false); $t->integer('occupied_tenant_id')->nullable(); $t->timestamps();
        });
        Schema::create('complex_changes', function (Blueprint $t) {
            $t->id(); $t->integer('complex_id'); $t->string('field_name'); $t->text('old_value')->nullable(); $t->text('new_value'); $t->integer('user_id'); $t->string('user_ip')->nullable(); $t->timestamps();
        });
        Schema::create('service_charges', function (Blueprint $t) { $t->id(); $t->integer('amenity_id'); $t->string('amenity_type'); });
        Schema::create('tenant_management_amenities', function (Blueprint $t) { $t->id(); $t->integer('amenity_id'); });
        Schema::create('tenant_parking_slots', function (Blueprint $t) { $t->id(); $t->integer('complex_parking_slot_id'); });
        $this->user();
    }

    private function user(bool $employee = false, array $permissions = []): void
    {
        $user = new User;
        $user->forceFill(['id' => $employee ? 11 : 10, 'is_sub_account' => $employee, 'main_account_user_id' => 10, 'business_permissions' => $permissions]);
        $user->setRelation('businessRole', null);
        $this->actingAs($user);
    }

    private function property(int $owner = 10): Complex
    {
        return Complex::create(['user_id' => $owner, 'title' => 'Gardens', 'slug' => 'gardens-'.$owner, 'setup_status' => 'ready']);
    }

    private function request(array $data): Request
    {
        return Request::create('/complex/test/amenities', 'POST', $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
    }

    public function test_cost_is_saved_and_unrelated_fields_are_ignored(): void
    {
        $complex = $this->property();
        $response = app(PropertyFacilityController::class)->store($this->request(['amenity_type' => 'water', 'water_type' => 'both', 'annual_cost' => '12000.50', 'amenity_currency' => 'KES', 'gym_description' => 'Stale hidden input']), $complex->slug);
        $facility = ComplexAmenity::first();
        $this->assertTrue($response->getData()->success);
        $this->assertEquals(12000.50, $facility->annual_price);
        $this->assertSame('KES', $facility->currency);
        $this->assertNull($facility->gym_description);
        $this->assertSame('draft', $complex->fresh()->setup_status);
        $this->assertDatabaseCount('complex_changes', 1);
    }

    public function test_parking_edit_preserves_ids_and_assignments_and_adds_new_spaces(): void
    {
        $complex = $this->property(); $controller = app(PropertyFacilityController::class);
        $controller->store($this->request(['amenity_type' => 'parking', 'parking_slots' => 3, 'parking_ids' => 'RG-P01, RG-P02']), $complex->slug);
        $facility = ComplexAmenity::first(); $slot = ComplexParkingSlot::first();
        $slot->update(['is_occupied' => true, 'occupied_tenant_id' => 9]);
        $controller->update($this->request(['amenity_type' => 'parking', 'parking_slots' => 3, 'parking_ids' => 'RG-P01, RG-P02, RG-P03', 'annual_cost' => 0, 'amenity_currency' => 'KES']), $facility->id);
        $this->assertSame($slot->id, ComplexParkingSlot::where('parking_id', 'RG-P01')->first()->id);
        $this->assertEquals(9, $slot->fresh()->occupied_tenant_id);
        $this->assertDatabaseCount('complex_parking_slots', 3);
        $this->assertEquals(0, $facility->fresh()->annual_price);
    }

    public function test_assigned_parking_cannot_be_removed_or_relabelled(): void
    {
        $complex = $this->property(); $controller = app(PropertyFacilityController::class);
        $controller->store($this->request(['amenity_type' => 'parking', 'parking_ids' => 'P1']), $complex->slug);
        $slot = ComplexParkingSlot::first(); $slot->update(['is_occupied' => true]);
        foreach ([['amenity_type' => 'parking', 'parking_ids' => 'P2'], ['amenity_type' => 'water']] as $data) {
            try { $controller->update($this->request($data), $slot->amenity_id); $this->fail('Assigned parking must be retained.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('parking_ids', $e->errors()); }
        }
        $this->assertDatabaseHas('complex_parking_slots', ['id' => $slot->id, 'parking_id' => 'P1']);
        $this->assertDatabaseHas('complex_amenities', ['id' => $slot->amenity_id, 'amenity_type' => 'parking']);
    }

    public function test_invalid_capacity_duplicate_labels_and_cost_are_rejected(): void
    {
        $complex = $this->property(); $controller = app(PropertyFacilityController::class);
        foreach ([['amenity_type' => 'parking', 'parking_slots' => 1, 'parking_ids' => 'P1, P2'], ['amenity_type' => 'parking', 'parking_ids' => 'P1, p1'], ['amenity_type' => 'water', 'annual_cost' => -1], ['amenity_type' => 'water', 'annual_cost' => 0], ['amenity_type' => 'unsupported']] as $data) {
            try { $controller->store($this->request($data), $complex->slug); $this->fail('Invalid data must be rejected.'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertDatabaseCount('complex_amenities', 0);
    }

    public function test_removal_checks_links_and_bulk_removal_rolls_back(): void
    {
        $complex = $this->property(); $controller = app(PropertyFacilityController::class);
        $one = ComplexAmenity::create(['complex_id' => $complex->id, 'amenity_type' => 'water']);
        $two = ComplexAmenity::create(['complex_id' => $complex->id, 'amenity_type' => 'security']);
        DB::table('service_charges')->insert(['amenity_id' => $two->id, 'amenity_type' => 'complex_amenities']);
        try { $controller->bulkDestroy($this->request(['ids' => [$one->id, $two->id]])); $this->fail('Linked facility must be retained.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('facility', $e->errors()); }
        $this->assertDatabaseCount('complex_amenities', 2);
        $this->assertDatabaseCount('complex_changes', 0);
        $controller->destroy($this->request(['amenity_id' => $one->id]));
        $this->assertDatabaseCount('complex_amenities', 1);
    }

    public function test_view_only_staff_and_foreign_properties_cannot_be_changed(): void
    {
        $complex = $this->property(); $foreign = $this->property(99);
        $facility = ComplexAmenity::create(['complex_id' => $foreign->id, 'amenity_type' => 'water']);
        $controller = app(PropertyFacilityController::class);
        try { $controller->destroy($this->request(['amenity_id' => $facility->id])); $this->fail('Foreign facility must be hidden.'); }
        catch (ModelNotFoundException $e) { $this->assertDatabaseCount('complex_amenities', 1); }
        $this->user(true, ['complex_management' => ['view']]);
        try { $controller->store($this->request(['amenity_type' => 'water']), $complex->slug); $this->fail('Edit permission is required.'); }
        catch (HttpException $e) { $this->assertEquals(403, $e->getStatusCode()); }
        $this->user(true, ['complex_management' => ['view', 'edit']]);
        $controller->store($this->request(['amenity_type' => 'water']), $complex->slug);
        $this->assertDatabaseHas('complex_amenities', ['complex_id' => $complex->id]);
    }
}
