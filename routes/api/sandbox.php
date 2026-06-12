<?php

use App\Http\Controllers\Sandbox\SandboxPaymentController;
use Illuminate\Support\Facades\Route;

/*
 * ⚠️  ROUTES SANDBOX — JAMAIS EN PRODUCTION
 * Ce fichier n'est chargé que si APP_ENV=local ou APP_ENV=sandbox.
 * Permet de tester le flux de paiement complet sans passer par FedaPay.
 */

Route::prefix('sandbox')->group(function () {

    // Simule l'approbation FedaPay (transaction.approved)
    Route::post('payments/{uuid}/approve', [SandboxPaymentController::class, 'approve'])
        ->name('sandbox.payments.approve');

    // Simule le refus FedaPay (transaction.declined)
    Route::post('payments/{uuid}/decline', [SandboxPaymentController::class, 'decline'])
        ->name('sandbox.payments.decline');
});
