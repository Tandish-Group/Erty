<?php

/*
|--------------------------------------------------------------------------
| Business route access map
|--------------------------------------------------------------------------
| Every route in the business dashboard (role:business) resolves to one of:
|   - 'any_staff'   : every signed-in team member (own profile, notifications, help pages)
|   - 'owner_only'  : only the account owner (modules, storage, payment methods, account deletion)
|   - 'group.action': a permission from config/business_route_permissions.php
|
| Resolution order (App\Services\Access\BusinessAccessResolver):
|   1. 'routes'      Controller@method  => rule
|   2. 'controllers' Controller          => rule or group (action inferred from the method/verb)
| Unmapped routes are denied to staff. tests/Feature/BusinessAccessMapTest.php fails if any
| business route is unmapped or maps to an action its group does not define.
|
| Controller names are relative to App\Http\Controllers.
*/

return [

    'controllers' => [
        // Always available to the team
        'Business\Account\NotificationController' => 'any_staff',
        'Business\Account\MyTicketsController' => 'any_staff',       // own tickets only, checked in the controller
        'Business\Account\ActivityLogController' => 'activity_log',             // index: view, export: export
        'Business\Reports\ReportController' => 'any_staff',        // each report checks its own rights (ReportRegistry)
        'Business\Dashboard\HelpController' => 'any_staff',     // Help & FAQ
        'Closure' => 'any_staff',                              // old-URL redirects; the target is checked separately
        'Illuminate\Routing\RedirectController' => 'any_staff', // the target route is checked separately

        // Owner only: account-level settings
        'Business\Account\BusinessModuleController' => 'owner_only',
        'Business\Account\StorageController' => 'owner_only',

        // Dashboard
        'Business\Dashboard\DashboardController' => 'dashboard_access',

        // Properties
        'Business\Properties\ComplexController' => 'complex_management',
        'Business\Properties\PropertyImportController' => 'complex_management',
        'Business\Properties\PropertyLookupController' => 'complex_management',
        'Business\Properties\ComplexWorkflowController' => 'complex_management',
        'Business\Properties\ResidentialInventoryController' => 'complex_management',
        'Business\Properties\CommercialUnitController' => 'complex_management',
        'Business\Properties\PropertyFacilityController' => 'complex_management',
        'Business\Properties\ComplexFloorPlanController' => 'complex_management',
        'Business\Properties\ComplexHistoryController' => 'listing_history',
        'Business\Properties\ServiceChargeController' => 'service_charge_management',
        'Business\Expenses\ExpenseController' => 'expense_management',
        'Business\Expenses\PropertyExpenseWorkflowController' => 'expense_management',

        // Tenants
        'Business\Tenancies\TenantManagementController' => 'tenant_management',
        'Business\Tenancies\TenancyOnboardingController' => 'tenant_management',
        'Business\Tenancies\TenancyTableController' => 'tenant_management',
        'Business\Tenancies\TenantProfileController' => 'tenant_management',
        'Business\Tenancies\TenantImportController' => 'tenant_management',
        'Business\Tenancies\TenantExportController' => 'tenant_management',
        'Business\Tenancies\TenantLeaseController' => 'tenant_management',
        'Business\Tenancies\TenantBlacklistController' => 'tenant_management.edit',
        'Business\Tenancies\TenantMovementController' => 'tenant_management.transfers',
        'Business\Tenancies\TenantNoticeController' => 'tenant_management.notices',
        'Business\Rent\PaymentReconciliationController' => 'tenant_management.payments',
        'Business\Agents\AgentCommissionController' => 'agent_management',

        // CRM
        'Business\Crm\CrmDashboardController' => 'crm_management.view',
        'Business\Crm\DocumentCenterController' => 'crm_management.view',
        'Business\Crm\CrmReportsController' => 'crm_management.view',
        'Business\Crm\LeadManagementController' => 'lead_management',
        'Business\Crm\LeadStatusController' => 'lead_management',
        'Business\Agents\LeadTaskController' => 'task_management',
        'Business\Agents\LeadTaskActionController' => 'task_management',
        'Business\Agents\AgentManagementController' => 'agent_management',
        'Business\Agents\AgentLoginController' => 'agent_management',
        'Business\Agents\AgentSettingsController' => 'agent_management',

        // Legal
        'Business\Legal\LegalController' => 'legal_documents',

        // Team
        'Business\Team\BusinessRoleController' => 'role_management',

        // Listings module (also gated by module:listings)
        'Business\Listings\UnitListingController' => 'listings_management',

        // Sales (houses)
        'Business\Sales\PropertySaleController' => 'sales_management',
    ],

    'routes' => [
        // Every team member manages their own profile and sign-in details
        'Business\Dashboard\DashboardController@completeWalkthrough' => 'any_staff',
        'Business\Account\ProfileController@show' => 'any_staff',
        'Business\Account\ProfileController@update' => 'any_staff',
        'Business\Account\ProfileController@createAgentProfile' => 'agent_management.create',
        'Business\Agents\MyAgentController@show' => 'any_staff',
        // Unused resource stubs on the dashboard controller
        'Business\Account\AccountsController@emailReset' => 'any_staff',
        'Business\Account\AccountsController@passwordReset' => 'any_staff',

        // Business settings (business details and storage); modules, payment methods and deletion stay owner-only
        'Business\Account\AccountsController@index' => 'account_settings.view',
        'Business\Account\AccountsController@update' => 'account_settings.edit',
        'Business\Account\StorageController@getOverview' => 'account_settings.view',

        // Account owner only
        'Business\Account\AccountsController@destroy' => 'owner_only',
        'Business\Account\AccountsController@paymentMethodsList' => 'owner_only',
        'Business\Account\AccountsController@storePaymentMethod' => 'owner_only',
        'Business\Account\AccountsController@updatePaymentMethod' => 'owner_only',
        'Business\Account\AccountsController@destroyPaymentMethod' => 'owner_only',
        'Business\Account\AccountsController@setPrimaryPaymentMethod' => 'owner_only',
        'Business\Account\AccountsController@togglePaymentMethod' => 'owner_only',

        // Team members
        'Business\Team\TeamController@index' => 'subaccount_management.view',
        'Business\Team\TeamController@invite' => 'subaccount_management.create',
        'Business\Team\TeamController@update' => 'subaccount_management.edit',
        'Business\Team\TeamController@resendInvite' => 'subaccount_management.create',
        'Business\Team\TeamController@suspend' => 'subaccount_management.edit',
        'Business\Team\TeamController@reactivate' => 'subaccount_management.edit',
        'Business\Team\TeamController@destroy' => 'subaccount_management.delete',
        'Business\Team\TeamController@searchProperties' => 'complex_management.assign',
        'Business\Team\BusinessRoleController@getTemplate' => 'role_management.view',
        'Business\Team\BusinessRoleController@toggle' => 'role_management.edit',

        // Properties
        'Business\Properties\ComplexController@confirmSetup' => 'complex_management.edit',
        'Business\Properties\ComplexManagerController@update' => 'complex_management.assign',
        // Lead agents assign their property's units too; the controller checks who may assign whom.
        'Business\Properties\ComplexManagerController@unitAgents' => 'any_staff',
        // Who manages the property: administrators decide (checked again in AgentAssignmentService::setManagement).
        'Business\Properties\ComplexManagerController@management' => 'complex_management.assign',
        'Business\Properties\ComplexManagerController@history' => 'complex_management.view',
        'Business\Properties\PropertyLookupController@getResidentialUnitsForTenant' => 'tenant_management.view',
        'Business\Properties\PropertyLookupController@getParkingSlots' => 'tenant_management.view',
        'Business\Properties\PropertyLookupController@getComplexAmenities' => 'complex_management.view',
        'Business\Properties\PropertyLookupController@commercialSpaces' => 'lead_management.view',
        'Business\Properties\PropertyImportController@dataDownloadComplexes' => 'complex_management.export',
        'Business\Properties\PropertyImportController@downloadComplex' => 'complex_management.import',
        'Business\Properties\PropertyImportController@complex_upload' => 'complex_management.import',
        'Business\Properties\ComplexWorkflowController@draft' => 'complex_management.create',
        'Business\Properties\CommercialUnitController@unit_upload' => 'complex_management.import',
        'Business\Properties\CommercialUnitController@downloadUnitSample' => 'complex_management.import',
        'Business\Properties\CommercialUnitController@getComplexInfo' => 'complex_management.view',
        'Business\Properties\PropertyFacilityController@bulkDestroy' => 'complex_management.delete',
        'Business\Properties\ComplexFloorPlanController@complex_bulk_delete' => 'complex_management.edit',
        'Business\Properties\ComplexFloorPlanController@store' => 'complex_management.edit',
        'Business\Properties\ComplexFloorPlanController@destroy' => 'complex_management.edit',
        'Business\Properties\PropertyLookupController@searchForHistory' => 'listing_history.view',
        'Business\Properties\PropertyLookupController@getComplexUnits' => 'listing_history.view',
        'Business\Properties\ComplexHistoryController@destroy' => 'listing_history.create',
        'Business\Properties\ComplexHistoryController@create' => 'listing_history.create',
        'Business\Properties\ComplexHistoryController@show' => 'listing_history.view',
        'Business\Properties\ServiceChargeController@deleteServiceCharge' => 'service_charge_management.delete',
        'Business\Properties\ServiceChargeController@service_charges_upload' => 'service_charge_management.import',
        'Business\Properties\ServiceChargeController@downloadServiceChargesSample' => 'service_charge_management.import',
        'Business\Properties\ServiceChargeController@serviceCharges' => 'service_charge_management.view',

        // Expenses (the workflow controller also checks approve/pay per action)
        'Business\Expenses\ExpenseController@getData' => 'expense_management.view',
        'Business\Expenses\ExpenseController@getComplexUnits' => 'expense_management.view',
        'Business\Expenses\ExpenseController@exportLedger' => 'expense_management.export',
        'Business\Expenses\ExpenseController@updateExpense' => 'expense_management.edit',
        'Business\Expenses\ExpenseController@destroyExpense' => 'expense_management.delete',
        'Business\Expenses\PropertyExpenseWorkflowController@act' => 'expense_management.edit',
        'Business\Expenses\PropertyExpenseWorkflowController@upload' => 'expense_management.edit',
        'Business\Expenses\PropertyExpenseWorkflowController@download' => 'expense_management.view',

        // Tenants
        // Table data loads by POST (DataTables) but only reads.
        'Business\Tenancies\TenancyTableController@getTenantsData' => 'tenant_management.view',
        'Business\Tenancies\TenancyOnboardingController@getExistingTenants' => 'tenant_management.view',
        'Business\Tenancies\TenancyOnboardingController@onboardTenant' => 'tenant_management.create',
        'Business\Tenancies\TenantManagementController@commercialUnitsForTenant' => 'tenant_management.create',
        'Business\Tenancies\TenantManagementController@recordPayment' => 'tenant_management.payments',
        // Tenant account: viewing is part of the tenancy; money and charges need the payments/billing permissions
        'Business\Rent\TenantAccountController@summary' => 'tenant_management.view',
        'Business\Rent\TenantAccountController@statementPdf' => 'tenant_management.view',
        'Business\Rent\TenantAccountController@receipt' => 'tenant_management.payments',
        'Business\Rent\TenantAccountController@recordPayment' => 'tenant_management.payments',
        'Business\Rent\TenantAccountController@confirm' => 'tenant_management.payments',
        'Business\Rent\TenantAccountController@reverse' => 'tenant_management.payments',
        'Business\Rent\TenantAccountController@refund' => 'tenant_management.payments',
        'Business\Rent\TenantAccountController@charge' => 'tenant_management.billing',
        'Business\Rent\TenantAccountController@void' => 'tenant_management.billing',
        // Follow-ups on overdue rent: seen with the tenancy; logging also needs create (agents, managers) or payments (finance), checked in the controller
        'Business\Rent\TenantAccountController@storeFollowUp' => 'tenant_management.view',
        'Business\Rent\TenantAccountController@updatePromise' => 'tenant_management.view',
        'Business\Sales\PropertySaleController@index' => 'sales_management.view',
        'Business\Sales\PropertySaleController@show' => 'sales_management.view',
        'Business\Sales\PropertySaleController@receipt' => 'sales_management.view',
        'Business\Sales\PropertySaleController@statement' => 'sales_management.view',
        'Business\Sales\PropertySaleController@create' => 'sales_management.create',
        'Business\Sales\PropertySaleController@store' => 'sales_management.create',
        'Business\Sales\PropertySaleController@recordPayment' => 'sales_management.payments',
        'Business\Sales\PropertySaleController@reversePayment' => 'sales_management.payments',
        'Business\Sales\PropertySaleController@complete' => 'sales_management.complete',
        'Business\Sales\PropertySaleController@titleTransferred' => 'sales_management.complete',
        'Business\Sales\PropertySaleController@cancel' => 'sales_management.cancel',
        // Maintenance requests: assigning, starting, completing and closing (act) are checked against "manage" in the controller
        'Business\Maintenance\MaintenanceRequestController@index' => 'maintenance_management.view',
        'Business\Maintenance\MaintenanceRequestController@show' => 'maintenance_management.view',
        'Business\Maintenance\MaintenanceRequestController@create' => 'maintenance_management.log',
        'Business\Maintenance\MaintenanceRequestController@units' => 'maintenance_management.log',
        'Business\Maintenance\MaintenanceRequestController@store' => 'maintenance_management.log',
        'Business\Maintenance\MaintenanceRequestController@act' => 'maintenance_management.view',
        'Business\Tenancies\TenancyTableController@getTenantBillings' => 'tenant_management.billing',
        'Business\Tenancies\TenancyTableController@getTenantInvoices' => 'tenant_management.billing',
        'Business\Tenancies\TenantProfileController@paymentHistory' => 'tenant_management.view',
        'Business\Tenancies\TenantExportController@customExport' => 'tenant_management.export',
        'Business\Tenancies\TenantProfileController@updateTenantProfile' => 'tenant_management.edit',
        'Business\Tenancies\TenantManagementController@updateTenancy' => 'tenant_management.edit',
        'Business\Tenancies\TenantImportController@showMappingPage' => 'tenant_management.import',
        'Business\Tenancies\TenantImportController@saveMappingData' => 'tenant_management.import',
        'Business\Tenancies\TenantImportController@cancelMappingData' => 'tenant_management.import',
        'Business\Tenancies\TenantImportController@processBulkUpload' => 'tenant_management.import',
        'Business\Tenancies\TenantImportController@downloadTenantBulkUploadTemplate' => 'tenant_management.import',
        'Business\Tenancies\TenantImportController@downloadPaymentBulkUploadTemplate' => 'tenant_management.import',
        'Business\Tenancies\TenantImportController@downloadNoticeBulkUploadTemplate' => 'tenant_management.import',
        // The bulk-upload review screens show every staged row of the business: import rights only.
        'Business\Tenancies\TenantNoticeController@getTerminationNotices' => 'tenant_management.view',
        'Business\Tenancies\TenantMovementController@index' => 'tenant_management.view',
        'Business\Tenancies\TenantMovementController@list' => 'tenant_management.view',
        'Business\Tenancies\TenantMovementController@show' => 'tenant_management.view',
        'Business\Agents\AgentCommissionController@export' => 'agent_management.view',
        'Business\Agents\AgentCommissionController@saveAgreement' => 'agent_management.edit',
        'Business\Agents\AgentCommissionController@endAgreement' => 'agent_management.edit',
        'Business\Agents\AgentCommissionController@calculatePeriod' => 'agent_management.edit',
        'Business\Agents\AgentCommissionController@approve' => 'agent_management.edit',
        'Business\Agents\AgentCommissionController@statement' => 'agent_management.view',
        'Business\Agents\AgentCommissionController@recordPayout' => 'agent_management.edit',

        // CRM
        'Business\Crm\LeadManagementController@onboardLead' => 'lead_management.create',
        'Business\Crm\LeadManagementController@searchProfiles' => 'lead_management.view',
        'Business\Crm\LeadStatusController@addInteraction' => 'lead_management.edit',
        'Business\Crm\LeadStatusController@confirmDetails' => 'lead_management.update_status',
        'Business\Crm\LeadStatusController@updateLeadStatus' => 'lead_management.update_status',
        'Business\Agents\LeadTaskActionController@start' => 'task_management.complete',
        'Business\Agents\LeadTaskActionController@complete' => 'task_management.complete',
        'Business\Agents\LeadTaskActionController@markBlocked' => 'task_management.edit',
        'Business\Agents\LeadTaskActionController@skip' => 'task_management.skip',
        'Business\Agents\AgentManagementController@inviteAgent' => 'agent_management.invite',
        'Business\Agents\AgentLoginController@linkCandidates' => 'agent_management.edit',
        'Business\Agents\AgentLoginController@linkLogin' => 'agent_management.edit',
        'Business\Agents\AgentLoginController@unlinkLogin' => 'agent_management.edit',
        'Business\Agents\AgentLoginController@inviteLogin' => 'agent_management.edit',
        'Business\Agents\AgentManagementController@updateHierarchy' => 'agent_management.edit',
        'Business\Agents\AgentManagementController@onboardAgent' => 'agent_management.create',
        'Business\Agents\AgentManagementController@updateStatus' => 'agent_management.edit',
        'Business\Agents\AgentSettingsController@handleSettings' => 'agent_management.edit',
        'Business\Agents\AgentSettingsController@agentSettings' => 'agent_management.view',
        // Read-only list of the business's active agents for pickers (properties, leads, tenancies).
        'Business\Agents\AgentManagementController@searchAgents' => 'any_staff',

        // Legal
        'Business\Legal\LegalController@legal_document_upload' => 'legal_documents.create',
        'Business\Legal\LegalController@legal_document_move' => 'legal_documents.edit',
        'Business\Legal\LegalController@legal_folder_updated' => 'legal_documents.edit',
        'Business\Legal\LegalController@legal_document_download' => 'legal_documents.download',
        'Business\Legal\LegalController@legal_folder_download' => 'legal_documents.download',
        'Business\Legal\LegalController@destroyFIle' => 'legal_documents.delete',

        // Listings module
        'Business\Listings\UnitListingController@index' => 'listings_management.view',
        'Business\Listings\UnitListingController@edit' => 'listings_management.view',
        'Business\Listings\UnitListingController@update' => 'listings_management.edit',
        'Business\Listings\UnitListingController@storePhoto' => 'listings_management.edit',
        'Business\Listings\UnitListingController@destroyPhoto' => 'listings_management.edit',
        'Business\Listings\UnitListingController@publish' => 'listings_management.publish',
        'Business\Listings\UnitListingController@unpublish' => 'listings_management.publish',
    ],

    // Where a team member lands when they cannot open the dashboard: the first section they may view.
    'landing' => [
        'dashboard_access' => '/dashboard',
        'complex_management' => '/complex',
        'tenant_management' => '/tenant_management',
        'crm_management' => '/customer_relationship',
        'lead_management' => '/customer_relationship/lead_management',
        'task_management' => '/agents/tasks',
        'agent_management' => '/agents',
        'expense_management' => '/complex-expenses',
        'service_charge_management' => '/service-charges',
        'listing_history' => '/history',
        'legal_documents' => '/legal',
        'maintenance_management' => '/maintenance',
        'reports' => '/reports',
        'activity_log' => '/activity',
        'subaccount_management' => '/business_account/team',
        'account_settings' => '/business_account',
    ],
    // Everyone can open their own profile, so it is the last place a team member can land.
    'fallback_landing' => '/my-profile',
];
