<?php

use App\Http\Controllers\Driver\DriverArrivalController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page "Approche point de prise en charge" (DriverArrivalView)
//
//  Phase : booking accepté → conducteur en route vers le passager.
//
//  Flux endpoints :
//    GET  .../arrival-context   → chargement initial (carte, infos passager)
//    POST .../arrived           → conducteur confirme son arrivée au pickup
//
//  Endpoints existants réutilisés depuis cette page :
//    POST /api/trips/{uuid}/location            — push GPS en continu (TripController)
//    POST /api/trips/{uuid}/start               — démarrage officiel (TripController)
//    GET  /api/driver/trips/{uuid}/pre-departure — pré-départ (DriverActiveTripController)
//    Messagerie rapide                          — system de conversations existant
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Chargement initial : coordonnées GPS, infos passager, infos trajet
    Route::get('bookings/{uuid}/arrival-context', [DriverArrivalController::class, 'context'])
        ->name('driver.arrival.context');

    // Conducteur confirme arrivée → notifie passager + enregistre driver_arrived_at
    Route::post('bookings/{uuid}/arrived', [DriverArrivalController::class, 'arrived'])
        ->name('driver.arrival.arrived');

});
