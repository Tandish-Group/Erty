<?php

/*
| Reports: the business overview and each report, on screen or downloaded (?format=xlsx|pdf).
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
| Each report checks its own rights; see App\Services\Reports\ReportRegistry.
*/

use App\Http\Controllers\Business\Reports\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/{key}', [ReportController::class, 'show'])->where('key', '[a-z-]+')->name('reports.show');
