<?php

use App\Http\Controllers\Api\V1\ListingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefix /api, "api" middleware group)
|--------------------------------------------------------------------------
|
| Public, read-only endpoints for a renter-facing site or app. No sign-in and
| no personal data; rate limited per IP address (config/listings.php).
|
*/

Route::prefix('v1')->group(function () {
    Route::get('listings', [ListingController::class, 'index'])->name('api.v1.listings.index');
    Route::get('listings/{ref}', [ListingController::class, 'show'])->where('ref', '[A-Za-z0-9]{1,32}')->name('api.v1.listings.show');
});
