<?php

use App\Http\Controllers\Driver\DriverPaymentHistoryController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  💰 DRIVER — Page "Historique des paiements"
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Transactions groupées par mois — revenus + retraits
    Route::get('payment-history', [DriverPaymentHistoryController::class, 'index'])
        ->name('driver.payment_history.index');

    // Reçu récapitulatif du mois en cours (pour controller.onDownloadReceipt)
    Route::get('payment-history/receipt', [DriverPaymentHistoryController::class, 'receipt'])
        ->name('driver.payment_history.receipt');

});
