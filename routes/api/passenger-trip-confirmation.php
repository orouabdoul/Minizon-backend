<?php

use App\Http\Controllers\Passenger\PassengerTripConfirmationController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Trajet terminé" (TripConfirmationView)
//
//  Flux :
//    GET  .../trip-confirmation-context  → infos résumé (ride card)
//    POST .../confirm                    → passager confirme réception + signalement optionnel
//    POST .../review                     → note conducteur (stars + tags + avis écrit)
//
//  Actions Flutter sans endpoint :
//    controller.skipReview()          → navigation locale (goHome)
//    controller.goHome()              → navigation locale
//    BottonNavController.goToTab(2)   → navigation locale (onglet réservations)
// ============================================================

Route::middleware(['auth:sanctum'])->prefix('passenger')->group(function () {

    // Chargement des infos de résumé (TripSummary card)
    Route::get('bookings/{uuid}/trip-confirmation-context', [PassengerTripConfirmationController::class, 'context'])
        ->name('passenger.trip_confirmation.context');

    // Passager confirme que le trajet est bien reçu (ConfirmCard → "Oui, le trajet est terminé")
    Route::post('bookings/{uuid}/confirm', [PassengerTripConfirmationController::class, 'confirm'])
        ->name('passenger.trip_confirmation.confirm');

    // Soumission de l'avis conducteur (RatingCard + QuickTagsCard + ReviewField → submitReview)
    Route::post('bookings/{uuid}/review', [PassengerTripConfirmationController::class, 'review'])
        ->name('passenger.trip_confirmation.review');

});
