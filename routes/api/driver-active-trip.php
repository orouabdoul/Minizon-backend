<?php

use App\Http\Controllers\Driver\DriverActiveTripController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page "Prêt à partir ?" (pré-départ)
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Données pré-départ : checklist, résumé, itinéraire passagers
    Route::get('trips/{uuid}/pre-departure', [DriverActiveTripController::class, 'readiness'])
        ->name('driver.active_trip.readiness');

    // ℹ️ Le démarrage effectif du trajet est géré par l'endpoint existant :
    //    POST /api/trips/{uuid}/start  (TripController::startTrip)

});
