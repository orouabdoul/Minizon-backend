<?php

use App\Http\Controllers\Passenger\PassengerRefundController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Remboursement"
// ============================================================

Route::middleware(['auth:sanctum'])->prefix('passenger')->group(function () {

    // Contexte pour pré-remplir le formulaire (trajet, montant, transaction ref)
    Route::get('bookings/{uuid}/refund-context', [PassengerRefundController::class, 'context'])
        ->name('passenger.refund.context');

    // Soumettre la demande de remboursement (multipart avec proof_images[])
    Route::post('bookings/{uuid}/refund', [PassengerRefundController::class, 'store'])
        ->name('passenger.refund.store');

    // Historique des demandes de remboursement
    Route::get('refunds', [PassengerRefundController::class, 'history'])
        ->name('passenger.refund.history');

});
