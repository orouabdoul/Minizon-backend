<?php

use App\Http\Controllers\Admin\AdminTripController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🗺️ ROUTES ADMIN — Gestion des trajets (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/trips')->group(function () {

    // Routes nommées — AVANT les wildcards {uuid}
    Route::get('metrics',    [AdminTripController::class, 'metrics']);
    Route::get('/',          [AdminTripController::class, 'index']);

    // Wildcards — APRÈS les routes nommées
    Route::get('{uuid}',     [AdminTripController::class, 'show']);
    Route::put('{uuid}',     [AdminTripController::class, 'update']);
    Route::delete('{uuid}',  [AdminTripController::class, 'destroy']);
});
