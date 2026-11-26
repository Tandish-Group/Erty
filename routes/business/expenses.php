<?php

/*
| Property expenses.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Expenses\ExpenseController;
use App\Http\Controllers\Business\Expenses\PropertyExpenseWorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/complex-expenses',                [ExpenseController::class, 'index'])->name('business.expenses.index');
Route::get('/complex-expenses/create',         [ExpenseController::class, 'create'])->name('business.expenses.create');
Route::get('/complex-expenses/data',           [ExpenseController::class, 'getData'])->name('business.expenses.data');
Route::get('/complex-expenses/export-ledger', [ExpenseController::class, 'exportLedger'])->name('business.expenses.export-ledger');
Route::get('/complex-expenses/{id}/workflow', [PropertyExpenseWorkflowController::class, 'show'])->name('business.expenses.workflow');
Route::post('/complex-expenses/{id}/workflow', [PropertyExpenseWorkflowController::class, 'act'])->name('business.expenses.workflow.act');
Route::post('/complex-expenses/{id}/evidence', [PropertyExpenseWorkflowController::class, 'upload'])->name('business.expenses.evidence');
Route::get('/complex-expenses/{id}/evidence/{evidence}', [PropertyExpenseWorkflowController::class, 'download'])->name('business.expenses.evidence.download');
Route::get('/complex-expenses/units/{id}',     [ExpenseController::class, 'getComplexUnits'])->name('business.expenses.units');
Route::post('/complex-expenses',               [ExpenseController::class, 'store'])->name('business.expenses.store');
Route::put('/complex-expenses/{id}',           [ExpenseController::class, 'updateExpense'])->name('business.expenses.update');
Route::delete('/complex-expenses/{id}',        [ExpenseController::class, 'destroyExpense'])->name('business.expenses.destroy');
