<?php

namespace App\Services\Access;

use App\Models\User;

/**
 * The permissions a role can hold, as the role editor and "My access" show them: under the same headings
 * as the left menu (config/business_sections.php), with a label and an explanation for every action.
 */
class PermissionCatalogue
{
    /** Labels for actions shared by every group (a group can override them with `action_labels`). */
    public const ACTION_LABELS = [
        'view' => 'View', 'create' => 'Add', 'edit' => 'Edit', 'delete' => 'Delete', 'export' => 'Download', 'import' => 'Import',
        'download' => 'Download', 'approve' => 'Approve', 'pay' => 'Record payments', 'publish' => 'Publish', 'billing' => 'Billing',
        'payments' => 'Payments', 'transfers' => 'Transfers', 'notices' => 'Notices & move-outs', 'update_status' => 'Change status',
        'invite' => 'Invite to sign in', 'complete' => 'Complete', 'skip' => 'Skip', 'cancel' => 'Cancel', 'manage' => 'Manage repairs',
        'log' => 'Log requests', 'assign' => 'Assign people',
    ];

    public function __construct(private BusinessPermissionService $permissions)
    {
    }

    public function label(array $definition, string $action): string
    {
        return $definition['action_labels'][$action] ?? self::ACTION_LABELS[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * The editor's sections: [key, label, icon, where, shows[], groups[ [group, label, description, actions[ [key, label, help] ] ] ]].
     * Groups of switched-off modules are left out.
     */
    public function sections(?User $user = null): array
    {
        $definitions = collect($this->permissions->visibleDefinitions($user))->keyBy('group');
        $modules = app(ModuleService::class);
        $sections = [];
        foreach (config('business_sections') as $key => $section) {
            if (!empty($section['module']) && !$modules->enabled($section['module'], $user)) {
                continue;
            }
            $groups = [];
            foreach ($section['groups'] as $group) {
                if (!$definition = $definitions->get($group)) {
                    continue;
                }
                $groups[] = [
                    'group' => $group,
                    'label' => $definition['display_name'] ?? $group,
                    'description' => $definition['description'] ?? '',
                    'actions' => array_map(fn ($action) => [
                        'key' => $action,
                        'label' => $this->label($definition, $action),
                        'help' => $definition['help'][$action] ?? '',
                    ], $definition['permissions'] ?? []),
                ];
            }
            if ($groups) {
                $sections[] = ['key' => $key, 'label' => $section['label'], 'icon' => $section['icon'], 'where' => $section['where'],
                    'shows' => $section['shows'], 'groups' => $groups];
            }
        }

        return $sections;
    }

    /**
     * Add the rights an action needs: every action needs its group's "view" when the group has one.
     *
     * @param array<string, string[]> $permissions
     * @return array<string, string[]>
     */
    public static function withRequirements(array $permissions): array
    {
        $catalogue = collect(config('business_route_permissions', []))->keyBy('group');
        foreach ($permissions as $group => $actions) {
            $available = $catalogue[$group]['permissions'] ?? [];
            if ($actions && in_array('view', $available, true) && !in_array('view', $actions, true)) {
                $permissions[$group] = array_values(array_intersect($available, array_merge(['view'], $actions)));
            }
        }

        return $permissions;
    }

    /** Menu sections a set of permissions opens (for the editor's preview and "My access"); switched-off modules never show. */
    public function menuFor(array $permissions, bool $owner = false): array
    {
        $modules = app(ModuleService::class);

        return collect(config('business_sections'))
            ->reject(fn ($s) => !empty($s['module']) && !$modules->enabled($s['module']))
            ->filter(fn ($s) => $owner || collect($s['shows'])->contains(function ($right) use ($permissions) {
                [$group, $action] = explode('.', $right, 2);

                return in_array($action, $permissions[$group] ?? [], true);
            }))
            ->map(fn ($s) => $s['label'])->values()->all();
    }
}
