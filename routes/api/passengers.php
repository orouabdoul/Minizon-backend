<?php

use App\Http\Controllers\Admin\PassengerController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🧑 ROUTES ADMIN — Gestion des passagers
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/passengers')->group(function () {

    Route::get('metrics',            [PassengerController::class, 'metrics']);
    Route::get('/',                  [PassengerController::class, 'index']);
    Route::get('{uuid}',             [PassengerController::class, 'show']);
    Route::put('{uuid}/suspend',     [PassengerController::class, 'suspend']);
    Route::put('{uuid}/unsuspend',   [PassengerController::class, 'unsuspend']);
});
