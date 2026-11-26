<?php

/*
| Team members and business roles.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Team\BusinessRoleController;
use App\Http\Controllers\Business\Team\TeamController;
use Illuminate\Support\Facades\Route;

// Team & roles (before the business_account resource so "team" is not read as an ID)
Route::get('/business_account/team', [TeamController::class, 'index'])->name('business.team.index');
Route::post('/business_account/team/invite', [TeamController::class, 'invite'])->name('business.team.invite');
Route::get('/business_account/team/properties', [TeamController::class, 'searchProperties'])->name('business.team.properties');
Route::put('/business_account/team/{id}', [TeamController::class, 'update'])->whereNumber('id')->name('business.team.update');
Route::post('/business_account/team/{id}/resend', [TeamController::class, 'resendInvite'])->whereNumber('id')->name('business.team.resend');
Route::post('/business_account/team/{id}/suspend', [TeamController::class, 'suspend'])->whereNumber('id')->name('business.team.suspend');
Route::post('/business_account/team/{id}/reactivate', [TeamController::class, 'reactivate'])->whereNumber('id')->name('business.team.reactivate');
Route::delete('/business_account/team/{id}', [TeamController::class, 'destroy'])->whereNumber('id')->name('business.team.destroy');
Route::group(['prefix' => 'business-roles', 'middleware' => ['auth']], function () {

    Route::get('/list', [BusinessRoleController::class, 'index'])->name('business-roles.list');
    Route::post('/store', [BusinessRoleController::class, 'store'])->name('business-roles.store');
    Route::get('/template/{key}', [BusinessRoleController::class, 'getTemplate'])->name('business-roles.template');
    Route::get('/{id}', [BusinessRoleController::class, 'show'])->name('business-roles.show');
    Route::put('/{id}', [BusinessRoleController::class, 'update'])->name('business-roles.update');
    Route::delete('/{id}', [BusinessRoleController::class, 'destroy'])->name('business-roles.destroy');
    Route::patch('/{id}/toggle', [BusinessRoleController::class, 'toggle'])->name('business-roles.toggle');

});
