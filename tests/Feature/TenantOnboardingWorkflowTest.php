<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Tenancies\{TenancyOnboardingController, TenancyTableController};
use App\Http\Requests\StoreTenancyRequest;
use App\Http\Controllers\Business\Properties\ComplexController;
use App\Http\Controllers\Business\Properties\PropertyLookupController;
use App\Models\{Business, CommercialUnit, Complex, ComplexParkingSlot, ComplexResidentialUnit, ComplexUnit, TenantLease, TenantManagement, TenantProfile, User};
use App\Services\Properties\ComplexSetupService;
use App\Services\Rent\TenantChargesService;
use App\Services\Tenancy\TenantStoreService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TenantOnboardingWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'onboarding_test', 'database.connections.onboarding_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        // Only the records touched by this controller; no production migrations or data.
        foreach ([Complex::class, ComplexUnit::class, ComplexResidentialUnit::class, CommercialUnit::class, ComplexParkingSlot::class, TenantProfile::class, TenantManagement::class, TenantLease::class] as $class) {
            $model = new $class;
            Schema::create($model->getTable(), function (Blueprint $table) use ($model) {
                $table->id();
                foreach (array_diff(array_unique($model->getFillable()), ['id', 'created_at', 'updated_at']) as $field) $table->text($field)->nullable();
                if ($model instanceof TenantLease) $table->softDeletes();
                $table->timestamps();
            });
        }
        Schema::create('tenant_parking_slots', function (Blueprint $table) { $table->id(); $table->integer('tenant_management_id'); $table->integer('complex_parking_slot_id'); $table->timestamps(); });
        Schema::create('agents', function (Blueprint $table) { $table->id(); $table->integer('created_by'); });
        $user = new User; $user->forceFill(['id' => 10]);
        $business = new Business; $business->forceFill(['id' => 90]); $user->setRelation('business', $business);
        $this->actingAs($user);
        DB::table('complexes')->insert(['id' => 1, 'user_id' => 10, 'slug' => 'gardens', 'title' => 'Gardens', 'type' => 'Apartment, Commercial Unit', 'setup_status' => 'ready']);
        DB::table('complex_residential_units')->insert(['id' => 1, 'complex_id' => 1, 'unit_number' => 'A1', 'status' => 'available', 'sq_ft' => 950]);
        $this->mock(ComplexSetupService::class)->shouldReceive('ready')->andReturn(true);
        $this->partialMock(TenantStoreService::class)->shouldReceive('sendWelcomeEmail')->andReturnNull();
        $charges = $this->mock(TenantChargesService::class);
        $charges->shouldReceive('startBilling')->andReturn(collect());
        $charges->shouldReceive('notify')->andReturnNull();
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['onboarding_confirmed' => 1, 'tenant_option' => 'new', 'tenant_type' => 'Individual', 'tenant_name' => 'Amina Kamau', 'tenant_email' => 'amina@example.test', 'tenant_phone' => '0700000000', 'tenant_id_number' => 'ID100', 'listing_type' => 'Apartment', 'complex' => 'gardens', 'residential_unit_id' => 1, 'unit_number' => 'A1', 'agreement_type' => 'rental', 'start_date' => '2026-10-26', 'monthly_payment' => 35000, 'currency' => 'KES', 'payment_interval' => 'Monthly', 'sq_area' => 100], $overrides);
    }

    private function store(array $data, array $files = [])
    {
        try {
            // Resolve the form request the way the router does: validated before the controller runs.
            $request = StoreTenancyRequest::createFrom(Request::create('/tenant_management', 'POST', $data, [], $files, ['HTTP_ACCEPT' => 'application/json']));
            $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => auth()->user());
            $request->validateResolved();
            return app(TenancyOnboardingController::class)->store($request, app(\App\Services\Tenancy\TenancyOnboardingService::class));
        } catch (HttpResponseException $exception) {
            return $exception->getResponse();
        } catch (ValidationException $exception) {
            return response()->json(['errors' => $exception->errors()], 422);
        }
    }

    public function test_success_creates_one_profile_tenancy_and_open_ended_lease_and_preserves_unit_area(): void
    {
        $response = $this->store($this->data());
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseCount('tenant_profiles', 1);
        $this->assertDatabaseCount('tenant_management', 1);
        $this->assertDatabaseCount('tenant_leases', 1);
        $this->assertDatabaseHas('complex_residential_units', ['id' => 1, 'status' => 'occupied', 'sq_ft' => 950]);
        $this->assertDatabaseHas('tenant_leases', ['business_id' => 90, 'lease_type' => 'month-to-month', 'lease_end_date' => null]);
        $this->assertNotEmpty($response->getData()->encrypted_lease_id);
    }

    public function test_rejected_property_rolls_back_the_new_profile(): void
    {
        DB::table('complexes')->where('id', 1)->update(['setup_status' => 'draft']);
        $response = $this->store($this->data());
        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseCount('tenant_profiles', 0);
        $this->assertDatabaseCount('tenant_management', 0);
    }

    public function test_billing_failure_rolls_back_profile_tenancy_and_unit_occupation(): void
    {
        $this->mock(TenantChargesService::class)->shouldReceive('startBilling')->once()->andThrow(new \RuntimeException('Billing unavailable'));
        $response = $this->store($this->data());
        $this->assertSame(500, $response->getStatusCode());
        $this->assertDatabaseCount('tenant_profiles', 0);
        $this->assertDatabaseCount('tenant_management', 0);
        $this->assertDatabaseCount('tenant_leases', 0);
        $this->assertDatabaseHas('complex_residential_units', ['id' => 1, 'status' => 'available']);
    }

    public function test_a_residential_unit_is_required_when_inventory_exists_and_occupied_units_are_rejected(): void
    {
        $this->assertSame(422, $this->store($this->data(['residential_unit_id' => null]))->getStatusCode());
        DB::table('complex_residential_units')->where('id', 1)->update(['status' => 'occupied']);
        $this->assertSame(422, $this->store($this->data())->getStatusCode());
        $this->assertDatabaseCount('tenant_profiles', 0);
    }

    public function test_occupied_or_foreign_parking_is_rejected_without_partial_writes(): void
    {
        foreach ([[1, true], [2, false]] as [$complexId, $occupied]) {
            DB::table('complex_parking_slots')->updateOrInsert(['id' => 5], ['complex_id' => $complexId, 'parking_id' => 'P5', 'is_occupied' => $occupied]);
            $this->assertSame(422, $this->store($this->data(['parking_slot' => [5]]))->getStatusCode());
            $this->assertDatabaseCount('tenant_profiles', 0);
        }
    }

    public function test_duplicate_email_in_another_business_does_not_block_onboarding(): void
    {
        DB::table('tenant_profiles')->insert(['tenant_ref' => 'OTHER', 'tenant_email' => 'amina@example.test', 'tenant_id_number' => 'OTHER', 'managed_by' => 11]);
        $response = $this->store($this->data());
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseCount('tenant_profiles', 2);
    }

    public function test_commercial_onboarding_checks_capacity_and_creates_a_lease(): void
    {
        DB::table('commercial_units')->insert(['id' => 2, 'complex_id' => 1, 'status' => 'Available', 'unit_sq_ft' => 1000, 'min_sq_ft' => 200, 'max_subdivisions' => 2]);
        $data = $this->data(['listing_type' => 'Commercial Unit', 'residential_unit_id' => null, 'commercial_unit_id' => 2, 'occupied_sq_area' => 1200, 'agreement_type' => 'lease']);
        $this->assertSame(422, $this->store($data)->getStatusCode());
        $response = $this->store(array_replace($data, ['occupied_sq_area' => 500]));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseHas('commercial_units', ['id' => 2, 'status' => 'Occupied']);
        $this->assertDatabaseHas('tenant_management', ['commercial_unit_id' => 2, 'occupied_sq_area' => 500]);
    }

    public function test_open_ended_lease_is_visible_and_searchable_by_tenant_email(): void
    {
        $this->assertSame(200, $this->store($this->data())->getStatusCode());
        $response = app(TenancyTableController::class)->getLeasesData(Request::create('/tenant_management/leases/data', 'GET', ['search' => ['value' => 'amina@example.test']]));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $data = $response->getData(true);
        $this->assertSame(1, $data['recordsTotal']);
        $this->assertSame(1, $data['recordsFiltered']);
        $this->assertStringContainsString('Open-ended', $data['data'][0]['lease_period']);
        $this->assertSame('No end date', $data['data'][0]['expiry']);
    }

    public function test_existing_profile_is_reused_and_assigned_agent_is_saved(): void
    {
        DB::table('tenant_profiles')->insert(['id' => 15, 'tenant_ref' => 'TP-EXISTING', 'tenant_name' => 'Existing Tenant', 'tenant_type' => 'Individual', 'managed_by' => 10]);
        DB::table('agents')->insert(['id' => 7, 'created_by' => 10]);
        $response = $this->store($this->data(['tenant_option' => 'existing', 'existing_tenant_id' => 15, 'assigned_agent_id' => 7]));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseCount('tenant_profiles', 1);
        $this->assertDatabaseHas('tenant_management', ['tenant_profile_id' => 15, 'assigned_agent_id' => 7]);
    }

    public function test_notification_is_sent_after_the_database_commit(): void
    {
        $this->partialMock(TenantStoreService::class)->shouldReceive('sendWelcomeEmail')->once()->andReturnUsing(function () {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertDatabaseCount('tenant_leases', 1);
        });
        $this->assertSame(200, $this->store($this->data())->getStatusCode());
    }

    public function test_confirmation_quarterly_timing_and_conditional_details_are_required(): void
    {
        foreach ([
            ['onboarding_confirmed' => null],
            ['payment_interval' => 'Quarterly'],
            ['emergency_contact_name' => 'Contact without phone'],
            ['has_pets' => 1, 'pet_details' => ''],
            ['end_date' => '2026-10-25'],
            ['kt_repeater_additional_parking_slots' => [['parking_id' => 'P1']]],
        ] as $invalid) {
            $response = $this->store($this->data($invalid));
            $this->assertSame(422, $response->getStatusCode(), $response->getContent());
            $this->assertDatabaseCount('tenant_profiles', 0);
            $this->assertDatabaseCount('tenant_management', 0);
        }
        $response = $this->store($this->data(['payment_interval' => 'Quarterly', 'tenancy_quarterly_payment_type' => 'pre_pay', 'security_deposit' => 0, 'late_fee_amount' => 0, 'penalty_amount' => 0, 'grace_period' => 0]));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseHas('tenant_leases', ['payment_frequency' => 'quarterly', 'security_deposit' => 0]);
    }

    public function test_commercial_build_out_must_leave_time_for_billing_before_agreement_ends(): void
    {
        DB::table('commercial_units')->insert(['id' => 2, 'complex_id' => 1, 'status' => 'Available', 'unit_sq_ft' => 1000, 'min_sq_ft' => 200, 'max_subdivisions' => 2]);
        $response = $this->store($this->data(['listing_type' => 'Commercial Unit', 'residential_unit_id' => null, 'commercial_unit_id' => 2, 'occupied_sq_area' => 500, 'end_date' => '2026-11-01', 'build_out_period' => 6]));
        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertArrayHasKey('build_out_period', $response->getData(true)['errors']);
        $this->assertDatabaseCount('tenant_profiles', 0);
    }

    public function test_blacklisted_or_mismatched_existing_profiles_cannot_be_onboarded(): void
    {
        DB::table('tenant_profiles')->insert(['id' => 15, 'tenant_ref' => 'TP-EXISTING', 'tenant_type' => 'Individual', 'profile_status' => 'Blacklisted', 'managed_by' => 10]);
        $data = $this->data(['tenant_option' => 'existing', 'existing_tenant_id' => 15]);
        $this->assertSame(422, $this->store($data)->getStatusCode());
        DB::table('tenant_profiles')->where('id', 15)->update(['profile_status' => 'Active', 'tenant_type' => 'Business']);
        $this->assertSame(422, $this->store($data)->getStatusCode());
        $this->assertDatabaseCount('tenant_management', 0);
    }

    public function test_saved_unit_specifications_override_submitted_values_and_preserve_studios(): void
    {
        DB::table('complex_residential_units')->where('id', 1)->update(['bedrooms' => 0, 'bathrooms' => 1]);
        $response = $this->store($this->data(['unit_number' => 'WRONG', 'bedrooms' => 5, 'bathrooms' => 7, 'sq_area' => 1]));
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseHas('tenant_management', ['unit_number' => 'A1', 'bedrooms' => 0, 'bathrooms' => 1, 'sq_area' => '950.00']);
    }

    public function test_whole_property_cannot_be_occupied_twice_and_property_type_must_match(): void
    {
        $this->assertSame(422, $this->store($this->data(['listing_type' => 'Villa']))->getStatusCode());
        DB::table('complex_residential_units')->delete();
        $this->assertSame(200, $this->store($this->data(['residential_unit_id' => null]))->getStatusCode());
        $response = $this->store($this->data(['residential_unit_id' => null, 'tenant_email' => 'second@example.test', 'tenant_id_number' => 'SECOND']));
        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertDatabaseCount('tenant_profiles', 1);
        $this->assertDatabaseCount('tenant_management', 1);
    }

    public function test_unit_picker_excludes_occupied_inventory_even_when_status_is_stale(): void
    {
        DB::table('complex_residential_units')->insert([
            ['id' => 2, 'complex_id' => 1, 'unit_number' => 'A2', 'status' => 'occupied'],
            ['id' => 3, 'complex_id' => 1, 'unit_number' => 'A3', 'status' => 'available'],
        ]);
        DB::table('tenant_management')->insert(['residential_unit_id' => 3, 'complex_id' => 1, 'status' => 'Active']);
        $data = app(PropertyLookupController::class)->getResidentialUnitsForTenant('gardens')->getData(true);
        $this->assertTrue($data['has_inventory']);
        $this->assertSame([1], array_column($data['units'], 'id'));
        $this->assertSame(422, $this->store($this->data(['residential_unit_id' => 3]))->getStatusCode());
        DB::table('complex_residential_units')->where('id', 1)->update(['status' => 'maintenance']);
        $data = app(PropertyLookupController::class)->getResidentialUnitsForTenant('gardens')->getData(true);
        $this->assertTrue($data['has_inventory']);
        $this->assertSame([], $data['units']);
    }

    public function test_unit_picker_rejects_another_business_property(): void
    {
        DB::table('complexes')->where('id', 1)->update(['user_id' => 11]);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(PropertyLookupController::class)->getResidentialUnitsForTenant('gardens');
    }

    public function test_partial_document_upload_removes_known_files_and_rolls_back_onboarding(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tenants/partial.pdf', 'partial upload');
        $this->partialMock(TenantStoreService::class)->shouldReceive('handleTenantDocumentUpload')->once()->andReturn([['path' => 'tenants/partial.pdf']]);
        $files = ['documents' => [UploadedFile::fake()->create('first.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('second.pdf', 10, 'application/pdf')]];
        $response = $this->store($this->data(), $files);
        $this->assertSame(422, $response->getStatusCode(), $response->getContent());
        $this->assertArrayHasKey('documents', $response->getData(true)['errors']);
        Storage::disk('public')->assertMissing('tenants/partial.pdf');
        $this->assertDatabaseCount('tenant_profiles', 0);
        $this->assertDatabaseCount('tenant_management', 0);
        $this->assertDatabaseCount('tenant_leases', 0);
        $this->assertDatabaseHas('complex_residential_units', ['id' => 1, 'status' => 'available']);
    }

    public function test_failed_disk_writes_or_duplicate_upload_paths_cannot_report_success(): void
    {
        Storage::fake('public');
        foreach ([[['path' => false], ['path' => 'tenants/one.pdf']], [['path' => 'tenants/one.pdf'], ['path' => 'tenants/one.pdf']]] as $result) {
            Storage::disk('public')->put('tenants/one.pdf', 'uploaded');
            $this->partialMock(TenantStoreService::class)->shouldReceive('handleTenantDocumentUpload')->once()->andReturn($result);
            $files = ['documents' => [UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('two.pdf', 10, 'application/pdf')]];
            $response = $this->store($this->data(), $files);
            $this->assertSame(422, $response->getStatusCode(), $response->getContent());
            $this->assertDatabaseCount('tenant_profiles', 0);
            $this->assertDatabaseCount('tenant_management', 0);
            Storage::disk('public')->assertMissing('tenants/one.pdf');
        }
    }
}
