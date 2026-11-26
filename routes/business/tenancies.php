<?php

/*
| Tenancies, tenant accounts and rent, reconciliation, reports, leases, notices and moves (all under /tenant_management).
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Rent\PaymentReconciliationController;
use App\Http\Controllers\Business\Rent\TenantAccountController;
use App\Http\Controllers\Business\Tenancies\TenantBlacklistController;
use App\Http\Controllers\Business\Tenancies\TenantLeaseController;
use App\Http\Controllers\Business\Tenancies\TenantManagementController;
use App\Http\Controllers\Business\Tenancies\TenancyOnboardingController;
use App\Http\Controllers\Business\Tenancies\TenancyTableController;
use App\Http\Controllers\Business\Tenancies\TenantExportController;
use App\Http\Controllers\Business\Tenancies\TenantImportController;
use App\Http\Controllers\Business\Tenancies\TenantProfileController;
use App\Http\Controllers\Business\Tenancies\TenantMovementController;
use App\Http\Controllers\Business\Tenancies\TenantNoticeController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'tenant_management'], function () {

    Route::get('/', [TenantManagementController::class, 'index'])->name('tenant_management.index');
    Route::post('/', [TenancyOnboardingController::class, 'store'])->name('tenant_management.store');
    Route::get('/{id}', [TenantManagementController::class, 'show'])->whereNumber('id')->name('tenant_management.show');
    Route::delete('/{id}', [TenantManagementController::class, 'destroy'])->whereNumber('id')->name('tenant_management.destroy');
    Route::get('/onboarding/tenant', [TenancyOnboardingController::class, 'onboardTenant'])->name('tenant_management.onboarding');
    Route::get('/complex-occupancy/data', [TenantManagementController::class, 'getComplexOccupancyData'])->name('tenant_management.complex_occupancy_data');

    // View tenant details
    Route::get('/tenancy/{ref}/view', [TenantProfileController::class, 'tenancyView']);
    Route::post('/tenancy/{id}/update', [TenantManagementController::class, 'updateTenancy'])->whereNumber('id')->name('tenant_management.update_tenancy');
    Route::post('/data', [TenancyTableController::class, 'getTenantsData']);

    // View tenant profile
    Route::get('/tenant/{ref}/view', [TenantProfileController::class, 'tenantView']);
    Route::match(['post', 'put'], '/tenant/{ref}/update', [TenantProfileController::class, 'updateTenantProfile'])->name('tenant_management.update_tenant_profile');

    // Edit tenant details
    Route::get('/edit/{ref}/tenant', [TenantProfileController::class, 'edit']);
    Route::get('/{id}/edit-data', [TenantProfileController::class, 'getEditData'])->name('tenant_management.edit_data');
    Route::get('/complex/{slug}/commercial-units', [TenantManagementController::class, 'commercialUnitsForTenant'])->name('tenant_management.commercial_units');

    // Search tenants
    Route::get('/search/tenants', [TenancyOnboardingController::class, 'searchTenants'])->name('search.tenants');

    // Tenant actions
    Route::post('/give-notice', [TenantNoticeController::class, 'giveNotice'])->name('tenant.give-notice');
    Route::get('/tenant/{tenantId}/termination-notices', [TenantNoticeController::class, 'getTerminationNotices'])->name('tenant.termination-notices');
    Route::post('/record-payment', [TenantManagementController::class, 'recordPayment'])->name('tenant.record-payment');

    // Tenant account: payments, credit, deposit, charges and the statement
    Route::prefix('account')->group(function () {
        Route::get('/{tenancy}/summary', [TenantAccountController::class, 'summary'])->whereNumber('tenancy')->name('tenant_account.summary');
        Route::post('/{tenancy}/payments', [TenantAccountController::class, 'recordPayment'])->whereNumber('tenancy')->name('tenant_account.record');
        Route::post('/{tenancy}/refund', [TenantAccountController::class, 'refund'])->whereNumber('tenancy')->name('tenant_account.refund');
        Route::post('/{tenancy}/charges', [TenantAccountController::class, 'charge'])->whereNumber('tenancy')->name('tenant_account.charge');
        Route::get('/{tenancy}/statement.pdf', [TenantAccountController::class, 'statementPdf'])->whereNumber('tenancy')->name('tenant_account.statement_pdf');
        Route::post('/payments/{payment}/confirm', [TenantAccountController::class, 'confirm'])->whereNumber('payment')->name('tenant_account.confirm');
        Route::post('/payments/{payment}/reverse', [TenantAccountController::class, 'reverse'])->whereNumber('payment')->name('tenant_account.reverse');
        Route::get('/payments/{payment}/receipt', [TenantAccountController::class, 'receipt'])->whereNumber('payment')->name('tenant_account.receipt');
        Route::post('/invoices/{invoice}/void', [TenantAccountController::class, 'void'])->whereNumber('invoice')->name('tenant_account.void');
        Route::post('/{tenancy}/follow-ups', [TenantAccountController::class, 'storeFollowUp'])->whereNumber('tenancy')->name('tenant_account.follow_up');
        Route::post('/follow-ups/{followUp}/promise', [TenantAccountController::class, 'updatePromise'])->whereNumber('followUp')->name('tenant_account.follow_up.promise');
    });

    Route::get('/{tenant}/payments', [TenantProfileController::class, 'paymentHistory'])->name('tenant.payment-history');
    Route::get('/{tenant}/notices', [TenantProfileController::class, 'noticeHistory'])->name('tenant.notice-history');
    Route::post('/existing-tenants', [TenancyOnboardingController::class, 'getExistingTenants'])->name('tenant.existing');
    Route::post('/end-tenancy', [TenantNoticeController::class, 'endTenancy'])->name('tenant_management.end_tenancy');
    Route::get('/end-tenancy/preview', [TenantNoticeController::class, 'settlementPreview'])->name('tenant_management.end_tenancy.preview');

    // Tenant Profiles DataTable
    Route::get('/tenant-profiles/data', [TenancyTableController::class, 'getTenantProfilesData'])->name('tenant_management.tenant_profiles.data');

    // Bulk upload interface
    Route::post('/bulk-upload/process', [TenantImportController::class, 'processBulkUpload']);

    // Bulk upload routes

    // Template downloads
    Route::get('/bulk-upload/template/tenants', [TenantImportController::class, 'downloadTenantBulkUploadTemplate']);
    Route::get('/bulk-upload/template/payments', [TenantImportController::class, 'downloadPaymentBulkUploadTemplate']);
    Route::get('/bulk-upload/template/notices', [TenantImportController::class, 'downloadNoticeBulkUploadTemplate']);

    // Export routes
    Route::post('/export/custom', [TenantExportController::class, 'customExport'])->name('tenant_management.export.custom');

    // Mapping page
    Route::get('/bulk-upload/mapping', [TenantImportController::class, 'showMappingPage']);

    // Mapping Data Handler
    Route::post('/bulk-upload/confirm', [TenantImportController::class, 'saveMappingData']);
    Route::post('/bulk-upload/cancel', [TenantImportController::class, 'cancelMappingData']);

    // Invoice routes
    Route::get('/{id}/invoices', [TenancyTableController::class, 'getTenantInvoices'])->name('tenant_management.invoices');

    // Billing routes
    Route::get('/billings', [TenancyTableController::class, 'getTenantBillings'])->name('tenant_management.billings');

    // Agent commissions moved to the Agents section.
    Route::redirect('/agent-commissions', '/agents/commissions', 301);

    // Payment Reconciliation Routes
    Route::get('/payment-reconciliation', [PaymentReconciliationController::class, 'index'])->name('tenant_management.reconciliation');
    Route::get('/payment-reconciliation/unmatched-payments', [PaymentReconciliationController::class, 'getUnmatchedPayments'])->name('tenant_management.reconciliation.unmatched');
    Route::get('/payment-reconciliation/staged-receipts', [PaymentReconciliationController::class, 'getStagedReceipts'])->name('tenant_management.reconciliation.staged');
    Route::post('/payment-reconciliation/upload', [PaymentReconciliationController::class, 'uploadReceipt'])->name('tenant_management.reconciliation.upload');
    Route::post('/payment-reconciliation/match', [PaymentReconciliationController::class, 'matchReceiptToPayment'])->name('tenant_management.reconciliation.match');
    Route::post('/payment-reconciliation/unmatch/{paymentId}', [PaymentReconciliationController::class, 'unmatch'])->name('tenant_management.reconciliation.unmatch');
    Route::delete('/payment-reconciliation/receipts/{id}', [PaymentReconciliationController::class, 'deleteStagedReceipt'])->name('tenant_management.reconciliation.delete_receipt');
    Route::get('/payment-reconciliation/recent-matches', [PaymentReconciliationController::class, 'getRecentMatches'])->name('tenant_management.reconciliation.recent');

    // Tenant Blacklist Routes
    Route::post('/tenant/{ref}/blacklist', [TenantBlacklistController::class, 'blacklist'])->name('tenant.blacklist');
    Route::post('/tenant/{ref}/unblacklist', [TenantBlacklistController::class, 'unblacklist'])->name('tenant.unblacklist');

    // Tenant Lease Routes
    Route::get('/leases/data', [TenancyTableController::class, 'getLeasesData'])->name('tenant_management.leases_data');
    Route::get('/lease/create/{tenantManagementId}', [TenantLeaseController::class, 'create'])->name('tenant_lease.create');
    Route::post('/lease/store', [TenantLeaseController::class, 'store'])->name('tenant_lease.store');
    Route::get('/lease/edit/{id}', [TenantLeaseController::class, 'edit'])->name('tenant_lease.edit');
    Route::put('/lease/update/{id}', [TenantLeaseController::class, 'update'])->name('tenant_lease.update');
    Route::get('/lease/{tenantLease}', [TenantLeaseController::class, 'show'])->name('tenant_lease.show');
    Route::delete('/lease/{tenantLease}', [TenantLeaseController::class, 'destroy'])->name('tenant_lease.destroy');

    // All Tenants Page
    Route::get('/tenants', [TenantManagementController::class, 'allTenants'])->name('tenant_management.all_tenants');

    // Tenant Movement Routes
    Route::prefix('movement')->group(function () {
        Route::get('/', [TenantMovementController::class, 'index'])->name('tenant_movement.index');
        Route::get('/create', [TenantMovementController::class, 'create'])->name('tenant_movement.create');
        Route::post('/preview', [TenantMovementController::class, 'preview'])->name('tenant_movement.preview');
        Route::post('/initiate', [TenantMovementController::class, 'initiate'])->name('tenant_movement.store');
        Route::post('/{ref}/approve', [TenantMovementController::class, 'approve'])->name('tenant_movement.approve');
        Route::post('/{ref}/cancel', [TenantMovementController::class, 'cancel'])->name('tenant_movement.cancel');
        Route::get('/{ref}', [TenantMovementController::class, 'show'])->name('tenant_movement.show');
    });
});
