<?php

use App\Http\Controllers\Admin\AdminVehicleController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 ROUTES ADMIN — Vérification et approbation des véhicules
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/vehicles')->group(function () {

    Route::get('metrics',         [AdminVehicleController::class, 'metrics']);
    Route::get('/',               [AdminVehicleController::class, 'index']);
    Route::get('{id}',            [AdminVehicleController::class, 'show']);
    Route::post('{id}/approve',   [AdminVehicleController::class, 'approve']);
    Route::post('{id}/reject',    [AdminVehicleController::class, 'reject']);
    Route::post('{id}/suspend',   [AdminVehicleController::class, 'suspend']);
    Route::post('{id}/reinstate', [AdminVehicleController::class, 'reinstate']);
    Route::delete('{id}',         [AdminVehicleController::class, 'destroy']);
});
