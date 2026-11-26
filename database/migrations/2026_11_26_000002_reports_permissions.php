<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reports get their own permission group ("reports"). Existing roles, and people with their own
 * permissions, get exactly the reports they could already open, so nobody gains or loses access:
 *   overview    ← Tenants: reports or view, or Properties: view
 *   financial   ← Tenants: reports (the old "Reports" action, which is retired)
 *   occupancy   ← Tenants: view or Properties: view (occupancy and leases)
 *   agents      ← Agents: view
 *   sales       ← Sales: view
 *   maintenance ← Properties: view
 *   export      ← any of the above (downloads were open to anyone who could open a report)
 * Roles that already have a "reports" group are left alone, so running this again changes nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_roles')) {
            foreach (DB::table('business_roles')->get(['id', 'permissions']) as $role) {
                $updated = $this->convert($role->permissions);
                if ($updated !== null) {
                    DB::table('business_roles')->where('id', $role->id)->update(['permissions' => json_encode($updated)]);
                }
            }
        }
        if (Schema::hasColumn('users', 'business_permissions')) {
            foreach (DB::table('users')->whereNotNull('business_permissions')->get(['id', 'business_permissions']) as $user) {
                $updated = $this->convert($user->business_permissions);
                if ($updated !== null) {
                    DB::table('users')->where('id', $user->id)->update(['business_permissions' => json_encode($updated)]);
                }
            }
        }
    }

    public function down(): void
    {
        // The old rights are kept apart from the retired Tenants "reports" action; restore a backup to undo.
    }

    /** The converted permissions, or null when nothing changes. */
    private function convert($stored): ?array
    {
        $permissions = is_array($stored) ? $stored : json_decode((string) $stored, true);
        if (!is_array($permissions) || array_key_exists('reports', $permissions) || array_key_exists('*', $permissions)) {
            return null;
        }
        $has = fn (string $group, string $action) => in_array($action, (array) ($permissions[$group] ?? []), true);

        $money = $has('tenant_management', 'reports');
        $tenants = $has('tenant_management', 'view');
        $properties = $has('complex_management', 'view');
        $reports = array_keys(array_filter([
            'view' => $money || $tenants || $properties,
            'financial' => $money,
            'occupancy' => $tenants || $properties,
            'agents' => $has('agent_management', 'view'),
            'sales' => $has('sales_management', 'view'),
            'maintenance' => $properties,
        ]));
        if ($reports) {
            $reports[] = 'export';
            $permissions['reports'] = $reports;
        }

        if (isset($permissions['tenant_management'])) {
            $permissions['tenant_management'] = array_values(array_diff((array) $permissions['tenant_management'], ['reports']));
        }

        return $reports || $money ? $permissions : null;
    }
};
