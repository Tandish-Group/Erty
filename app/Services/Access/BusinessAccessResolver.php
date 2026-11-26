<?php

namespace App\Services\Access;

use App\Models\User;
use Illuminate\Routing\Route;

/**
 * Resolves a business dashboard route to the access rule that guards it
 * (see config/business_access.php) and decides whether a user may use it.
 */
class BusinessAccessResolver
{
    public const ANY_STAFF = 'any_staff';
    public const OWNER_ONLY = 'owner_only';

    public function __construct(private BusinessPermissionService $permissions)
    {
    }

    /**
     * @return array{type: string, group?: string, action?: string}|null  null = unmapped
     */
    public function ruleFor(Route $route): ?array
    {
        [$controller, $method] = $this->controllerAndMethod($route);
        $config = config('business_access');
        $rule = $config['routes']["{$controller}@{$method}"] ?? $config['controllers'][$controller] ?? null;

        if ($rule === null) {
            return null;
        }
        if (in_array($rule, [self::ANY_STAFF, self::OWNER_ONLY], true)) {
            return ['type' => $rule];
        }

        [$group, $action] = array_pad(explode('.', $rule, 2), 2, null);
        $action ??= $this->inferAction($group, $method, $route->methods());

        return ['type' => 'permission', 'group' => $group, 'action' => $action];
    }

    public function allows(User $user, array $rule): bool
    {
        if ($user->isBusinessOwner()) {
            return true;
        }

        return match ($rule['type']) {
            self::ANY_STAFF => true,
            self::OWNER_ONLY => false,
            default => $this->permissions->can($user, $rule['group'], $rule['action']),
        };
    }

    /** First section URL a team member may open (used when the dashboard is not permitted). */
    public function landingUrl(User $user): ?string
    {
        foreach (config('business_access.landing', []) as $group => $url) {
            if ($this->permissions->can($user, $group, 'view')) {
                return $url;
            }
        }
        return config('business_access.fallback_landing');
    }

    /** Group display name for messages. */
    public function label(string $group): string
    {
        foreach (config('business_route_permissions', []) as $definition) {
            if (($definition['group'] ?? null) === $group) {
                return $definition['display_name'] ?? $group;
            }
        }
        return $group;
    }

    private function controllerAndMethod(Route $route): array
    {
        $action = $route->getActionName();
        if ($action === 'Closure') {
            return ['Closure', '__invoke'];
        }
        [$class, $method] = array_pad(explode('@', $action, 2), 2, '__invoke');

        return [str_replace('App\\Http\\Controllers\\', '', ltrim($class, '\\')), $method];
    }

    /**
     * Infer the permission action from the controller method (resource verbs first, then name hints, then HTTP verb),
     * falling back to the closest action the group defines.
     */
    public function inferAction(string $group, string $method, array $httpMethods): string
    {
        $name = strtolower($method);
        $candidates = match (true) {
            in_array($name, ['index', 'show', 'view'], true) => ['view'],
            in_array($name, ['create', 'store'], true) => ['create'],
            in_array($name, ['edit', 'update'], true) => ['edit'],
            in_array($name, ['destroy', 'delete'], true) || str_contains($name, 'delete') || str_contains($name, 'destroy') => ['delete'],
            str_contains($name, 'export') => ['export', 'view'],
            str_contains($name, 'import') || str_contains($name, 'upload') => ['import', 'create'],
            str_contains($name, 'download') => ['download', 'export', 'view'],
            str_contains($name, 'approve') || str_contains($name, 'reject') => ['approve', 'edit'],
            default => array_intersect(array_map('strtoupper', $httpMethods), ['GET', 'HEAD']) ? ['view'] : ['edit', 'create'],
        };

        $defined = $this->actionsFor($group);
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $defined, true)) {
                return $candidate;
            }
        }
        return $candidates[0];
    }

    public function actionsFor(string $group): array
    {
        foreach (config('business_route_permissions', []) as $definition) {
            if (($definition['group'] ?? null) === $group) {
                return $definition['permissions'] ?? [];
            }
        }
        return [];
    }
}
