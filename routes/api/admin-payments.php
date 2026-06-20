<?php

use App\Http\Controllers\Admin\AdminPaymentController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  💳 ROUTES ADMIN — Gestion des paiements (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/payments')->group(function () {

    Route::get('metrics',        [AdminPaymentController::class, 'metrics']);
    Route::get('/',              [AdminPaymentController::class, 'index']);
    Route::get('{uuid}',         [AdminPaymentController::class, 'show']);
    Route::post('{uuid}/refund', [AdminPaymentController::class, 'refund']);
});
