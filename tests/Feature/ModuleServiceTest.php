<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleEnabled;
use App\Models\User;
use App\Services\Access\ModuleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use Tests\TestCase;

class ModuleServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'database.default' => 'module_test', 'database.connections.module_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        Cache::flush();
        Schema::create('site_sections', function (Blueprint $table) {
            $table->id(); $table->string('key')->unique(); $table->string('label'); $table->string('description')->nullable();
            $table->boolean('is_enabled')->default(true); $table->timestamps();
        });
        Schema::create('businesses', function (Blueprint $table) {
            $table->id(); $table->integer('user_id'); $table->json('modules')->nullable(); $table->timestamps();
        });
        DB::table('businesses')->insert([['user_id' => 10], ['user_id' => 20]]);
    }

    private function user(int $id, bool $subAccount = false, int $owner = 10): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'is_sub_account' => $subAccount, 'main_account_user_id' => $owner]);
        return $user;
    }

    private function modules(): ModuleService
    {
        $this->app->forgetScopedInstances();
        return app(ModuleService::class);
    }

    public function test_missing_switch_and_override_default_to_enabled(): void
    {
        $this->assertTrue($this->modules()->enabled('listings', $this->user(10)));
        $this->assertTrue($this->modules()->enabled('sales'));
    }

    public function test_installation_off_beats_business_on(): void
    {
        $modules = $this->modules();
        $modules->setForBusiness(10, 'listings', true);
        $modules->setInstallation('listings', false);
        $this->assertFalse($this->modules()->enabled('listings', $this->user(10)));
        $this->assertFalse($this->modules()->installationEnabled('listings'));
    }

    public function test_business_override_applies_only_to_that_business_and_its_sub_accounts(): void
    {
        $this->modules()->setForBusiness(10, 'listings', false);
        $modules = $this->modules();
        $this->assertFalse($modules->enabled('listings', $this->user(10)));
        $this->assertFalse($modules->enabled('listings', $this->user(11, true, 10)));
        $this->assertTrue($modules->enabled('listings', $this->user(20)));
        $this->assertTrue($modules->enabled('sales', $this->user(10)));
    }

    public function test_clearing_override_restores_installation_setting(): void
    {
        $this->modules()->setForBusiness(10, 'sales', false);
        $this->modules()->setForBusiness(10, 'sales', null);
        $this->assertTrue($this->modules()->enabled('sales', $this->user(10)));
        $this->assertNull(DB::table('businesses')->where('user_id', 10)->value('modules'));
    }

    public function test_unknown_module_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->modules()->enabled('marketing');
    }

    public function test_middleware_returns_403_json_and_redirects_html_when_disabled(): void
    {
        $this->modules()->setInstallation('listings', false);
        $this->app->forgetScopedInstances();
        $middleware = new EnsureModuleEnabled;

        $json = Request::create('/listings/data', 'POST');
        $json->headers->set('Accept', 'application/json');
        $response = $middleware->handle($json, fn () => response('ok'), 'listings');
        $this->assertSame(403, $response->getStatusCode());

        $html = Request::create('/home', 'GET');
        $response = $middleware->handle($html, fn () => response('ok'), 'listings', 'installation');
        $this->assertSame(302, $response->getStatusCode());

        $this->modules()->setInstallation('listings', true);
        $this->app->forgetScopedInstances();
        $this->assertSame('ok', $middleware->handle($html, fn () => response('ok'), 'listings', 'installation')->getContent());
    }
}
