<?php

/*
| Listings module: one listing per unit type.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Listings\UnitListingController;
use Illuminate\Support\Facades\Route;

// Optional Listings module: one listing per unit type of a property (and per commercial space).
Route::group(['middleware' => 'module:listings'], function () {

Route::get('listings', [UnitListingController::class, 'index'])->name('listings.index');
Route::get('listings/{listing}/edit', [UnitListingController::class, 'edit'])->whereNumber('listing')->name('listings.edit');
Route::put('listings/{listing}', [UnitListingController::class, 'update'])->whereNumber('listing')->name('listings.update');
Route::post('listings/{listing}/publish', [UnitListingController::class, 'publish'])->whereNumber('listing')->name('listings.publish');
Route::post('listings/{listing}/unpublish', [UnitListingController::class, 'unpublish'])->whereNumber('listing')->name('listings.unpublish');
Route::post('listings/{listing}/photos', [UnitListingController::class, 'storePhoto'])->whereNumber('listing')->name('listings.photos.store');
Route::delete('listings/{listing}/photos/{photo}', [UnitListingController::class, 'destroyPhoto'])->whereNumber(['listing', 'photo'])->name('listings.photos.destroy');

}); // end module:listings (management)
