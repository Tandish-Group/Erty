<?php

/*
| Legal documents.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Legal\LegalController;
use Illuminate\Support\Facades\Route;

Route::resource('legal', LegalController::class)->only(['index', 'store', 'destroy']);
Route::post('/update/legal_folder', [LegalController::class, 'legal_folder_updated']);
Route::post('/upload/legal_document', [LegalController::class, 'legal_document_upload']);
Route::delete('/delete/legal_document/{fileName}', [LegalController::class, 'destroyFIle']);
Route::post('/move/legal_document', [LegalController::class, 'legal_document_move']);
Route::get('/download/legal_document/{document}', [LegalController::class, 'legal_document_download']);
Route::get('/download/legal_folder/{slug}', [LegalController::class, 'legal_folder_download']);
