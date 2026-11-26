<?php

namespace Tests\Feature;

use App\Services\Access\BusinessAccessResolver;
use App\Services\Access\BusinessPermissionService;
use App\Services\Access\PermissionCatalogue;
use App\Services\Access\StarterRoles;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The role editor tells the truth: every tick box is explained and actually allows something, every area
 * sits under a heading that matches the left menu, and an action always comes with the "view" it needs.
 */
class PermissionCatalogueTest extends TestCase
{
    private function catalogue()
    {
        return collect(config('business_route_permissions'))->keyBy('group');
    }

    public function test_every_action_is_explained_in_the_role_editor(): void
    {
        foreach ($this->catalogue() as $group => $definition) {
            $this->assertNotEmpty($definition['description'] ?? null, "{$group} has a description");
            foreach ($definition['permissions'] as $action) {
                $this->assertNotEmpty($definition['help'][$action] ?? null, "{$group}.{$action} says what it allows");
            }
            $this->assertEmpty(array_diff(array_keys($definition['help'] ?? []), $definition['permissions']), "{$group} explains only its own actions");
        }
    }

    public function test_every_action_allows_something(): void
    {
        $resolver = app(BusinessAccessResolver::class);
        $routed = [];
        foreach (Route::getRoutes() as $route) {
            if (in_array('business.access', $route->gatherMiddleware(), true) && ($rule = $resolver->ruleFor($route)) && $rule['type'] === 'permission') {
                $routed["{$rule['group']}.{$rule['action']}"] = true;
            }
        }
        $code = '';
        foreach ([app_path(), resource_path('views')] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
                if (str_ends_with($file, '.php')) $code .= file_get_contents($file);
            }
        }

        foreach ($this->catalogue() as $group => $definition) {
            foreach ($definition['permissions'] as $action) {
                $checked = isset($routed["{$group}.{$action}"])
                    || preg_match('~[\'"]' . preg_quote($group) . '[\'"]\s*,\s*[\'"]' . preg_quote($action) . '[\'"]~', $code)
                    || str_contains($code, "'{$group}.{$action}'")
                    || ($group === 'expense_management' && preg_match("~'{$action}'~", file_get_contents(app_path('Http/Controllers/Business/Expenses/PropertyExpenseWorkflowController.php'))));
                $this->assertTrue((bool) $checked, "{$group}.{$action} is enforced by a route or a check in code; otherwise remove it from the role editor");
            }
        }
    }

    public function test_every_area_sits_under_one_menu_heading(): void
    {
        $groups = $this->catalogue()->keys();
        $placed = collect(config('business_sections'))->flatMap(fn ($s) => $s['groups']);
        $this->assertSame($placed->count(), $placed->unique()->count(), 'an area is listed under one heading only');
        $this->assertEqualsCanonicalizing($groups->all(), $placed->all(), 'every area appears in the role editor, and only real ones');

        foreach (config('business_sections') as $key => $section) {
            foreach ($section['shows'] as $right) {
                [$group, $action] = explode('.', $right);
                $this->assertContains($action, $this->catalogue()[$group]['permissions'] ?? [], "{$key}: {$right} exists");
            }
        }
    }

    public function test_an_action_brings_the_view_it_needs(): void
    {
        $saved = app(BusinessPermissionService::class)->normalize([
            'tenant_management' => ['payments' => 'on'],
            'maintenance_management' => ['manage' => 'on'],
            'reports' => ['export' => 'on'],
        ]);
        $this->assertSame(['view', 'payments'], $saved['tenant_management']);
        $this->assertSame(['view', 'manage'], $saved['maintenance_management']);
        $this->assertSame(['view', 'export'], $saved['reports']);

        foreach (StarterRoles::definitions() as $name => [, , $spec]) {
            $permissions = StarterRoles::permissions($spec);
            $this->assertSame($permissions, PermissionCatalogue::withRequirements($permissions), "{$name} already holds the views its actions need");
        }
    }

    public function test_existing_roles_keep_what_they_could_do_when_maintenance_gets_its_own_rights(): void
    {
        config(['database.default' => 'catalogue_test', 'database.connections.catalogue_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('business_roles', fn (Blueprint $t) => $t->id() && $t->json('permissions')->nullable());
        Schema::create('users', fn (Blueprint $t) => $t->id() && $t->json('business_permissions')->nullable());
        DB::table('business_roles')->insert([
            ['id' => 1, 'permissions' => json_encode(['complex_management' => ['view', 'edit'], 'listings_management' => ['view', 'create', 'delete']])],
            ['id' => 2, 'permissions' => json_encode(['complex_management' => ['view'], 'tenant_management' => ['view', 'create'], 'commercial_units' => ['view']])],
            ['id' => 3, 'permissions' => json_encode(['tenant_management' => ['view']])],
        ]);
        $migration = require database_path('migrations/2026_11_26_000004_permission_catalogue_cleanup.php');
        $migration->up();
        $migration->up(); // running it again changes nothing

        $roles = DB::table('business_roles')->pluck('permissions', 'id')->map(fn ($p) => json_decode($p, true));
        $this->assertSame(['view', 'manage'], $roles[1]['maintenance_management'], 'property editors managed repairs');
        $this->assertSame(['view'], $roles[1]['listings_management'], 'actions that allowed nothing are removed');
        $this->assertSame(['view', 'log'], $roles[2]['maintenance_management'], 'onboarding tenants allowed logging repairs');
        $this->assertArrayNotHasKey('commercial_units', $roles[2]);
        $this->assertArrayNotHasKey('maintenance_management', $roles[3], 'no property access, no repairs');
    }

    public function test_the_menu_preview_follows_the_permissions(): void
    {
        $catalogue = app(PermissionCatalogue::class);
        $this->assertSame(['Home', 'Tenants', 'Maintenance', 'Reports'], $catalogue->menuFor([
            'dashboard_access' => ['view'], 'tenant_management' => ['view'], 'maintenance_management' => ['view', 'log'], 'reports' => ['occupancy'],
        ]));
        $this->assertSame([], $catalogue->menuFor(['tenant_management' => []]));
    }
}
