<?php

/*
| Agents: directory, onboarding, commissions, tasks and sign-in links.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Agents\AgentCommissionController;
use App\Http\Controllers\Business\Agents\AgentLoginController;
use App\Http\Controllers\Business\Agents\AgentManagementController;
use App\Http\Controllers\Business\Agents\AgentSettingsController;
use App\Http\Controllers\Business\Agents\LeadTaskActionController;
use App\Http\Controllers\Business\Agents\LeadTaskController;
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'agents'], function () {
    Route::get('/', [AgentManagementController::class, 'index'])->name('agent_management.index');
    Route::post('/', [AgentManagementController::class, 'store'])->name('agent_management.store');
    Route::get('/onboarding', [AgentManagementController::class, 'onboardAgent'])->name('agent_management.onboarding');
    Route::get('/data', [AgentManagementController::class, 'getAgentsData'])->name('customer_relationship.agents.data');
    Route::get('/search', [AgentManagementController::class, 'searchAgents'])->name('agents.search');
    Route::post('/invite', [AgentManagementController::class, 'inviteAgent'])->name('agent-management.invite');
    Route::get('/settings', [AgentSettingsController::class, 'agentSettings'])->name('agent_management.settings');
    Route::match(['get', 'post'], '/settings/handle', [AgentSettingsController::class, 'handleSettings'])->name('agent-management.settings.handle');
    Route::match(['put', 'patch', 'delete'], '/settings/{id}', [AgentSettingsController::class, 'handleSettings'])->name('agent-management.settings.modify');
    Route::get('/commissions', [AgentCommissionController::class, 'index'])->name('tenant_management.agent_commissions');
    Route::get('/commissions/export', [AgentCommissionController::class, 'export'])->name('tenant_management.agent_commissions.export');
    Route::post('/commissions/agreements', [AgentCommissionController::class, 'saveAgreement'])->name('tenant_management.agent_commissions.agreements.save');
    Route::post('/commissions/agreements/{agreement}/end', [AgentCommissionController::class, 'endAgreement'])->whereNumber('agreement')->name('tenant_management.agent_commissions.agreements.end');
    Route::post('/commissions/periods', [AgentCommissionController::class, 'calculatePeriod'])->name('tenant_management.agent_commissions.periods');
    Route::post('/commissions/approve', [AgentCommissionController::class, 'approve'])->name('tenant_management.agent_commissions.approve');
    Route::get('/commissions/{agentId}/statement', [AgentCommissionController::class, 'statement'])->whereNumber('agentId')->name('tenant_management.agent_commissions.statement');
    Route::post('/commissions/{agentId}/payouts', [AgentCommissionController::class, 'recordPayout'])->whereNumber('agentId')->name('tenant_management.agent_commissions.payouts');
    Route::get('/lead-tasks/{encryptedLeadId}', [AgentManagementController::class, 'showLeadTasks'])->name('agent_management.lead_tasks');
    Route::get('/profile/{encryptedId}', [AgentManagementController::class, 'showProfile'])->name('agent_management.profile');
    Route::post('/{encryptedId}/status', [AgentManagementController::class, 'updateStatus'])->name('agent_management.update_status');

    // Agent tasks (created from the lead workflow; see "How tasks are assigned")
    Route::group(['prefix' => 'tasks'], function () {
        Route::get('/', [LeadTaskController::class, 'index'])->name('tasks.index');
        Route::get('/data', [LeadTaskController::class, 'getData'])->name('tasks.data');
        Route::get('/upcoming', [LeadTaskController::class, 'getUpcoming'])->name('tasks.upcoming');
        Route::get('/lead/{leadId}', [LeadTaskController::class, 'getLeadTasks'])->name('tasks.lead-tasks');
        Route::post('/', [LeadTaskActionController::class, 'store'])->name('tasks.store');
        Route::get('/{id}', [LeadTaskController::class, 'show'])->whereNumber('id')->name('tasks.show');
        Route::post('/{id}/start', [LeadTaskActionController::class, 'start'])->whereNumber('id')->name('tasks.start');
        Route::post('/{id}/complete', [LeadTaskActionController::class, 'complete'])->whereNumber('id')->name('tasks.complete');
        Route::post('/{id}/block', [LeadTaskActionController::class, 'markBlocked'])->whereNumber('id')->name('tasks.block');
        Route::post('/{id}/skip', [LeadTaskActionController::class, 'skip'])->whereNumber('id')->name('tasks.skip');
    });

    // Sign-in link between an agent and a team member
    Route::get('/profile/{encryptedId}/link-candidates', [AgentLoginController::class, 'linkCandidates'])->name('agent_management.link_candidates');
    Route::post('/profile/{encryptedId}/link', [AgentLoginController::class, 'linkLogin'])->name('agent_management.link');
    Route::post('/profile/{encryptedId}/unlink', [AgentLoginController::class, 'unlinkLogin'])->name('agent_management.unlink');
    Route::post('/profile/{encryptedId}/invite-login', [AgentLoginController::class, 'inviteLogin'])->name('agent_management.invite_login');
    Route::post('/profile/{encryptedId}/hierarchy', [AgentManagementController::class, 'updateHierarchy'])->name('agent_management.hierarchy');
});
