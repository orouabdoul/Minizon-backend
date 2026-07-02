<?php

use App\Http\Controllers\Driver\DriverEndTripController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page "Trajet terminé" (résumé fin de course)
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Résumé financier, confirmations passagers, date de disponibilité des fonds
    Route::get('trips/{uuid}/end-summary', [DriverEndTripController::class, 'summary'])
        ->name('driver.end_trip.summary');

    // ℹ️ La confirmation effective (status → completed) est gérée par :
    //    POST /api/trips/{uuid}/end  (TripController::endTrip)

});
