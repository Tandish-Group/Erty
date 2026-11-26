<?php

/*
|--------------------------------------------------------------------------
| Permission groups
|--------------------------------------------------------------------------
| The permissions a role can be given, grouped by area. Which routes need which group and action is in
| config/business_access.php; the headings the groups appear under in the role editor (matching the left
| menu) are in config/business_sections.php.
|
|   group          stored key in a role's permissions (business_roles.permissions)
|   display_name   name in the role editor
|   description    one line on what the area is
|   permissions    the actions that can be granted
|   help           action => what it lets the person see or do, shown next to its tick box
|   action_labels  optional labels for this group's actions (default: the shared labels)
|   module         optional Erty module the group belongs to (hidden while that module is off)
|
| Every action other than "view" needs the group's "view": the role editor ticks it for you and saving adds
| it. Every action listed here is enforced by a route or a check in code (a test fails otherwise).
*/

return [

    'dashboard' => [
        'group' => 'dashboard_access',
        'display_name' => 'Home',
        'description' => 'The dashboard: occupancy, rent collected and what needs attention.',
        'permissions' => ['view'],
        'help' => [
            'view' => 'Open Home. Money figures on it also need Tenants: billing or payments, or Reports: money.',
        ],
    ],

    'crm_management' => [
        'group' => 'crm_management',
        'display_name' => 'CRM overview & documents',
        'description' => 'The CRM page, CRM reports and the document centre.',
        'permissions' => ['view'],
        'help' => [
            'view' => 'Open CRM in the menu: the pipeline and follow-ups overview, CRM reports and the document centre. The document centre lists only the kinds of file the role can open in their own section (tenancy files need Tenants, receipts need Tenants → Payments, expense evidence needs Expenses, property history needs History, legal documents need Legal, CVs need Agents).',
        ],
    ],

    'lead_management' => [
        'group' => 'lead_management',
        'display_name' => 'Leads',
        'description' => 'People interested in renting or buying, from first contact until they become tenants.',
        'permissions' => ['view', 'create', 'edit', 'update_status'],
        'help' => [
            'view' => 'See leads and each lead\'s profile and history.',
            'create' => 'Capture new leads.',
            'edit' => 'Log calls, meetings and notes on a lead.',
            'update_status' => 'Move a lead through its stages and confirm its details. Converting a lead to a tenant also needs Tenants: add.',
        ],
    ],

    'agent_management' => [
        'group' => 'agent_management',
        'display_name' => 'Agents & commissions',
        'description' => 'The agent directory, agent profiles and the commission ledger.',
        'permissions' => ['view', 'create', 'edit', 'invite'],
        'help' => [
            'view' => 'Open Agents: the directory, profiles, commission ledger and statements.',
            'create' => 'Onboard new agents.',
            'edit' => 'Change agents and their commission agreements, approve period commission and record payouts.',
            'invite' => 'Invite an agent to sign in to Erty.',
        ],
    ],

    'task_management' => [
        'group' => 'task_management',
        'display_name' => 'Agent tasks',
        'description' => 'The follow-up tasks agents get for their leads (Agents → Tasks).',
        'permissions' => ['view', 'create', 'edit', 'complete', 'skip'],
        'help' => [
            'view' => 'Open the task list. Agents only see their own tasks.',
            'create' => 'Add tasks.',
            'edit' => 'Mark tasks as blocked.',
            'complete' => 'Start and complete tasks.',
            'skip' => 'Skip tasks that do not apply.',
        ],
    ],

    'tenant_management' => [
        'group' => 'tenant_management',
        'display_name' => 'Tenants & tenancies',
        'description' => 'Tenants, tenancies and leases, and each tenant\'s account.',
        'permissions' => ['view', 'create', 'edit', 'delete', 'billing', 'payments', 'transfers', 'notices', 'import', 'export'],
        'help' => [
            'view' => 'Open Tenants: tenancies, tenant profiles, leases, statements and follow-ups.',
            'create' => 'Onboard tenants and add leases.',
            'edit' => 'Change tenancies, tenant profiles and leases, and blacklist tenants.',
            'delete' => 'Delete tenancies and leases.',
            'billing' => 'Open Billings, add charges and void bills.',
            'payments' => 'Record payments and refunds, confirm or reverse them, and match receipts (POP reconciliation).',
            'transfers' => 'Move tenants to another unit and approve transfers.',
            'notices' => 'Give notice and end tenancies.',
            'import' => 'Import tenants, payments and notices from a spreadsheet.',
            'export' => 'Download tenant data.',
        ],
    ],

    'sales_management' => [
        'group' => 'sales_management',
        'module' => 'sales',
        'display_name' => 'Sales',
        'description' => 'Houses sold on a deposit and instalments.',
        'permissions' => ['view', 'create', 'payments', 'complete', 'cancel'],
        'help' => [
            'view' => 'Open Sales, each sale, buyer statements and receipts.',
            'create' => 'Start a sale.',
            'payments' => 'Record and reverse the buyer\'s payments.',
            'complete' => 'Complete a sale and record the title transfer.',
            'cancel' => 'Cancel a sale.',
        ],
    ],

    'maintenance_management' => [
        'group' => 'maintenance_management',
        'display_name' => 'Maintenance',
        'description' => 'Repair requests: who handles them and what they cost.',
        'permissions' => ['view', 'log', 'manage'],
        'help' => [
            'view' => 'Open Maintenance and see repair requests at the properties the person can reach.',
            'log' => 'Log new repair requests.',
            'manage' => 'Assign, start, complete and close repairs with their cost. Recharging a repair to the tenant also needs Tenants: billing.',
        ],
    ],

    'legal_documents' => [
        'group' => 'legal_documents',
        'display_name' => 'Legal documents',
        'description' => 'Contracts, leases and other documents, kept in folders.',
        'permissions' => ['view', 'create', 'edit', 'delete', 'download'],
        'help' => [
            'view' => 'Open Legal and see the folders and documents.',
            'create' => 'Create folders and upload documents.',
            'edit' => 'Rename folders and move documents.',
            'delete' => 'Delete folders and documents.',
            'download' => 'Download documents and whole folders.',
        ],
    ],

    'reports' => [
        'group' => 'reports',
        'display_name' => 'Reports',
        'description' => 'The Reports section. Each report only shows the properties the person can reach.',
        'permissions' => ['view', 'financial', 'occupancy', 'agents', 'sales', 'maintenance', 'export'],
        'help' => [
            'view' => 'Business overview: every property side by side.',
            'financial' => 'Money reports: income & expenses and the rent roll & arrears.',
            'occupancy' => 'Occupancy & vacancy, and leases & notices.',
            'agents' => 'Agents & leads: leads, conversions, tasks and commission per agent, and the Leads & pipeline report (sources, phases, overdue follow-ups).',
            'sales' => 'Sales: value, collections, balances and instalments.',
            'maintenance' => 'Maintenance: repairs, time to close and repair costs.',
            'export' => 'Download the reports the person can open, as Excel or PDF.',
        ],
        'action_labels' => [
            'view' => 'Overview',
            'financial' => 'Money (income, rent roll)',
            'occupancy' => 'Occupancy & leases',
            'agents' => 'Agents, leads & pipeline',
            'sales' => 'Sales',
            'maintenance' => 'Maintenance',
            'export' => 'Download',
        ],
    ],

    'complex_management' => [
        'group' => 'complex_management',
        'display_name' => 'Properties & units',
        'description' => 'The property portfolio: properties, units, houses, commercial spaces, floor plans and facilities.',
        'permissions' => ['view', 'create', 'edit', 'delete', 'import', 'export', 'assign'],
        'help' => [
            'view' => 'Open Property portfolio and every property, its units, floor plans and facilities.',
            'create' => 'Add properties, commercial spaces and facilities.',
            'edit' => 'Change a property\'s set-up, units, floor plans, facilities and commercial spaces.',
            'delete' => 'Remove facilities and commercial spaces.',
            'import' => 'Import properties and units from a spreadsheet.',
            'export' => 'Download the property list.',
            'assign' => 'Choose who manages each property and which team members work it.',
        ],
    ],

    'listing_history' => [
        'group' => 'listing_history',
        'display_name' => 'Property history',
        'description' => 'What happened to each property and unit: ownership, renovations, legal matters, incidents.',
        'permissions' => ['view', 'create'],
        'help' => [
            'view' => 'Open Property history and each record.',
            'create' => 'Add history records, and remove ones added by mistake.',
        ],
    ],

    'service_charge_management' => [
        'group' => 'service_charge_management',
        'display_name' => 'Service charges',
        'description' => 'Recurring charges billed to tenants for shared facilities.',
        'permissions' => ['view', 'create', 'edit', 'delete', 'import'],
        'help' => [
            'view' => 'Open Service charges.',
            'create' => 'Add service charges.',
            'edit' => 'Change service charges.',
            'delete' => 'Remove service charges.',
            'import' => 'Import service charges from a spreadsheet.',
        ],
    ],

    'expense_management' => [
        'group' => 'expense_management',
        'display_name' => 'Property expenses',
        'description' => 'Costs of running the properties, with approval and payment.',
        'permissions' => ['view', 'create', 'edit', 'delete', 'approve', 'pay', 'export'],
        'help' => [
            'view' => 'Open Property expenses and each expense\'s approval trail and evidence.',
            'create' => 'Record expenses.',
            'edit' => 'Change expenses, add evidence and send them for approval.',
            'delete' => 'Void and delete expenses.',
            'approve' => 'Approve or return expenses sent to them for review.',
            'pay' => 'Record payments against approved expenses.',
            'export' => 'Download the expense ledger.',
        ],
    ],

    'listings_management' => [
        'group' => 'listings_management',
        'module' => 'listings',
        'display_name' => 'Listings',
        'description' => 'One listing per unit type, published for renters (drafts are created with each property).',
        'permissions' => ['view', 'edit', 'publish'],
        'help' => [
            'view' => 'Open Listings and each listing.',
            'edit' => 'Set rent, deposit, description and photos.',
            'publish' => 'Publish and unpublish listings.',
        ],
    ],

    'account_settings' => [
        'group' => 'account_settings',
        'display_name' => 'Business settings',
        'description' => 'The business details and file storage (modules, payment methods and closing the account stay with the owner).',
        'permissions' => ['view', 'edit'],
        'help' => [
            'view' => 'Open Business settings and see storage use.',
            'edit' => 'Change the business details.',
        ],
    ],

    'subaccount_management' => [
        'group' => 'subaccount_management',
        'display_name' => 'Team members',
        'description' => 'The people who work in the business.',
        'permissions' => ['view', 'create', 'edit', 'delete'],
        'help' => [
            'view' => 'Open Team & roles and see the team.',
            'create' => 'Invite people and resend invitations.',
            'edit' => 'Change people\'s roles and properties, and suspend or reactivate them.',
            'delete' => 'Remove people.',
        ],
    ],

    'role_management' => [
        'group' => 'role_management',
        'display_name' => 'Roles & permissions',
        'description' => 'The roles people hold and what each role allows.',
        'permissions' => ['view', 'create', 'edit', 'delete'],
        'help' => [
            'view' => 'See roles and what each allows.',
            'create' => 'Create roles.',
            'edit' => 'Change roles and switch them on or off. People can only grant rights they hold themselves.',
            'delete' => 'Delete roles.',
        ],
    ],

    'activity_log' => [
        'group' => 'activity_log',
        'display_name' => 'Activity log',
        'description' => 'A record of who opened and changed what, and when, including sign-ins.',
        'permissions' => ['view', 'export'],
        'help' => [
            'view' => 'Open the Activity log for everyone in the business.',
            'export' => 'Download the activity log.',
        ],
    ],

];
