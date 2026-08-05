<?php

use App\Http\Controllers\Admin\AdminReservationController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🎫 ROUTES ADMIN — Gestion des réservations (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/reservations')->group(function () {

    // Routes nommées — AVANT les wildcards {uuid}
    Route::get('metrics',    [AdminReservationController::class, 'metrics']);
    Route::get('/',          [AdminReservationController::class, 'index']);

    // Wildcards — APRÈS les routes nommées
    Route::get('{uuid}',     [AdminReservationController::class, 'show']);
    Route::put('{uuid}',     [AdminReservationController::class, 'update']);
    Route::delete('{uuid}',  [AdminReservationController::class, 'destroy']);
});
