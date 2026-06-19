<?php

use App\Http\Controllers\Admin\DriverController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 ROUTES ADMIN — Gestion des conducteurs
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/drivers')->group(function () {

    Route::get('metrics',       [DriverController::class, 'metrics']);
    Route::get('/',             [DriverController::class, 'index']);
    Route::get('{uuid}',        [DriverController::class, 'show']);
    Route::put('{uuid}/validate', [DriverController::class, 'validate']);
    Route::put('{uuid}/reject',   [DriverController::class, 'reject']);
});
