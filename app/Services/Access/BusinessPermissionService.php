<?php

namespace App\Services\Access;

use App\Models\User;

class BusinessPermissionService
{
    /**
     * Keep submitted permissions inside the configured business capability map.
     * The UI may submit group checkboxes as scalar values; only selected actions
     * are persisted because actions are what workflow gates enforce.
     */
    public function normalize(?array $permissions, ?array $existing = null): array
    {
        $normalized = $this->normalizeSubmitted($permissions);

        // Groups hidden because their optional module is off are not in the form:
        // keep their previously stored actions instead of silently removing them.
        if ($existing) {
            $visible = array_column($this->visibleDefinitions(), 'group');
            foreach ($existing as $group => $actions) {
                if (!in_array($group, $visible, true) && is_array($actions) && !isset($normalized[$group])) {
                    $normalized[$group] = array_values($actions);
                }
            }
        }

        // An action is no use without seeing the area it is in: add the "view" it needs.
        return PermissionCatalogue::withRequirements($normalized);
    }

    private function normalizeSubmitted(?array $permissions): array
    {
        if (!$permissions) {
            return [];
        }

        $allowedGroups = collect(config('business_route_permissions', []))
            ->mapWithKeys(fn (array $definition) => [
                $definition['group'] => $definition['permissions'] ?? [],
            ]);

        $normalized = [];

        foreach ($permissions as $group => $actions) {
            if (!is_array($actions) || !$allowedGroups->has($group)) {
                continue;
            }

            $selectedActions = array_values(array_intersect(
                array_keys($actions),
                $allowedGroups->get($group, [])
            ));

            if ($selectedActions) {
                $normalized[$group] = $selectedActions;
            }
        }

        return $normalized;
    }

    /**
     * Permission groups to offer in role/sub-account editors: groups that belong to an
     * optional module (`module` key) are hidden while that module is off. Stored
     * permissions are left untouched so switching the module back on restores them.
     */
    public function visibleDefinitions(?User $user = null): array
    {
        $modules = app(ModuleService::class);

        return collect(config('business_route_permissions', []))
            ->filter(fn (array $definition) => empty($definition['hidden']))
            ->filter(fn (array $definition) => empty($definition['module']) || $modules->enabled($definition['module'], $user))
            ->all();
    }

    public function can(User $user, string $group, string $action): bool
    {
        if (!$user->is_sub_account) {
            return true;
        }

        if ($user->businessRole && !$user->businessRole->is_active) {
            return false;
        }

        $permissions = $user->business_permissions;

        if ($permissions === null && $user->businessRole) {
            $permissions = $user->businessRole->permissions;
        }

        return in_array($action, $permissions[$group] ?? [], true);
    }

    /** True when every action in $permissions is also in $allowed ('*' => ['*'] allows everything). */
    public function isSubset(array $permissions, array $allowed): bool
    {
        if (($allowed['*'] ?? null) === ['*']) {
            return true;
        }
        foreach ($permissions as $group => $actions) {
            if (array_diff((array) $actions, $allowed[$group] ?? [])) {
                return false;
            }
        }
        return true;
    }

    public function permissionsFor(User $user): array
    {
        if (!$user->is_sub_account) {
            return ['*' => ['*']];
        }

        if ($user->business_permissions !== null) {
            return $user->business_permissions;
        }

        return $user->businessRole?->permissions ?? [];
    }
}
