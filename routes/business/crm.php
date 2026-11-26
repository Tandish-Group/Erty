<?php

/*
| CRM: leads, document centre and reports (under /customer_relationship).
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Crm\CrmDashboardController;
use App\Http\Controllers\Business\Crm\CrmReportsController;
use App\Http\Controllers\Business\Crm\DocumentCenterController;
use App\Http\Controllers\Business\Crm\LeadManagementController;
use App\Http\Controllers\Business\Crm\LeadStatusController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'customer_relationship'], function () {

    // Customer Relationship Main
    Route::get('/', [CrmDashboardController::class, 'index'])->name('customer_relationship.index');

    // Lead Management
    Route::resource('/lead_management', LeadManagementController::class)->only(['index', 'store']);
    Route::get('/lead_profiles/search', [LeadManagementController::class, 'searchProfiles'])->name('lead_profiles.search');
    Route::get('/lead_management/onboarding/lead', [LeadManagementController::class, 'onboardLead']);
    Route::get('/leads/data', [LeadManagementController::class, 'getLeadsData'])->name('customer_relationship.lead_management.data');
    Route::get('/lead_management/lead/{id}', [LeadManagementController::class, 'show'])->name('lead_management.show');
    Route::redirect('/lead_management/{id}', '/customer_relationship/lead_management/lead/{id}', 301); // older links to a lead

    Route::post('/lead_management/{id}/update-status', [LeadStatusController::class, 'updateLeadStatus'])->name('lead_management.update_status');
    Route::post('/lead_management/{id}/confirm', [LeadStatusController::class, 'confirmDetails'])->name('lead_management.confirm');
    Route::post('/lead_management/{id}/add-interaction', [LeadStatusController::class, 'addInteraction'])->name('lead_management.add_interaction');

    // Agents moved to their own section (/agents); old links keep working.
    Route::redirect('/agent_management', '/agents', 301);
    Route::redirect('/agent_management/onboarding/agent', '/agents/onboarding', 301);
    Route::redirect('/agent_management/profile/{encryptedId}', '/agents/profile/{encryptedId}', 301);
    Route::redirect('/agent_management/lead-tasks/{encryptedLeadId}', '/agents/lead-tasks/{encryptedLeadId}', 301);
    Route::redirect('/agent-management-settings', '/agents/settings', 301);

    // Document Center
    Route::get('/document-center', [DocumentCenterController::class, 'index'])->name('document-center.index');
    Route::get('/document-center/files/{source}/{id}/{index}', [DocumentCenterController::class, 'file'])
        ->whereIn('source', ['tenancy', 'history', 'agent'])->whereNumber(['id', 'index'])->name('document-center.file');

    // Reports & Analytics
    Route::get('/reports', [CrmReportsController::class, 'index'])->name('crm.reports.index');

    // Lead Task Management Routes
    // Agent tasks moved to the Agents section (/agents/tasks).
    Route::redirect('/tasks', '/agents/tasks', 301);
    Route::get('/tasks/{id}', fn ($id) => redirect('/agents/tasks/' . $id, 301))->whereNumber('id');

});
