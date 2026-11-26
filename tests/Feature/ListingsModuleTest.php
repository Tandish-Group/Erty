<?php

namespace Tests\Feature;

use App\Http\Controllers\Business\Account\BusinessModuleController;
use App\Models\{Listing, User};
use App\Services\Access\BusinessPermissionService;
use App\Services\Access\ModuleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Route, Schema};
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ListingsModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'database.default' => 'listings_test', 'database.connections.listings_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Cache::flush();
        Schema::create('site_sections', function (Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->string('label'); $t->string('description')->nullable();
            $t->boolean('is_enabled')->default(true); $t->timestamps();
        });
        Schema::create('businesses', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->json('modules')->nullable(); $t->timestamps();
        });
        Schema::create('listings', function (Blueprint $t) {
            $t->id(); $t->integer('user_id'); $t->string('slug'); $t->string('status')->default('Published'); $t->timestamps();
        });
        DB::table('businesses')->insert([['user_id' => 10], ['user_id' => 20]]);
        DB::table('listings')->insert([['user_id' => 10, 'slug' => 'a'], ['user_id' => 20, 'slug' => 'b']]);
    }

    private function user(int $id, bool $subAccount = false): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'is_sub_account' => $subAccount, 'main_account_user_id' => 10]);
        return $user;
    }

    private function fresh(): void
    {
        Cache::flush();
        $this->app->forgetScopedInstances();
    }

    private function middlewareFor(string $method, string $uri): array
    {
        app(\Illuminate\Contracts\Http\Kernel::class); // registers the middleware aliases with the router
        $route = Route::getRoutes()->match(Request::create('/' . ltrim($uri, '/'), $method));
        return Route::gatherRouteMiddleware($route);
    }

    public function test_listing_routes_are_gated_and_shared_routes_are_not(): void
    {
        foreach ([
            ['GET', 'listings'], ['GET', 'listings/1/edit'], ['PUT', 'listings/1'], ['POST', 'listings/1/publish'],
            ['POST', 'listings/1/unpublish'], ['POST', 'listings/1/photos'], ['DELETE', 'listings/1/photos/2'],
        ] as [$method, $uri]) {
            $this->assertContains(\App\Http\Middleware\EnsureModuleEnabled::class . ':listings', $this->middlewareFor($method, $uri), "$method $uri should require the Listings module");
        }

        foreach ([['GET', 'complex/1/commercial-spaces'], ['GET', 'customer_relationship/lead_management'], ['GET', 'tenant_management']] as [$method, $uri]) {
            $gathered = implode(' ', $this->middlewareFor($method, $uri));
            $this->assertStringNotContainsString('EnsureModuleEnabled:listings', $gathered, "$method $uri is a core route");
        }

        // The public home page was removed; /home only redirects to the sign-in guard.
        $home = Route::getRoutes()->match(Request::create('/home', 'GET'));
        $this->assertSame('/', $home->defaults['destination']);
    }

    public function test_sales_routes_are_gated_by_the_sales_module_and_sit_behind_the_business_checks(): void
    {
        foreach ([['GET', 'sales'], ['GET', 'sales/create'], ['POST', 'sales'], ['GET', 'sales/SAL-261118-ABCD'], ['POST', 'sales/SAL-261118-ABCD/payments'],
            ['POST', 'sales/SAL-261118-ABCD/payments/3/reverse'], ['GET', 'sales/SAL-261118-ABCD/receipts/3'], ['POST', 'sales/SAL-261118-ABCD/complete'], ['POST', 'sales/SAL-261118-ABCD/cancel']] as [$method, $uri]) {
            $gathered = $this->middlewareFor($method, $uri);
            $this->assertContains(\App\Http\Middleware\EnsureModuleEnabled::class . ':sales', $gathered, "$method $uri should require the Sales module");
            $this->assertNotEmpty(array_filter($gathered, fn ($m) => str_contains($m, 'AuthorizeBusinessRoute')), "$method $uri should go through the business access map");
        }
        $this->assertArrayHasKey('sales', \App\Services\Access\ModuleService::MODULES);
        $this->assertTrue(app(\App\Services\Access\ModuleService::class)->enabled('sales', $this->user(10)), 'on by default');
    }

    public function test_viewings_applications_and_statistics_routes_are_gone(): void
    {
        foreach ([['GET', 'listings/viewings'], ['GET', 'customer_relationship/appointments'], ['GET', 'appointments/onboarding'],
                  ['POST', 'applications/1/confirm'], ['GET', 'events'], ['GET', 'statistics']] as [$method, $uri]) {
            try {
                Route::getRoutes()->match(Request::create('/' . $uri, $method));
                $this->fail("$method $uri was removed with viewings");
            } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException|\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_marketplace_scope_hides_businesses_that_switched_listings_off(): void
    {
        $this->assertSame(['a', 'b'], Listing::onMarketplace()->orderBy('slug')->pluck('slug')->all());
        app(ModuleService::class)->setForBusiness(20, 'listings', false);
        $this->fresh();
        $this->assertSame(['a'], Listing::onMarketplace()->pluck('slug')->all());
        $this->assertFalse(Listing::where('slug', 'b')->first()->isOnMarketplace());
    }

    public function test_permission_editor_hides_listing_groups_but_keeps_stored_actions(): void
    {
        $this->actingAs($this->user(10));
        $permissions = app(BusinessPermissionService::class);
        $this->assertArrayHasKey('listings_management', $permissions->visibleDefinitions());

        app(ModuleService::class)->setInstallation('listings', false);
        $this->fresh();
        $permissions = app(BusinessPermissionService::class);
        $visible = $permissions->visibleDefinitions();
        $this->assertArrayNotHasKey('listings_management', $visible);
        $this->assertArrayHasKey('tenant_management', $visible);

        $saved = $permissions->normalize(['tenant_management' => ['view' => 'on']], ['listings_management' => ['view', 'publish'], 'tenant_management' => ['edit']]);
        $this->assertSame(['view'], $saved['tenant_management']);
        $this->assertSame(['view', 'publish'], $saved['listings_management']);
    }

    public function test_owner_can_switch_listings_off_and_back_on(): void
    {
        $this->actingAs($this->user(10));
        $request = fn (array $data) => tap(Request::create('/business/modules', 'POST', $data), fn ($r) => $r->setUserResolver(fn () => auth()->user()));

        $response = app(BusinessModuleController::class)->update($request(['module' => 'listings', 'enabled' => 0]), app(ModuleService::class));
        $this->assertTrue($response->getData(true)['success']);
        $this->fresh();
        $this->assertFalse(app(ModuleService::class)->enabled('listings', $this->user(10)));

        app(BusinessModuleController::class)->update($request(['module' => 'listings', 'enabled' => 1]), app(ModuleService::class));
        $this->fresh();
        $this->assertTrue(app(ModuleService::class)->enabled('listings', $this->user(10)));
        $this->assertNull(DB::table('businesses')->where('user_id', 10)->value('modules'));
    }

    public function test_owner_can_toggle_sales_but_sub_accounts_and_unknown_modules_cannot(): void
    {
        $this->actingAs($this->user(10));
        $request = fn (array $data) => tap(Request::create('/business/modules', 'POST', $data), fn ($r) => $r->setUserResolver(fn () => auth()->user()));

        app(BusinessModuleController::class)->update($request(['module' => 'sales', 'enabled' => 0]), app(ModuleService::class));
        $this->fresh();
        $this->assertFalse(app(ModuleService::class)->enabled('sales', $this->user(10)));
        $this->assertTrue(app(ModuleService::class)->enabled('sales', $this->user(20)));

        app(BusinessModuleController::class)->update($request(['module' => 'sales', 'enabled' => 1]), app(ModuleService::class));
        $this->fresh();
        $this->assertTrue(app(ModuleService::class)->enabled('sales', $this->user(10)));
        $this->assertNull(DB::table('businesses')->where('user_id', 10)->value('modules'));

        try { app(BusinessModuleController::class)->update($request(['module' => 'subscriptions', 'enabled' => 0]), app(ModuleService::class)); $this->fail('Unknown module'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('module', $e->errors()); }

        $this->actingAs($this->user(11, true));
        $request = Request::create('/business/modules', 'POST', ['module' => 'listings', 'enabled' => 0]);
        $request->setUserResolver(fn () => auth()->user());
        try { app(BusinessModuleController::class)->update($request, app(ModuleService::class)); $this->fail('Sub-accounts cannot change modules'); }
        catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }
}
