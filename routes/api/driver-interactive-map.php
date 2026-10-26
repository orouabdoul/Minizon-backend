<?php

use App\Http\Controllers\Driver\DriverDropoffController;
use App\Http\Controllers\Driver\DriverInteractiveMapController;
use App\Http\Controllers\Driver\DriverNoShowController;
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

    // Demande d'embarquement → FCM au passager
    Route::post('trips/{uuid}/stops/{bookingUuid}/boarding-request', [DriverInteractiveMapController::class, 'boardingRequest'])
        ->name('driver.map.boarding_request');

    // Vérification du code 4 chiffres (fallback hors réseau passager)
    Route::post('trips/{uuid}/stops/{bookingUuid}/verify-code', [DriverInteractiveMapController::class, 'verifyCode'])
        ->name('driver.map.verify_code');

    // Déposer un passager → déclenche le timer de libération des fonds (1h)
    Route::post('trips/{uuid}/stops/{bookingUuid}/dropoff', [DriverDropoffController::class, 'dropoff'])
        ->name('driver.map.dropoff');

    // Signaler un passager absent (15min après départ) → libération immédiate des fonds au conducteur
    Route::post('trips/{uuid}/stops/{bookingUuid}/no-show', [DriverNoShowController::class, 'reportNoShow'])
        ->name('driver.map.no_show');

    // Recalcul/optimisation de l'itinéraire
    Route::post('trips/{uuid}/recalculate', [DriverInteractiveMapController::class, 'recalculate'])
        ->name('driver.map.recalculate');

});
