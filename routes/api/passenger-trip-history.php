<?php

use App\Http\Controllers\Passenger\PassengerTripHistoryController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Mes trajets" (TripHistoryView)
//
//  Distinct de ReservationView : 3 statuts (upcoming / completed / cancelled)
//  et rating = note personnelle du passager pour chaque trajet.
//
//  Actions Flutter sans endpoint :
//    rebookTrip()    → navigation vers SearchView
//                      (GET /api/passenger/search — public, existant)
//    requestRefund() → navigation vers RefundView
//                      (POST /api/passenger/refunds — PassengerRefundController existant)
// ============================================================

Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('passenger')->group(function () {

    Route::get('trips/history', [PassengerTripHistoryController::class, 'index'])
        ->name('passenger.trips.history');

});
