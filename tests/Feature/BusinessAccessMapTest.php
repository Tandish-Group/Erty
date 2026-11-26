<?php

namespace Tests\Feature;

use App\Services\Access\BusinessAccessResolver;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every business dashboard route must resolve to an access rule, and every permission rule must use a
 * group and action defined in config/business_route_permissions.php. New routes fail here until mapped
 * in config/business_access.php, so team members are never silently granted or refused a page.
 */
class BusinessAccessMapTest extends TestCase
{
    private function businessRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('business.access', $route->gatherMiddleware(), true))
            ->values()->all();
    }

    public function test_business_routes_are_guarded_by_the_access_middleware(): void
    {
        $unguarded = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => collect($route->gatherMiddleware())->contains(fn ($m) => is_string($m) && str_starts_with($m, 'role:business')))
            ->reject(fn ($route) => in_array('business.access', $route->gatherMiddleware(), true))
            ->map(fn ($route) => implode('|', $route->methods()) . ' ' . $route->uri())
            ->values()->all();

        $this->assertSame([], $unguarded);
        $this->assertGreaterThan(250, count($this->businessRoutes()), "sanity check that business routes were found");
    }

    public function test_every_business_route_maps_to_a_defined_rule(): void
    {
        $resolver = app(BusinessAccessResolver::class);
        $groups = collect(config('business_route_permissions'))->keyBy('group')->map(fn ($d) => $d['permissions'] ?? []);
        $problems = [];

        foreach ($this->businessRoutes() as $route) {
            $label = implode('|', $route->methods()) . ' ' . $route->uri() . ' → ' . $route->getActionName();
            $rule = $resolver->ruleFor($route);
            if ($rule === null) {
                $problems[] = 'UNMAPPED ' . $label;
                continue;
            }
            if ($rule['type'] !== 'permission') {
                continue;
            }
            if (!$groups->has($rule['group'])) {
                $problems[] = "UNKNOWN GROUP {$rule['group']} for {$label}";
            } elseif (!in_array($rule['action'], $groups[$rule['group']], true)) {
                $problems[] = "UNKNOWN ACTION {$rule['group']}.{$rule['action']} for {$label}";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_every_access_map_key_names_a_real_controller_and_method(): void
    {
        $stale = [];
        foreach (['routes', 'controllers'] as $section) {
            foreach (array_keys(config("business_access.$section", [])) as $key) {
                [$class, $method] = array_pad(explode('@', $key), 2, null);
                if (in_array($class, ['Closure', 'Illuminate\\Routing\\RedirectController'], true)) continue;
                $fqcn = 'App\\Http\\Controllers\\' . $class;
                if (!class_exists($fqcn) || ($method && !method_exists($fqcn, $method))) $stale[] = $key;
            }
        }
        $this->assertSame([], $stale);
    }
}
