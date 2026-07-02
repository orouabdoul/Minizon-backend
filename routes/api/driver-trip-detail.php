<?php

use App\Http\Controllers\Driver\DriverTripDetailController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page Détail trajet
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Détail complet d'un trajet
    Route::get('trips/{uuid}',   [DriverTripDetailController::class, 'show'])->name('driver.trip_detail.show');
    // Modifier les champs éditables d'un trajet pending
    Route::patch('trips/{uuid}', [DriverTripDetailController::class, 'update'])->name('driver.trip_detail.update');

});
