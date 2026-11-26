<?php

/*
| Properties: complexes and their set-up, units, facilities, floor plans, history and service charges.
| Loaded by routes/web.php inside the business group (signed in, password changed, business access map).
*/

use App\Http\Controllers\Business\Properties\CommercialUnitController;
use App\Http\Controllers\Business\Properties\ComplexController;
use App\Http\Controllers\Business\Properties\ComplexFloorPlanController;
use App\Http\Controllers\Business\Properties\ComplexHistoryController;
use App\Http\Controllers\Business\Properties\ComplexManagerController;
use App\Http\Controllers\Business\Properties\ComplexWorkflowController;
use App\Http\Controllers\Business\Properties\PropertyFacilityController;
use App\Http\Controllers\Business\Properties\PropertyImportController;
use App\Http\Controllers\Business\Properties\PropertyLookupController;
use App\Http\Controllers\Business\Properties\ResidentialInventoryController;
use App\Http\Controllers\Business\Properties\ServiceChargeController;
use Illuminate\Support\Facades\Route;

// Creation goes through the draft workflow and editing through the focused editors below;
// the legacy store/update/destroy actions were unscoped and are no longer routed.
Route::resource('complex', ComplexController::class)->except(['store', 'edit', 'update', 'destroy']);
Route::get('/residential-units', [ResidentialInventoryController::class, 'index'])->name('residential-units.index');
Route::post('/complex-workflow/draft', [ComplexWorkflowController::class, 'draft'])->name('complex.workflow.draft');
Route::post('/complex/{slug}/setup/details', [ComplexWorkflowController::class, 'details'])->name('complex.workflow.details');
Route::post('/complex/{slug}/setup/composition', [ComplexWorkflowController::class, 'composition'])->name('complex.workflow.composition');
Route::post('/complex/{slug}/setup/review/{step}', [ComplexWorkflowController::class, 'review'])->name('complex.workflow.review');
Route::post('/complex/{slug}/setup/generate', [ComplexWorkflowController::class, 'generate'])->name('complex.workflow.generate');
Route::post('/complex/{slug}/setup/advanced', [ComplexWorkflowController::class, 'advanced'])->name('complex.workflow.advanced');
Route::post('/complex/{slug}/setup/residential/{unit}', [ComplexWorkflowController::class, 'refineResidential'])->name('complex.workflow.residential');
Route::put('/complex/{slug}/managers', [ComplexManagerController::class, 'update'])->name('complex.managers.update');
Route::put('/complex/{slug}/unit-agents', [ComplexManagerController::class, 'unitAgents'])->name('complex.unit_agents.update');
Route::put('/complex/{slug}/management', [ComplexManagerController::class, 'management'])->name('complex.management.update');
Route::get('/complex/{slug}/assignment-history/{kind}/{unit?}', [ComplexManagerController::class, 'history'])->whereIn('kind', ['residential', 'commercial', 'property'])->whereNumber('unit')->name('complex.assignment_history');
Route::get('/complex/{slug}/setup', [ComplexController::class, 'setup'])
    ->name('complex.setup');

Route::post('/complex/{slug}/setup/confirm', [ComplexController::class, 'confirmSetup'])
    ->name('complex.setup.confirm');

Route::get('/data-complexes/download', [PropertyImportController::class, 'dataDownloadComplexes']);
Route::resource('plans_complex', ComplexFloorPlanController::class)->only(['store', 'show', 'destroy']);
Route::post('bulk/complex/plans/delete', [ComplexFloorPlanController::class, 'complex_bulk_delete']);
Route::post('/upload/complexes', [PropertyImportController::class, 'complex_upload']);
Route::post('/complex-amenities/bulk-delete', [PropertyFacilityController::class, 'bulkDestroy'])->name('complex-amenities.bulk-delete');
Route::post('/single-delete/complex-amenities', [PropertyFacilityController::class, 'destroy'])->name('complex-amenities.single-delete');
Route::get('/complex-amenities/{slug}/get-amenities', [PropertyLookupController::class, 'getComplexAmenities'])->name('complex-amenities.get-amenities');
Route::get('/get-parking-slots/{amenityId}/available', [PropertyLookupController::class, 'getParkingSlots']);
Route::get('/complex/{complexId}/residential-units-for-tenant', [PropertyLookupController::class, 'getResidentialUnitsForTenant'])->name('complex.residential-units-for-tenant');

/* Amenity CRUD routes */
Route::get('/complex/{slug}/amenities/create', [PropertyFacilityController::class, 'create'])->name('complex.amenities.create');
Route::post('/complex/{slug}/amenities', [PropertyFacilityController::class, 'store'])->name('complex.amenities.store');
Route::get('/complex/amenities/{id}/edit', [PropertyFacilityController::class, 'edit'])->name('complex.amenities.edit');
Route::put('/complex/amenities/{id}', [PropertyFacilityController::class, 'update'])->name('complex.amenities.update');

/* Commercial Units */
Route::resource('commercial-units', CommercialUnitController::class)->except(['show']);
Route::get('download/unit-sample', [CommercialUnitController::class, 'downloadUnitSample']);
Route::post('/upload/units', [CommercialUnitController::class, 'unit_upload']);
Route::get('/complexes/{id}/info', [CommercialUnitController::class, 'getComplexInfo'])->name('complexes.info');
Route::get('/complexes/search', [PropertyLookupController::class, 'searchComplexes'])->name('complexes.search');
Route::get('/complexes/stats', [ComplexController::class, 'getComplexesStats'])->name('complexes.stats');
Route::get('/history/create', [ComplexHistoryController::class, 'create'])->name('history.create');
Route::resource('history', ComplexHistoryController::class)->only(['index', 'store', 'destroy']);
Route::get('/history/{id}', [ComplexHistoryController::class, 'show'])->whereNumber('id')->name('history.show');
// Older links to a record's details.
Route::get('/history/{id}/view', fn (int $id) => redirect()->route('history.show', $id))->whereNumber('id');
Route::get('/history/complexes/search', [PropertyLookupController::class, 'searchForHistory'])->name('history.complexes.search');
Route::get('/complex/{slug}/units', [PropertyLookupController::class, 'getComplexUnits'])->name('complex.units');

// Commercial spaces of a property, for the lead form (core: not part of the Listings module).
Route::get('/complex/{complex}/commercial-spaces', [PropertyLookupController::class, 'commercialSpaces'])->whereNumber('complex')->name('complex.commercial-spaces');
Route::resource('service_charge', ServiceChargeController::class)->only(['store', 'update', 'destroy']);
Route::get('/service-charges', [ServiceChargeController::class, 'serviceCharges'])->name('complex.service-charges');
Route::delete('/service-charge/{id}', [ServiceChargeController::class, 'deleteServiceCharge'])->name('service-charge.delete');
Route::post('/upload/service_charges', [ServiceChargeController::class, 'service_charges_upload'])->name('service_charges.upload');
Route::get('/download/service-charges-sample', [ServiceChargeController::class, 'downloadServiceChargesSample'])->name('service_charges.sample');
Route::get('download/complex-sample', [PropertyImportController::class, 'downloadComplex']);
