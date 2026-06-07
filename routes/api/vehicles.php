<?php

use App\Http\Controllers\Vehicle\VehicleController;
use Illuminate\Support\Facades\Route;

// =============================================================================
//  ROUTES VÉHICULES — routes/vehicles.php
//  Chargé dans bootstrap/app.php via :
//      ->withRouting(using: function () {
//          Route::middleware('api')->prefix('api')->group(base_path('routes/vehicles.php'));
//      })
//  OU dans routes/api.php via :
//      require __DIR__ . '/vehicles.php';
// =============================================================================


// -----------------------------------------------------------------------------
//  Endpoints AUTHENTIFIÉS — conducteurs
// -----------------------------------------------------------------------------

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('vehicles')->group(function () {

        // GET    /api/vehicles         — Lister mes véhicules (admin = toute la flotte)
        Route::get('/', [VehicleController::class, 'index']);

        // POST   /api/vehicles         — Soumettre un nouveau véhicule
        Route::post('/', [VehicleController::class, 'store']);

        // GET    /api/vehicles/{id}    — Consulter la fiche d'un véhicule
        Route::get('/{id}', [VehicleController::class, 'show']);

        // PUT    /api/vehicles/{id}    — Modifier un véhicule
        Route::put('/{id}', [VehicleController::class, 'update']);

        // DELETE /api/vehicles/{id}    — Supprimer un véhicule
        Route::delete('/{id}', [VehicleController::class, 'destroy']);

    });

});


// -----------------------------------------------------------------------------
//  Endpoints ADMIN uniquement
// -----------------------------------------------------------------------------

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // POST /api/admin/vehicles/{id}/approve — Approuver ou bloquer un véhicule
    Route::post('/vehicles/{id}/approve', [VehicleController::class, 'toggleApproval']);

});