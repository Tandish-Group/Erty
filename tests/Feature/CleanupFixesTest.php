<?php

namespace Tests\Feature;

use App\Http\Controllers\Support\SupportController;
use App\Http\Controllers\Business\Tenancies\TenantLeaseController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Route, Schema, Storage};
use Tests\TestCase;

/**
 * Fixes found during the open-source cleanup: support tickets need a signed-in owner, lease forms post to the
 * real routes and saved leases belong to the business, and no route points at a method that does not exist.
 */
class CleanupFixesTest extends TestCase
{
    use \Tests\Concerns\TenantAccountsSchema;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'cleanup_test', 'database.connections.cleanup_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        $this->schema();
        Schema::create('supports', function (Blueprint $t) { $t->id(); $t->string('topic'); $t->timestamps(); });
        Schema::create('support_tickets', function (Blueprint $t) { $t->id(); $t->integer('support_id')->nullable(); $t->integer('user_id')->nullable(); $t->integer('custom_user_id')->nullable(); $t->text('description')->nullable(); $t->string('custom_topic')->nullable(); $t->string('media_path')->nullable(); $t->boolean('has_attachments')->default(false); $t->timestamps(); });
        DB::table('users')->insert([['id' => 10, 'first_name' => 'Owner'], ['id' => 20, 'first_name' => 'Other']]);
        DB::table('businesses')->insert([['id' => 1, 'user_id' => 10, 'business_name' => 'Erty Homes'], ['id' => 2, 'user_id' => 20, 'business_name' => 'Other Co']]);
        DB::table('supports')->insert(['id' => 1, 'topic' => 'Billing']);
        DB::table('complexes')->insert(['id' => 1, 'user_id' => 10, 'title' => 'Riverside']);
        DB::table('complex_residential_units')->insert(['id' => 1, 'complex_id' => 1, 'unit_number' => 'A-101', 'status' => 'occupied']);
        DB::table('tenant_profiles')->insert(['id' => 1, 'tenant_name' => 'Amani', 'managed_by' => 10]);
    }

    public function test_support_routes_require_sign_in(): void
    {
        foreach ([['GET', 'support'], ['POST', 'support'], ['POST', 'support-upload-files']] as [$method, $uri]) {
            $route = Route::getRoutes()->match(Request::create('/' . $uri, $method));
            $this->assertContains('auth', $route->gatherMiddleware(), "$method /$uri must require sign-in");
        }
    }

    public function test_a_new_lease_belongs_to_the_business(): void
    {
        $this->tenancy();
        $this->actingAs(User::find(10));
        $response = (new TenantLeaseController)->store(Request::create('/tenant_management/lease/store', 'POST', [
            'tenant_management_id' => 1, 'lease_start_date' => '2026-12-01', 'lease_end_date' => '2027-11-30', 'rent_amount' => 35000,
            'currency' => 'KES', 'payment_frequency' => 'monthly', 'payment_day_of_month' => 1, 'lease_type' => 'fixed-term',
        ]));
        $this->assertSame(200, $response->getStatusCode(), json_encode($response->getData()));
        $this->assertSame(1, (int) DB::table('tenant_leases')->value('business_id'));
    }

    public function test_lease_forms_post_to_registered_routes(): void
    {
        $this->assertSame('/tenant_management/lease/store', parse_url(route('tenant_lease.store'), PHP_URL_PATH));
        $this->assertSame('/tenant_management/lease/update/5', parse_url(route('tenant_lease.update', 5), PHP_URL_PATH));
        $this->assertSame('/tenant_management', parse_url(route('tenant_management.index'), PHP_URL_PATH));
    }

    public function test_every_route_points_at_a_method_that_exists(): void
    {
        $missing = [];
        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();
            if (!str_contains($action, '@')) continue;
            [$class, $method] = explode('@', $action);
            if (!class_exists($class) || !method_exists($class, $method)) $missing[] = $route->uri() . ' → ' . $action;
        }
        $this->assertSame([], $missing);
    }

    public function test_route_names_are_unique_so_routes_can_be_cached(): void
    {
        $names = collect(Route::getRoutes()->getRoutes())->map->getName()->filter();
        $this->assertSame([], $names->duplicates()->values()->all());
    }

    public function test_lead_links_open_the_page_that_loads_the_lead_script(): void
    {
        $this->assertSame('/customer_relationship/lead_management/lead/abc', parse_url(route('lead_management.show', 'abc'), PHP_URL_PATH));
    }

    public function test_read_only_endpoints_called_by_post_need_only_view_access(): void
    {
        $resolver = app(\App\Services\Access\BusinessAccessResolver::class);
        $wrong = [];
        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true) || !str_contains($route->getActionName(), '@')) continue;
            if (!str_starts_with(explode('@', $route->getActionName())[1], 'get')) continue;
            $rule = $resolver->ruleFor($route);
            if (($rule['type'] ?? null) === 'permission' && $rule['action'] !== 'view') $wrong[] = $route->uri() . ' → ' . $rule['group'] . '.' . $rule['action'];
        }
        $this->assertSame([], $wrong);
    }
}
