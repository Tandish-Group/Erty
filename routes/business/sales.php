<?php

/*
| Sales module: house sales.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Sales\PropertySaleController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => 'module:sales'], function () {
    $sale = PropertySaleController::class;
    $ref = 'SAL-[0-9]{6}-[A-Z0-9]{4}';
    Route::get('/sales', [$sale, 'index'])->name('sales.index');
    Route::get('/sales/create', [$sale, 'create'])->name('sales.create');
    Route::post('/sales', [$sale, 'store'])->name('sales.store');
    Route::get('/sales/{reference}', [$sale, 'show'])->where('reference', $ref)->name('sales.show');
    Route::get('/sales/{reference}/statement', [$sale, 'statement'])->where('reference', $ref)->name('sales.statement');
    Route::get('/sales/{reference}/receipts/{payment}', [$sale, 'receipt'])->where('reference', $ref)->whereNumber('payment')->name('sales.receipt');
    Route::post('/sales/{reference}/payments', [$sale, 'recordPayment'])->where('reference', $ref)->name('sales.payments.store');
    Route::post('/sales/{reference}/payments/{payment}/reverse', [$sale, 'reversePayment'])->where('reference', $ref)->whereNumber('payment')->name('sales.payments.reverse');
    Route::post('/sales/{reference}/complete', [$sale, 'complete'])->where('reference', $ref)->name('sales.complete');
    Route::post('/sales/{reference}/title', [$sale, 'titleTransferred'])->where('reference', $ref)->name('sales.title');
    Route::post('/sales/{reference}/cancel', [$sale, 'cancel'])->where('reference', $ref)->name('sales.cancel');
});
