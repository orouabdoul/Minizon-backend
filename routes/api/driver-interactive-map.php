<?php

use App\Http\Controllers\Driver\DriverInteractiveMapController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page "Carte interactive du trajet en cours"
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Chargement initial de la carte (arrêts, polyline, stats)
    Route::get('trips/{uuid}/map', [DriverInteractiveMapController::class, 'mapData'])
        ->name('driver.map.data');

    // Marquer un arrêt/pickup comme terminé
    Route::post('trips/{uuid}/stops/{bookingUuid}/done', [DriverInteractiveMapController::class, 'markStopDone'])
        ->name('driver.map.stop_done');

    // Recalcul/optimisation de l'itinéraire
    Route::post('trips/{uuid}/recalculate', [DriverInteractiveMapController::class, 'recalculate'])
        ->name('driver.map.recalculate');

});
