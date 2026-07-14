<?php

use App\Http\Controllers\Passenger\PassengerPaymentSuccessController;
use App\Http\Controllers\Passenger\PassengerReservationController;
use App\Http\Controllers\Passenger\PassengerWaitingApprovalController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Mes réservations" + "En attente"
// ============================================================

Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('passenger')->group(function () {

    // Liste complète formatée (active_trip + status_tabs + items)
    Route::get('reservations', [PassengerReservationController::class, 'index'])
        ->name('passenger.reservations.index');

    // Données de facture PDF pour une réservation terminée
    Route::get('reservations/{uuid}/invoice', [PassengerReservationController::class, 'invoice'])
        ->name('passenger.reservations.invoice');

    // ── WaitingApprovalView — polling du statut conducteur (~3 s) ───────────
    Route::get('bookings/{uuid}/approval-status', [PassengerWaitingApprovalController::class, 'status'])
        ->name('passenger.bookings.approval-status');

    // ── PaymentSuccessView — récapitulatif après paiement FedaPay ────────────
    Route::get('bookings/{uuid}/success', [PassengerPaymentSuccessController::class, 'show'])
        ->name('passenger.bookings.success');

});

// Note : l'annulation utilise l'endpoint générique existant :
//   POST /api/bookings/{uuid}/cancel  (BookingController@cancel)
// Le paiement utilise :
//   POST /api/bookings/{uuid}/pay     (PaymentController@initiate)
// Le chat utilise :
//   POST /api/passenger/bookings/{uuid}/conversation  (PassengerDetailMessagerController@getOrCreate)
