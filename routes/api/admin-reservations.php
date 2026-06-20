<?php

use App\Http\Controllers\Admin\AdminReservationController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🎫 ROUTES ADMIN — Gestion des réservations (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/reservations')->group(function () {

    Route::get('metrics', [AdminReservationController::class, 'metrics']);
    Route::get('/',       [AdminReservationController::class, 'index']);
    Route::get('{uuid}',  [AdminReservationController::class, 'show']);
});
