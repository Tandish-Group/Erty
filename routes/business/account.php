<?php

/*
| Account: personal profile, business settings, payment methods, storage, modules, notifications and
| the support tickets a person has posted.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Account\AccountsController;
use App\Http\Controllers\Business\Account\ActivityLogController;
use App\Http\Controllers\Business\Account\BusinessModuleController;
use App\Http\Controllers\Business\Account\MyTicketsController;
use App\Http\Controllers\Business\Account\NotificationController;
use App\Http\Controllers\Business\Account\ProfileController;
use App\Http\Controllers\Business\Account\StorageController;
use App\Http\Controllers\Business\Agents\MyAgentController;
use Illuminate\Support\Facades\Route;

// Personal profile (every team member); /business_account is the business-wide settings page
Route::get('/my-profile', [ProfileController::class, 'show'])->name('business.profile.show');
Route::put('/my-profile', [ProfileController::class, 'update'])->name('business.profile.update');
Route::post('/my-profile/agent', [ProfileController::class, 'createAgentProfile'])->name('business.profile.agent');
Route::get('/my-agent', [MyAgentController::class, 'show'])->name('business.my_agent');
Route::resource('business_account', AccountsController::class)->only(['index', 'update', 'destroy']);
Route::post('email_reset', [AccountsController::class, 'emailReset'])->name('emailReset');
Route::post('pass_reset', [AccountsController::class, 'passwordReset'])->name('passwordReset');
Route::get('/payment-methods/list',          [AccountsController::class, 'paymentMethodsList'])->name('business.payment-methods.list');
Route::post('/payment-methods/store',         [AccountsController::class, 'storePaymentMethod'])->name('business.payment-methods.store');
Route::put('/payment-methods/{id}/update',    [AccountsController::class, 'updatePaymentMethod'])->name('business.payment-methods.update');
Route::delete('/payment-methods/{id}',        [AccountsController::class, 'destroyPaymentMethod'])->name('business.payment-methods.destroy');
Route::post('/payment-methods/{id}/primary',  [AccountsController::class, 'setPrimaryPaymentMethod'])->name('business.payment-methods.primary');
Route::post('/payment-methods/{id}/toggle',   [AccountsController::class, 'togglePaymentMethod'])->name('business.payment-methods.toggle');

// Optional Erty modules the owner manages for their business (e.g. Listings).
Route::post('/business/modules', [BusinessModuleController::class, 'update'])->name('business.modules.update');
Route::get('/storage/overview', [StorageController::class, 'getOverview'])->name('business.storage.overview');
Route::post('/storage/recalculate', [StorageController::class, 'recalculate'])->name('business.storage.recalculate');
Route::get('/notifications/unread', [NotificationController::class, 'unread']);
Route::post('/notifications/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read_all');
Route::get('/notifications/{id}/open', [NotificationController::class, 'open'])->name('notifications.open');

// The activity log: who opened, changed or downloaded what, and when.
Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
Route::get('/activity/export', [ActivityLogController::class, 'export'])->name('activity.export');

// The support tickets a person has posted, and the replies on them.
Route::get('/support/tickets', [MyTicketsController::class, 'index'])->name('support.tickets.index');
Route::get('/support/tickets/{ticket:reference}', [MyTicketsController::class, 'show'])->name('support.tickets.show');
Route::post('/support/tickets/{ticket:reference}/reply', [MyTicketsController::class, 'reply'])->middleware('throttle:20,1')->name('support.tickets.reply');
Route::get('/support/tickets/{ticket:reference}/files/{attachment}', [MyTicketsController::class, 'file'])->whereNumber('attachment')->name('support.tickets.file');
