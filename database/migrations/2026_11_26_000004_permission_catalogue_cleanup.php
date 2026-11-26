<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permissions now say exactly what they allow. Existing roles (and people with their own permissions) keep
 * exactly what they could do before:
 * - Maintenance has its own "view": it was opened with Properties: view, so those roles get it.
 * - Managing repairs was also allowed by Properties: edit, and logging them by Tenants: add; those roles
 *   now hold Maintenance: manage and log explicitly.
 * - Actions that allowed nothing are removed (Listings add/delete/import, Expenses import, Leads delete,
 *   Agents delete), as are the unused "commercial_units" and "notifications" groups.
 * - Every action comes with its group's "view" (already true for every stored role).
 * Running it again changes nothing.
 */
return new class extends Migration
{
    private const DEAD = [
        'listings_management' => ['create', 'delete', 'import'],
        'expense_management' => ['import'],
        'lead_management' => ['delete'],
        'agent_management' => ['delete'],
    ];

    public function up(): void
    {
        $catalogue = $this->catalogue();
        if (Schema::hasTable('business_roles')) {
            foreach (DB::table('business_roles')->get(['id', 'permissions']) as $role) {
                if (($updated = $this->convert($role->permissions, $catalogue)) !== null) {
                    DB::table('business_roles')->where('id', $role->id)->update(['permissions' => json_encode($updated)]);
                }
            }
        }
        if (Schema::hasColumn('users', 'business_permissions')) {
            foreach (DB::table('users')->whereNotNull('business_permissions')->get(['id', 'business_permissions']) as $user) {
                if (($updated = $this->convert($user->business_permissions, $catalogue)) !== null) {
                    DB::table('users')->where('id', $user->id)->update(['business_permissions' => json_encode($updated)]);
                }
            }
        }
    }

    public function down(): void
    {
        // Restore a backup to undo.
    }

    /** group => actions that have a "view" (read from the config at the time of migrating). */
    private function catalogue(): array
    {
        $groups = [];
        foreach ((array) config('business_route_permissions', []) as $definition) {
            $groups[$definition['group']] = $definition['permissions'] ?? [];
        }

        return $groups;
    }

    private function convert($stored, array $catalogue): ?array
    {
        $before = is_array($stored) ? $stored : json_decode((string) $stored, true);
        if (!is_array($before) || array_key_exists('*', $before)) {
            return null;
        }
        $p = $before;
        $has = fn (string $group, string $action) => in_array($action, (array) ($p[$group] ?? []), true);

        $maintenance = (array) ($p['maintenance_management'] ?? []);
        if ($has('complex_management', 'view')) $maintenance[] = 'view';
        if ($has('complex_management', 'edit')) $maintenance[] = 'manage';
        if ($has('tenant_management', 'create')) $maintenance[] = 'log';
        if ($maintenance) {
            $p['maintenance_management'] = array_values(array_unique($maintenance));
        }

        foreach (self::DEAD as $group => $actions) {
            if (isset($p[$group])) {
                $p[$group] = array_values(array_diff((array) $p[$group], $actions));
            }
        }
        unset($p['commercial_units'], $p['notifications']);

        foreach ($p as $group => $actions) {
            $actions = array_values(array_unique((array) $actions));
            if ($actions && in_array('view', $catalogue[$group] ?? [], true) && !in_array('view', $actions, true)) {
                $actions[] = 'view';
            }
            if ($actions) {
                $p[$group] = $actions;
            } else {
                unset($p[$group]);
            }
        }

        return $p == $before ? null : $p;
    }
};
