<?php

use App\Http\Controllers\Passenger\PassengerSupportController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Centre d'aide" (SupportView)
// ============================================================

Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('passenger')->group(function () {

    // FAQ passager organisée par catégorie (statique serveur)
    Route::get('support/faq', [PassengerSupportController::class, 'faq'])
        ->name('passenger.support.faq');

    // Soumettre un ticket de support (_TicketCard → submitTicket())
    Route::post('support/tickets', [PassengerSupportController::class, 'createTicket'])
        ->name('passenger.support.tickets.store');

    // Historique des tickets (si nécessaire dans une future page)
    Route::get('support/tickets', [PassengerSupportController::class, 'tickets'])
        ->name('passenger.support.tickets.index');

});
