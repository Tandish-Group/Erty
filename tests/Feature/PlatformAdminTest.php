<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Platform\PlatformController;
use App\Http\Middleware\{EnsurePasswordChanged, EnsurePlatformAdmin};
use App\Models\User;
use App\Services\Access\ModuleService;
use App\Services\Access\StarterRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, DB, Hash, Route, Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Platform administration for the single business: only "admin" users get in; it lists the staff
 * with their sign-in state, restores people who are locked out (only of this business), switches
 * modules and adds missing standard roles. Also the temporary administrator: seeded with a random
 * password that must be changed before anything else.
 */
class PlatformAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'database.default' => 'platform_test', 'database.connections.platform_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        app()->forgetScopedInstances();
        Cache::flush();

        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->string('full_name_slug')->nullable(); $t->string('email')->unique(); $t->string('mobile')->nullable();
            $t->string('password')->nullable(); $t->boolean('must_change_password')->default(false); $t->string('remember_token')->nullable();
            $t->string('account_type')->nullable(); $t->boolean('is_suspended')->default(false); $t->integer('incorrect_attempts')->default(0);
            $t->boolean('is_sub_account')->default(false); $t->unsignedBigInteger('main_account_user_id')->nullable(); $t->unsignedBigInteger('business_role_id')->nullable();
            $t->json('business_permissions')->nullable(); $t->timestamp('last_login_at')->nullable(); $t->timestamp('invited_at')->nullable(); $t->timestamp('invitation_accepted_at')->nullable(); $t->timestamps();
        });
        foreach (['roles', 'permissions'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->timestamps(); });
        }
        Schema::create('model_has_roles', function (Blueprint $t) { $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create('model_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id'); });
        Schema::create('role_has_permissions', function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id'); });
        Schema::create('businesses', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('user_id'); $t->string('business_name')->nullable(); $t->string('business_email')->nullable(); $t->string('account_type')->nullable();
            $t->json('modules')->nullable(); $t->timestamp('starter_roles_seeded_at')->nullable(); $t->json('starter_roles_added')->nullable(); $t->timestamps();
        });
        Schema::create('business_roles', function (Blueprint $t) {
            $t->id(); $t->integer('business_id'); $t->integer('user_id'); $t->string('name'); $t->string('department', 60)->nullable(); $t->text('description')->nullable(); $t->string('color')->nullable();
            $t->boolean('is_active')->default(true); $t->json('permissions')->nullable(); $t->string('property_scope')->default('all'); $t->timestamps();
        });
        Schema::create('site_sections', function (Blueprint $t) { $t->id(); $t->string('key')->unique(); $t->string('label')->nullable(); $t->string('description')->nullable(); $t->boolean('is_enabled')->default(true); $t->timestamps(); });

        DB::table('roles')->insert([['id' => 1, 'name' => 'business', 'guard_name' => 'web'], ['id' => 2, 'name' => 'admin', 'guard_name' => 'web']]);
        DB::table('users')->insert([
            ['id' => 4, 'first_name' => 'Owner', 'email' => 'owner@acme.test', 'is_sub_account' => false, 'main_account_user_id' => null, 'is_suspended' => false],
            ['id' => 5, 'first_name' => 'Locked', 'email' => 'locked@acme.test', 'is_sub_account' => true, 'main_account_user_id' => 4, 'is_suspended' => true],
            ['id' => 6, 'first_name' => 'Stranger', 'email' => 'someone@else.test', 'is_sub_account' => true, 'main_account_user_id' => 99, 'is_suspended' => true],
        ]);
        DB::table('model_has_roles')->insert([['role_id' => 1, 'model_type' => User::class, 'model_id' => 4], ['role_id' => 2, 'model_type' => User::class, 'model_id' => 4]]);
        DB::table('businesses')->insert(['user_id' => 4, 'business_name' => 'Acme Estates']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function as(int $id): User
    {
        $user = User::findOrFail($id);
        $this->actingAs($user);
        return $user;
    }

    public function test_only_platform_admins_get_in(): void
    {
        $through = fn (User $user) => app(EnsurePlatformAdmin::class)->handle(tap(Request::create('/platform'), fn ($r) => $r->setUserResolver(fn () => $user)), fn () => response('in'))->getContent();

        $this->assertSame('in', $through($this->as(4)));
        try { $through($this->as(5)); $this->fail('a team member got in'); }
        catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
    }

    public function test_restoring_only_works_for_people_of_this_business(): void
    {
        $this->as(4);
        app(PlatformController::class)->restorePerson(5);
        $this->assertFalse((bool) User::find(5)->is_suspended);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(PlatformController::class)->restorePerson(6);
    }

    public function test_a_module_switch_sets_the_business_and_the_installation_together(): void
    {
        $this->as(4);
        app(ModuleService::class)->setForBusiness(4, 'listings', false);

        app(PlatformController::class)->module(Request::create('/', 'POST', ['module' => 'listings', 'enabled' => 1]));
        Cache::flush(); app()->forgetScopedInstances();

        $this->assertTrue(app(ModuleService::class)->installationEnabled('listings'));
        $this->assertNull(app(ModuleService::class)->businessOverride(4, 'listings'), 'no conflicting business setting is left behind');
        $this->assertTrue(app(ModuleService::class)->enabled('listings', User::find(4)));
    }

    public function test_missing_standard_roles_can_be_added(): void
    {
        $this->as(4);
        app(PlatformController::class)->seedRoles(app(StarterRoles::class));
        $this->assertSame(count(StarterRoles::definitions()), DB::table('business_roles')->where('business_id', 4)->count());
    }

    // ─── Temporary administrator ─────────────────────────────────────────────

    public function test_a_temporary_password_must_be_changed_before_anything_else(): void
    {
        DB::table('users')->where('id', 4)->update(['must_change_password' => true, 'password' => Hash::make('Temp-pass-123456')]);
        $user = $this->as(4);
        $middleware = fn (string $uri) => app(EnsurePasswordChanged::class)->handle(
            tap(Request::create($uri), function ($r) use ($user) { $r->setUserResolver(fn () => $user); $r->setRouteResolver(fn () => Route::getRoutes()->match($r)); }),
            fn () => response('through'));

        $this->assertSame(route('password.change'), $middleware('/dashboard')->headers->get('Location'));
        $this->assertSame('through', $middleware('/password/change')->getContent());

        $change = function (string $password) {
            $request = Request::create('/password/change', 'POST', ['password' => $password, 'password_confirmation' => $password]);
            $request->setLaravelSession(app('session.store'));
            $request->setUserResolver(fn () => auth()->user());
            return app(ChangePasswordController::class)->update($request);
        };

        foreach (['short1A', 'alllowercase123', 'Temp-pass-123456'] as $weakOrSame) {
            try { $change($weakOrSame); $this->fail("accepted {$weakOrSame}"); }
            catch (ValidationException $e) { $this->assertArrayHasKey('password', $e->errors()); }
        }

        $change('A-much-better-Passw0rd');
        $user = User::find(4);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('A-much-better-Passw0rd', $user->password));
        $this->assertSame('through', $middleware('/dashboard')->getContent());
    }
}
