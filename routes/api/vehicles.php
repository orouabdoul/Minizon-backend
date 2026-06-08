<?php

/**
 * ============================================================
 *  ROUTES — FLOTTE & VÉHICULES
 *  Fichier : routes/api/vehicles.php
 * ============================================================
 *
 *  Préfixe global : /api  (défini dans bootstrap/app.php)
 *  Toutes les routes de ce fichier requièrent un token Sanctum.
 */

use App\Http\Controllers\Vehicle\VehicleController;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------
// 🔒 TOUTES LES ROUTES VÉHICULES SONT PROTÉGÉES
// -----------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {

    // CRUD véhicule
    Route::get('vehicles',          [VehicleController::class, 'index'])->name('vehicles.index');
    Route::post('vehicles',         [VehicleController::class, 'store'])->name('vehicles.store');
    Route::get('vehicles/{id}',     [VehicleController::class, 'show'])->name('vehicles.show');
    Route::put('vehicles/{id}',     [VehicleController::class, 'update'])->name('vehicles.update');
    Route::delete('vehicles/{id}',  [VehicleController::class, 'destroy'])->name('vehicles.destroy');

    // -----------------------------------------------------------------------
    // 👑 ROUTES ADMIN
    // -----------------------------------------------------------------------

    Route::post('admin/vehicles/{id}/approve', [VehicleController::class, 'toggleApproval'])->name('admin.vehicles.approve');
});