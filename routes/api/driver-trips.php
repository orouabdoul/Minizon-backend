<?php

use App\Http\Controllers\Driver\DriverTripController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page Trajets
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    Route::get('trips',                         [DriverTripController::class, 'list'])->name('driver.trips.list');
    Route::get('trips/{uuid}/passengers',       [DriverTripController::class, 'passengers'])->name('driver.trips.passengers');
    Route::post('trips/{uuid}/cancel',          [DriverTripController::class, 'cancel'])->name('driver.trips.cancel');

});
