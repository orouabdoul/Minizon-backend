<?php

use App\Http\Controllers\Passenger\PassengerConfirmationController;
use App\Http\Controllers\Passenger\PassengerLiveTrackingController;
use App\Http\Controllers\Passenger\PassengerTripDetailController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Détail trajet + Confirmation réservation
// ============================================================

Route::middleware(['auth:sanctum'])->prefix('passenger')->group(function () {

    // DetailJourneyView — SearchRide + métriques conducteur + 2 avis + statut réservation
    Route::get('trips/{uuid}/detail', [PassengerTripDetailController::class, 'show'])
        ->name('passenger.trip.detail');

    // ConfirmationReservationView — méthodes de paiement + infos trajet fraîches
    Route::get('trips/{uuid}/confirmation-context', [PassengerConfirmationController::class, 'context'])
        ->name('passenger.trip.confirmation-context');

    // LiveTrackingView — polling GPS toutes les ~3 s (position, ETA, vitesse, statut)
    Route::get('trips/{uuid}/live-tracking', [PassengerLiveTrackingController::class, 'poll'])
        ->name('passenger.trip.live-tracking');

});

// Endpoints existants réutilisés par le Flutter depuis ces pages :
//   bookNow              → POST /api/trips/{uuid}/bookings
//   contactDriver        → POST /api/bookings/{uuid}/conversation
//   cancelReservation    → POST /api/bookings/{uuid}/cancel
//   onViewAllReviews     → GET  /api/trips/{uuid}/reviews
// Depuis LiveTrackingView :
//   triggerSOS           → POST /api/passenger/safety/sos
//   sendQuickMessage     → messagerie existante (POST /api/bookings/{uuid}/conversation + messages)
//   callDriver           → url_launcher tel: (pas d'API)
