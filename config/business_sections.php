<?php

/*
|--------------------------------------------------------------------------
| Sections of the app, as people see them
|--------------------------------------------------------------------------
| The role editor groups permissions under these headings, in this order, so whoever assigns a role sees
| the same places the person will see: the left menu first, then the property portfolio (top bar) and the
| profile menu. `shows` lists the rights that put the section in that person's menu (any one is enough);
| the role editor previews it as permissions are ticked.
|
|   label   heading in the role editor and name of the menu item
|   icon    Font Awesome icon
|   where   where the person finds it
|   shows   rights ("group.action") that make it appear (any one)
|   groups  the permission groups (config/business_route_permissions.php) under this heading
*/

return [
    'home' => [
        'label' => 'Home', 'icon' => 'fa-home', 'where' => 'Left menu',
        'shows' => ['dashboard_access.view'],
        'groups' => ['dashboard_access'],
    ],
    'crm' => [
        'label' => 'CRM', 'icon' => 'fa-address-card', 'where' => 'Left menu',
        'shows' => ['crm_management.view'],
        'groups' => ['crm_management', 'lead_management'],
    ],
    'agents' => [
        'label' => 'Agents', 'icon' => 'fa-user-tie', 'where' => 'Left menu',
        'shows' => ['agent_management.view', 'task_management.view'],
        'groups' => ['agent_management', 'task_management'],
    ],
    'tenants' => [
        'label' => 'Tenants', 'icon' => 'fa-users', 'where' => 'Left menu',
        'shows' => ['tenant_management.view'],
        'groups' => ['tenant_management'],
    ],
    'sales' => [
        'label' => 'Sales', 'icon' => 'fa-handshake', 'where' => 'Left menu', 'module' => 'sales',
        'shows' => ['sales_management.view'],
        'groups' => ['sales_management'],
    ],
    'maintenance' => [
        'label' => 'Maintenance', 'icon' => 'fa-tools', 'where' => 'Left menu',
        'shows' => ['maintenance_management.view'],
        'groups' => ['maintenance_management'],
    ],
    'legal' => [
        'label' => 'Legal', 'icon' => 'fa-balance-scale', 'where' => 'Left menu',
        'shows' => ['legal_documents.view'],
        'groups' => ['legal_documents'],
    ],
    'reports' => [
        'label' => 'Reports', 'icon' => 'fa-chart-bar', 'where' => 'Left menu',
        'shows' => ['reports.view', 'reports.financial', 'reports.occupancy', 'reports.agents', 'reports.sales', 'reports.maintenance'],
        'groups' => ['reports'],
    ],
    'properties' => [
        'label' => 'Property portfolio', 'icon' => 'fa-building', 'where' => 'Top bar',
        'shows' => ['complex_management.view'],
        'groups' => ['complex_management', 'listing_history', 'service_charge_management', 'expense_management'],
    ],
    'listings' => [
        'label' => 'Listings', 'icon' => 'fa-bullhorn', 'where' => 'Top bar and profile menu', 'module' => 'listings',
        'shows' => ['listings_management.view'],
        'groups' => ['listings_management'],
    ],
    'business' => [
        'label' => 'Business & team', 'icon' => 'fa-briefcase', 'where' => 'Profile menu',
        'shows' => ['account_settings.view', 'subaccount_management.view', 'role_management.view', 'activity_log.view'],
        'groups' => ['account_settings', 'subaccount_management', 'role_management', 'activity_log'],
    ],
];
