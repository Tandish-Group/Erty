<?php

/*
| Maintenance requests.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Maintenance\MaintenanceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/maintenance', [MaintenanceRequestController::class, 'index'])->name('maintenance.index');
Route::get('/maintenance/new', [MaintenanceRequestController::class, 'create'])->name('maintenance.create');
Route::get('/maintenance/units/{complex}', [MaintenanceRequestController::class, 'units'])->whereNumber('complex')->name('maintenance.units');
Route::post('/maintenance', [MaintenanceRequestController::class, 'store'])->name('maintenance.store');
Route::get('/maintenance/{reference}', [MaintenanceRequestController::class, 'show'])->where('reference', 'MR-[A-Z0-9]+')->name('maintenance.show');
Route::post('/maintenance/{reference}/action', [MaintenanceRequestController::class, 'act'])->where('reference', 'MR-[A-Z0-9]+')->name('maintenance.act');
