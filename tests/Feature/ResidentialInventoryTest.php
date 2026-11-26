<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Properties\ResidentialInventoryController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ResidentialInventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Dedicated ephemeral connection; never migrate or write to the restored database.
        config(['database.default' => 'inventory_test', 'database.connections.inventory_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Schema::create('complexes', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->string('title'); $table->string('slug'); $table->string('type');
        });
        Schema::create('complex_residential_units', function (Blueprint $table) {
            $table->id(); $table->integer('complex_id'); $table->string('unit_number'); $table->string('status');
        });
        Schema::create('tenant_management', function (Blueprint $table) {
            $table->id(); $table->integer('residential_unit_id'); $table->string('status'); $table->integer('tenant_profile_id')->nullable();
        });
        DB::table('complexes')->insert([
            ['id' => 1, 'user_id' => 10, 'title' => 'Our property', 'slug' => 'ours', 'type' => 'Apartment'],
            ['id' => 2, 'user_id' => 20, 'title' => 'Other property', 'slug' => 'theirs', 'type' => 'Apartment'],
        ]);
        DB::table('complex_residential_units')->insert([
            ['id' => 1, 'complex_id' => 1, 'unit_number' => 'R01', 'status' => 'Available'],
            ['id' => 2, 'complex_id' => 2, 'unit_number' => 'R02', 'status' => 'available'],
        ]);
    }

    private function requestAs(array $filters = [], bool $employee = false, array $permissions = []): Request
    {
        $user = new User;
        $user->forceFill(['id' => $employee ? 11 : 10, 'is_sub_account' => $employee,
            'main_account_user_id' => 10, 'business_permissions' => $permissions]);
        $user->setRelation('businessRole', null);
        $request = Request::create('/residential-units', 'GET', $filters);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_owner_sees_only_their_units_and_case_insensitive_status_filter(): void
    {
        $data = (new ResidentialInventoryController)->index($this->requestAs(['status' => 'available', 'q' => 'r01']))->getData();
        $this->assertSame([1], $data['units']->pluck('id')->all());
        $this->assertSame(25, $data['units']->perPage());
        $this->assertSame([1], $data['properties']->pluck('id')->all());
    }

    public function test_employee_uses_parent_portfolio_and_does_not_gain_edit_access(): void
    {
        $data = (new ResidentialInventoryController)->index($this->requestAs([], true, ['complex_management' => ['view']]))->getData();
        $this->assertSame([1], $data['units']->pluck('id')->all());
        $this->assertFalse($data['canEdit']);
    }

    public function test_foreign_property_is_not_accessible(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);
        try {
            (new ResidentialInventoryController)->index($this->requestAs(['complex_id' => 2]));
        } catch (HttpException $error) {
            $this->assertSame(404, $error->getStatusCode());
            throw $error;
        }
    }

    public function test_employee_without_view_permission_is_denied(): void
    {
        try {
            (new ResidentialInventoryController)->index($this->requestAs([], true));
            $this->fail('Expected permission denial');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
    }

    public function test_large_inventory_is_paginated_and_only_active_tenancies_are_loaded(): void
    {
        for ($id = 3; $id <= 31; $id++) {
            DB::table('complex_residential_units')->insert(['id' => $id, 'complex_id' => 1, 'unit_number' => 'R'.str_pad((string) $id, 2, '0', STR_PAD_LEFT), 'status' => 'available']);
        }
        DB::table('tenant_management')->insert([
            ['id' => 1, 'residential_unit_id' => 1, 'status' => 'Terminated'],
            ['id' => 2, 'residential_unit_id' => 1, 'status' => 'Active'],
        ]);
        $data = (new ResidentialInventoryController)->index($this->requestAs())->getData();
        $this->assertSame(30, $data['units']->total());
        $this->assertCount(25, $data['units']);
        $this->assertSame([2], $data['units']->first()->activeTenancies->pluck('id')->all());
    }

    private function prepareRefinement(): array
    {
        Schema::table('complexes', function (Blueprint $table) {
            $table->string('setup_status')->nullable(); $table->timestamp('setup_confirmed_at')->nullable();
            $table->integer('setup_confirmed_by')->nullable(); $table->timestamps();
        });
        Schema::table('complex_residential_units', function (Blueprint $table) {
            $table->integer('floor')->nullable(); $table->decimal('sq_ft')->nullable(); $table->timestamps();
        });
        foreach (['complex_units', 'commercial_units', 'complex_amenities'] as $name) {
            Schema::create($name, function (Blueprint $table) { $table->id(); $table->integer('complex_id'); });
        }
        Schema::create('complex_changes', function (Blueprint $table) {
            $table->id(); $table->integer('complex_id'); $table->string('field_name');
            $table->text('old_value')->nullable(); $table->text('new_value');
            $table->integer('user_id'); $table->string('user_ip')->nullable(); $table->timestamps();
        });
        $request = $this->requestAs();
        $this->actingAs($request->user());
        $step = ['key' => 'residential', 'confirmed' => false, 'fingerprint' => str_repeat('a', 64),
            'data' => [['id' => 1, 'unit_number' => 'R01', 'floor' => 0, 'sq_ft' => 500]]];
        $this->mock(\App\Services\Properties\ComplexSetupService::class, fn ($mock) => $mock->shouldReceive('steps')->andReturn(['residential' => $step]));
        return $step;
    }

    public function test_refine_link_opens_one_focused_unit_instead_of_entire_setup(): void
    {
        $this->prepareRefinement();
        $request = $this->requestAs(['step' => 'residential', 'unit' => 1]);
        $controller = new \App\Http\Controllers\Business\Properties\ComplexController();
        $view = $controller->setup($request, 'ours');
        $this->assertSame('pages.business.complex.refine-residential', $view->name());
        $this->assertSame(1, $view->getData()['unit']['id']);
        $this->assertTrue($view->getData()['canEdit']);
    }

    public function test_focused_save_returns_to_inventory_and_retains_change_audit(): void
    {
        $step = $this->prepareRefinement();
        $request = Request::create('/complex/ours/setup/residential/1', 'POST', [
            'unit_number' => 'R01-updated', 'floor' => 2, 'sq_ft' => 650,
            'fingerprint' => $step['fingerprint'], 'return_to' => 'inventory',
        ]);
        $response = (new \App\Http\Controllers\Business\Properties\ComplexWorkflowController)->refineResidential($request, 'ours', 1);
        $this->assertSame(route('residential-units.index', ['complex_id' => 1]), $response->getTargetUrl());
        $this->assertSame('R01-updated', DB::table('complex_residential_units')->where('id', 1)->value('unit_number'));
        $this->assertSame('draft', DB::table('complexes')->where('id', 1)->value('setup_status'));
        $this->assertSame(1, DB::table('complex_changes')->count());
    }
}
