<?php

/**
 * ============================================================
 *  ROUTES — RÔLES
 *  Préfixe  : /api/roles
 *  Auth     : sanctum (toutes les routes)
 *  Admin    : store, update, destroy (vérifié dans le contrôleur)
 * ============================================================
 */

use App\Http\Controllers\Role\RoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('roles')->group(function () {
    Route::get('/',        [RoleController::class, 'index']);
    Route::get('/{id}',   [RoleController::class, 'show']);
    Route::post('/',      [RoleController::class, 'store']);
    Route::put('/{id}',   [RoleController::class, 'update']);
    Route::delete('/{id}',[RoleController::class, 'destroy']);
});
