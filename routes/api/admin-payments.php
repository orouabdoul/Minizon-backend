<?php

use App\Http\Controllers\Admin\AdminPaymentController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  💳 ROUTES ADMIN — Gestion des paiements (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/payments')->group(function () {

    // Routes nommées — AVANT les wildcards {uuid}
    Route::get('metrics',         [AdminPaymentController::class, 'metrics']);
    Route::get('/',               [AdminPaymentController::class, 'index']);
    Route::post('sync',           [AdminPaymentController::class, 'sync']);

    // Wildcards — APRÈS les routes nommées
    Route::get('{uuid}',           [AdminPaymentController::class, 'show']);
    Route::post('{uuid}/refund',   [AdminPaymentController::class, 'refund']);
    Route::post('{uuid}/release',  [AdminPaymentController::class, 'release']);
    Route::post('{uuid}/sync',     [AdminPaymentController::class, 'syncOne']);
});
