<?php

/*
| Dashboard and help.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Dashboard\DashboardController as Dashboard;
use App\Http\Controllers\Business\Dashboard\HelpController;
use Illuminate\Support\Facades\Route;

    Route::get('/business/faq', [HelpController::class, 'index'])->name('business.faq');

// The Business About and Contact pages were removed.

    Route::resource('dashboard', Dashboard::class)->only(['index'])
        ->middleware('business.dashboard');

    Route::post('/business/complete-walkthrough', [Dashboard::class, 'completeWalkthrough'])->name('business.completeWalkthrough');
