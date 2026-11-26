<?php

namespace App\Services\Access;

use App\Models\BusinessRole;
use Illuminate\Support\Facades\DB;

/**
 * The standard roles every business starts with. They are ordinary, editable roles.
 * Each one is offered to a business once (businesses.starter_roles_added): roles added to this list
 * later reach existing businesses, and a starter role an owner deleted is not created again.
 */
class StarterRoles
{
    /**
     * name => [color, description, permissions (group => actions | '*' all actions | 'view' read only | [] none), property scope].
     * Property scope "assigned" limits the role to the properties assigned to each member.
     */
    public static function definitions(): array
    {
        $all = '*';
        return [
            'Administrator' => ['danger', 'Runs the business day to day: every section and action, including team and roles. Modules, payment methods and deleting the account stay with the account owner.', [
                '*' => $all,
            ], 'all'],
            'Property Manager' => ['primary', 'Sets up and maintains properties, units, facilities and charges, and manages tenancies.', [
                'dashboard_access' => ['view'],
                'complex_management' => ['view', 'create', 'edit', 'delete', 'export', 'import'],
                'listing_history' => $all,
                'service_charge_management' => $all,
                'expense_management' => ['view', 'create', 'edit', 'export'],
                'tenant_management' => ['view', 'create', 'edit', 'billing', 'notices', 'transfers', 'export'],
                'crm_management' => ['view'],
                'lead_management' => ['view', 'create', 'edit', 'update_status'],
                'task_management' => ['view', 'create', 'edit', 'complete'],
                'legal_documents' => ['view', 'create', 'edit', 'download'],
                'listings_management' => ['view', 'create', 'edit', 'publish'],
                'sales_management' => ['view', 'create'],
                'maintenance_management' => $all,
                'reports' => ['view', 'financial', 'occupancy', 'sales', 'maintenance', 'export'],
            ], 'assigned'],
            'Leasing / CRM Agent' => ['info', 'Works leads and follow-up tasks, and onboards new tenants.', [
                'dashboard_access' => ['view'],
                'complex_management' => ['view'],
                'crm_management' => ['view'],
                'lead_management' => ['view', 'create', 'edit', 'update_status'],
                'task_management' => ['view', 'create', 'edit', 'complete', 'skip'],
                'agent_management' => ['view'],
                'tenant_management' => ['view', 'create'],
                'legal_documents' => ['view', 'download'],
                'listings_management' => ['view', 'create', 'edit'],
                'sales_management' => ['view', 'create'],
                'maintenance_management' => ['view', 'log'],
                'reports' => ['view', 'occupancy', 'agents'],
            ], 'assigned'],
            'Finance Officer' => ['success', 'Handles billing, payments and reconciliation, property expenses and financial reports.', [
                'dashboard_access' => ['view'],
                'complex_management' => ['view'],
                'expense_management' => ['view', 'create', 'edit', 'approve', 'pay', 'export'],
                'service_charge_management' => ['view', 'create', 'edit'],
                'tenant_management' => ['view', 'billing', 'payments', 'export'],
                'agent_management' => ['view'],
                'legal_documents' => ['view', 'download'],
                'sales_management' => ['view', 'payments'],
                'maintenance_management' => ['view'],
                'reports' => $all,
            ], 'all'],
            'Viewer' => ['dark', 'Read-only access to every section, for reviewers and auditors. Cannot change anything.', [
                '*' => 'view',
                'account_settings' => [],
                'reports' => $all, // reading and downloading reports changes nothing
            ], 'all'],
            'Maintenance Coordinator' => ['warning', 'Logs, assigns and closes repair requests at their properties and records what repairs cost. Cannot change property set-up.', [
                'dashboard_access' => ['view'],
                'complex_management' => ['view'],
                'maintenance_management' => $all,
                'expense_management' => ['view', 'create'],
                'tenant_management' => ['view'],
                'legal_documents' => ['view', 'download'],
                'reports' => ['view', 'occupancy', 'maintenance'],
            ], 'assigned'],
            'Caretaker' => ['info', 'On-site staff: sees their properties\' units and tenants, logs repair requests and records tenant follow-ups. No money or settings.', [
                'dashboard_access' => ['view'],
                'complex_management' => ['view'],
                'maintenance_management' => ['view', 'log'],
                'tenant_management' => ['view'],
                'reports' => ['view', 'occupancy', 'maintenance'],
            ], 'assigned'],
            'Accountant' => ['success', 'Reads everything financial, records payments, approves and pays expenses, and runs reports and exports. Cannot set up properties or tenancies.', [
                'dashboard_access' => ['view'],
                'complex_management' => ['view', 'export'],
                'expense_management' => ['view', 'approve', 'pay', 'export'],
                'service_charge_management' => ['view'],
                'tenant_management' => ['view', 'payments', 'export'],
                'agent_management' => ['view'],
                'legal_documents' => ['view', 'download'],
                'sales_management' => ['view', 'payments'],
                'maintenance_management' => ['view'],
                'reports' => $all,
            ], 'all'],
            'Agent (sales only)' => ['primary', 'A field agent at their assigned properties: works their own leads and follow-up tasks and sees their commission. Cannot change tenancies or billing.', [
                'dashboard_access' => ['view'],
                'complex_management' => ['view'],
                'crm_management' => ['view'],
                'lead_management' => ['view', 'create', 'edit', 'update_status'],
                'task_management' => ['view', 'complete', 'skip'],
                'listings_management' => ['view'],
                'sales_management' => ['view', 'create'],
                'maintenance_management' => ['view'],
                'reports' => ['view', 'occupancy', 'sales', 'maintenance'],
            ], 'assigned'],
        ];
    }

    /** Resolve a definition into stored permissions using the catalogue (only defined groups/actions). */
    /** Departments roles are grouped under (owners can add their own). */
    public const DEPARTMENTS = ['Administration', 'Finance', 'Property operations', 'Sales & leasing', 'Oversight'];

    /** The department of a role by its name: the starter roles' departments, or a best guess for other names. */
    public static function departmentFor(?string $name): ?string
    {
        $n = mb_strtolower(trim((string) $name));
        return match (true) {
            $n === '' => null,
            str_contains($n, 'admin') => 'Administration',
            str_contains($n, 'financ') || str_contains($n, 'account') || str_contains($n, 'billing') => 'Finance',
            str_contains($n, 'agent') || str_contains($n, 'leasing') || str_contains($n, 'sales') || str_contains($n, 'crm') => 'Sales & leasing',
            str_contains($n, 'property') || str_contains($n, 'maintenance') || str_contains($n, 'caretaker') || str_contains($n, 'facilit') => 'Property operations',
            str_contains($n, 'viewer') || str_contains($n, 'audit') || str_contains($n, 'read') => 'Oversight',
            default => null,
        };
    }

    /** Agent roles (leasing, sales) must stay limited to assigned properties. */
    public static function isAgentRoleName(?string $name, ?string $department = null): bool
    {
        return $department === 'Sales & leasing' || self::departmentFor($name) === 'Sales & leasing';
    }

    public static function permissions(array $spec): array
    {
        $catalogue = collect(config('business_route_permissions', []))
            ->reject(fn ($definition) => !empty($definition['hidden']))
            ->mapWithKeys(fn ($definition) => [$definition['group'] => $definition['permissions'] ?? []]);

        $resolved = [];
        foreach ($catalogue as $group => $actions) {
            $wanted = $spec[$group] ?? $spec['*'] ?? null;
            if ($wanted === null) continue;
            $selected = $wanted === '*' ? $actions : array_values(array_intersect((array) $wanted, $actions));
            if ($selected) $resolved[$group] = $selected;
        }
        return $resolved;
    }

    /** Create the starter roles this business has not been offered yet. Returns the number created. */
    public function ensureFor(int $ownerId): int
    {
        $business = DB::table('businesses')->where('user_id', $ownerId)->first();
        if (!$business) {
            return 0;
        }
        $offered = json_decode((string) $business->starter_roles_added, true) ?: [];
        $missing = array_diff_key(self::definitions(), array_flip($offered));
        if (!$missing) {
            return 0;
        }

        return DB::transaction(function () use ($ownerId, $offered, $missing) {
            $created = 0;
            foreach ($missing as $name => [$color, $description, $spec, $scope]) {
                $offered[] = $name;
                // A role the owner already made with this name is kept as it is.
                if (BusinessRole::where('business_id', $ownerId)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) continue;
                BusinessRole::create([
                    'business_id' => $ownerId, 'user_id' => $ownerId, 'name' => $name, 'description' => $description,
                    'color' => $color, 'is_active' => true, 'permissions' => self::permissions($spec), 'property_scope' => $scope,
                    'department' => self::departmentFor($name),
                ]);
                $created++;
            }
            DB::table('businesses')->where('user_id', $ownerId)->update([
                'starter_roles_added' => json_encode(array_values(array_unique($offered))),
                'starter_roles_seeded_at' => now(),
            ]);
            return $created;
        });
    }
}
