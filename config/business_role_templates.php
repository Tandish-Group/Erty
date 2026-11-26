<?php

/**
 * Predefined Business Role Templates
 * 
 * Maps common business roles to their appropriate permission groups and actions.
 * Used for quick role creation with sensible defaults.
 * 
 * Structure:
 * - role_key: Unique identifier for the role
 * - display_name: Human-readable role name
 * - description: What this role typically does
 * - color: Badge color for UI
 * - permissions: Array of permission groups with allowed actions
 * 
 * Date: January 21, 2026
 */

return [

    /*
    |--------------------------------------------------------------------------
    | PROPERTY MANAGER
    | Full property management with listing & complex control
    |--------------------------------------------------------------------------
    */
    'property_manager' => [
        'display_name' => 'Property Manager',
        'description' => 'Manages properties, listings, and day-to-day operations',
        'color' => 'primary',
        'department' => 'Operations',
        'permissions' => [
            'dashboard_access' => ['view'],
            'complex_management' => ['view', 'create', 'edit', 'delete', 'export', 'import'],
            'commercial_units' => ['view', 'create', 'edit', 'delete', 'import'],
            'listings_management' => ['view', 'create', 'edit', 'delete', 'publish', 'import'],
            'listing_history' => ['view'],
            'service_charge_management' => ['view', 'create', 'edit'],
            'tenant_management' => ['view', 'create', 'edit'], // Limited tenant access
            'notifications' => ['view', 'manage'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | FINANCE OFFICER / ACCOUNTANT
    | Financial operations, expenses, billing
    |--------------------------------------------------------------------------
    */
    'finance_officer' => [
        'display_name' => 'Finance Officer',
        'description' => 'Manages financial transactions, expenses, and tenant billing',
        'color' => 'success',
        'department' => 'Finance',
        'permissions' => [
            'dashboard_access' => ['view'],
            'expense_management' => ['view', 'create', 'edit', 'approve', 'export', 'import'],
            'service_charge_management' => ['view', 'create', 'edit', 'import'],
            'tenant_management' => ['view', 'billing', 'export'], // Only billing-related
            'complex_management' => ['view'], // Read-only for context
            'listings_management' => ['view'], // Read-only for context
            'notifications' => ['view'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | TENANT RELATIONS OFFICER
    | Focused on tenant management, payments, notices
    |--------------------------------------------------------------------------
    */
    'tenant_relations' => [
        'display_name' => 'Tenant Relations Officer',
        'description' => 'Manages tenant relationships, payments, and communication',
        'color' => 'info',
        'department' => 'Operations',
        'permissions' => [
            'dashboard_access' => ['view'],
            'tenant_management' => ['view', 'create', 'edit', 'billing', 'import', 'export'],
            'service_charge_management' => ['view'],
            'complex_management' => ['view'], // Read-only
            'listings_management' => ['view'], // Read-only
            'notifications' => ['view', 'manage'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SALES AGENT / LEASING AGENT
    | Customer-facing, leads, appointments, CRM
    |--------------------------------------------------------------------------
    */
    'sales_agent' => [
        'display_name' => 'Sales Agent',
        'description' => 'Handles leads, client appointments, and property showings',
        'color' => 'warning',
        'department' => 'Sales',
        'permissions' => [
            'dashboard_access' => ['view'],
            'crm_management' => ['view'],
            'lead_management' => ['view', 'create', 'edit', 'update_status'],
            'task_management' => ['view', 'create', 'edit', 'complete'],
            'listings_management' => ['view'], // Read-only
            'complex_management' => ['view'], // Read-only
            'notifications' => ['view', 'manage'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RECEPTIONIST / ADMIN ASSISTANT
    | Limited access: appointments, basic viewing
    |--------------------------------------------------------------------------
    */
    'receptionist' => [
        'display_name' => 'Receptionist',
        'description' => 'Handles scheduling, basic inquiries, and administrative tasks',
        'color' => 'secondary',
        'department' => 'Administration',
        'permissions' => [
            'dashboard_access' => ['view'],
            'lead_management' => ['view', 'create'], // Can log leads but not manage
            'tenant_management' => ['view'], // Read-only
            'listings_management' => ['view'], // Read-only
            'complex_management' => ['view'], // Read-only
            'notifications' => ['view'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MAINTENANCE COORDINATOR
    | Property maintenance, expenses tracking
    |--------------------------------------------------------------------------
    */
    'maintenance_coordinator' => [
        'display_name' => 'Maintenance Coordinator',
        'description' => 'Manages property maintenance, repairs, and related expenses',
        'color' => 'dark',
        'department' => 'Maintenance',
        'permissions' => [
            'dashboard_access' => ['view'],
            'complex_management' => ['view', 'edit'], // Can update amenities/facilities
            'listings_management' => ['view'],
            'expense_management' => ['view', 'create', 'edit'], // Can log expenses
            'service_charge_management' => ['view'],
            'tenant_management' => ['view'], // Read-only for contact info
            'notifications' => ['view'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SENIOR AGENT / TEAM LEADER
    | Sales team management, full CRM access
    |--------------------------------------------------------------------------
    */
    'senior_agent' => [
        'display_name' => 'Senior Agent / Team Leader',
        'description' => 'Manages sales team, leads, and overall CRM operations',
        'color' => 'danger',
        'department' => 'Sales',
        'permissions' => [
            'dashboard_access' => ['view'],
            'crm_management' => ['view'],
            'lead_management' => ['view', 'create', 'edit', 'delete', 'update_status'],
            'agent_management' => ['view', 'create', 'edit', 'invite'], // Can manage team
            'task_management' => ['view', 'create', 'edit', 'complete', 'skip'],
            'listings_management' => ['view', 'edit'], // Can update listings
            'complex_management' => ['view'],
            'tenant_management' => ['view'],
            'notifications' => ['view', 'manage'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OPERATIONS MANAGER
    | Broad operational access, no financial management
    |--------------------------------------------------------------------------
    */
    'operations_manager' => [
        'display_name' => 'Operations Manager',
        'description' => 'Oversees daily operations, property management, and tenant relations',
        'color' => 'primary',
        'department' => 'Operations',
        'permissions' => [
            'dashboard_access' => ['view'],
            'complex_management' => ['view', 'create', 'edit', 'delete', 'export', 'import'],
            'commercial_units' => ['view', 'create', 'edit', 'delete', 'import'],
            'listings_management' => ['view', 'create', 'edit', 'delete', 'publish', 'import'],
            'listing_history' => ['view'],
            'tenant_management' => ['view', 'create', 'edit', 'billing', 'export'],
            'service_charge_management' => ['view', 'create', 'edit', 'import'],
            'expense_management' => ['view', 'create', 'edit'], // Cannot approve/delete
            'notifications' => ['view', 'manage'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MARKETING COORDINATOR
    | Listings, analytics, limited editing
    |--------------------------------------------------------------------------
    */
    'marketing_coordinator' => [
        'display_name' => 'Marketing Coordinator',
        'description' => 'Manages property listings marketing and analytics',
        'color' => 'info',
        'department' => 'Marketing',
        'permissions' => [
            'dashboard_access' => ['view'],
            'listings_management' => ['view', 'edit', 'publish'], // Can update descriptions, photos
            'complex_management' => ['view'],
            'listing_history' => ['view'],
            'notifications' => ['view'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | COMPLIANCE OFFICER / LEGAL
    | Document management, view-only on operations
    |--------------------------------------------------------------------------
    */
    'compliance_officer' => [
        'display_name' => 'Compliance Officer',
        'description' => 'Manages legal documents and ensures regulatory compliance',
        'color' => 'dark',
        'department' => 'Legal',
        'permissions' => [
            'dashboard_access' => ['view'],
            'legal_documents' => ['view', 'create', 'edit', 'delete', 'download'],
            'tenant_management' => ['view'], // Read-only
            'complex_management' => ['view'], // Read-only
            'listings_management' => ['view'], // Read-only
            'notifications' => ['view'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AUDITOR / READ-ONLY
    | View-only access for auditing purposes
    |--------------------------------------------------------------------------
    */
    'auditor' => [
        'display_name' => 'Auditor',
        'description' => 'Read-only access for auditing and compliance review',
        'color' => 'secondary',
        'department' => 'Finance',
        'permissions' => [
            'dashboard_access' => ['view'],
            'complex_management' => ['view', 'export'],
            'listings_management' => ['view'],
            'listing_history' => ['view'],
            'expense_management' => ['view', 'export'],
            'tenant_management' => ['view', 'export'],
            'service_charge_management' => ['view'],
            'legal_documents' => ['view', 'download'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DATA ENTRY CLERK
    | Basic data entry, no deletion rights
    |--------------------------------------------------------------------------
    */
    'data_entry_clerk' => [
        'display_name' => 'Data Entry Clerk',
        'description' => 'Handles data entry tasks for properties and tenants',
        'color' => 'secondary',
        'department' => 'Administration',
        'permissions' => [
            'dashboard_access' => ['view'],
            'complex_management' => ['view', 'create', 'import'],
            'listings_management' => ['view', 'create', 'import'],
            'tenant_management' => ['view', 'create', 'import'],
            'expense_management' => ['view', 'create', 'import'],
            'service_charge_management' => ['view', 'create', 'import'],
            'notifications' => ['view'],
        ],
    ],

];