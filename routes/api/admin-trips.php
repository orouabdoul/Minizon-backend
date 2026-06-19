<?php

use App\Http\Controllers\Admin\AdminTripController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🗺️ ROUTES ADMIN — Gestion des trajets (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/trips')->group(function () {

    Route::get('metrics', [AdminTripController::class, 'metrics']);
    Route::get('/',       [AdminTripController::class, 'index']);
    Route::get('{uuid}',  [AdminTripController::class, 'show']);
});
