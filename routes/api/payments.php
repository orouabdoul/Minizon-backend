<?php

use App\Http\Controllers\Payment\PaymentController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🌐 Route publique — Webhook FedaPay (pas de token requis)
//  Sécurisée par la vérification de signature HMAC interne
// ============================================================
Route::post('payments/webhook/fedapay', [PaymentController::class, 'webhook'])->name('payments.webhook');

// ============================================================
//  🔒 Routes authentifiées
// ============================================================
Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    // Passager — initier le paiement (push USSD Mobile Money)
    Route::post('bookings/{uuid}/pay',             [PaymentController::class, 'initiate'])->name('payments.initiate');

    // Polling statut (toutes les 3 secondes côté mobile)
    Route::get('payments/{uuid}',                  [PaymentController::class, 'status'])->name('payments.status');

    // Passager — confirmer l'arrivée (libère l'escrow immédiatement)
    Route::post('bookings/{uuid}/confirm-arrival', [PaymentController::class, 'confirmArrival'])->name('payments.confirm-arrival');

    // Admin — voir admin-payments.php pour la supervision complète
});
