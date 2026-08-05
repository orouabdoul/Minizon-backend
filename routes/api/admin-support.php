<?php

use App\Http\Controllers\Admin\AdminSupportController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🎧 ROUTES ADMIN — Gestion des tickets support (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/support')->group(function () {

    // Routes nommées — AVANT les wildcards {uuid}
    Route::get('metrics',         [AdminSupportController::class, 'metrics']);
    Route::get('agents',          [AdminSupportController::class, 'agents']);
    Route::get('/',               [AdminSupportController::class, 'index']);
    Route::post('/',              [AdminSupportController::class, 'store']);

    // Wildcards — APRÈS les routes nommées
    Route::get('{uuid}',          [AdminSupportController::class, 'show']);
    Route::post('{uuid}/resolve', [AdminSupportController::class, 'resolve']);
    Route::delete('{uuid}',       [AdminSupportController::class, 'destroy']);
});
