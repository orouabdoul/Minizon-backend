<?php

use App\Http\Controllers\Admin\AdminSupportController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🎧 ROUTES ADMIN — Gestion des tickets support (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/support')->group(function () {

    Route::get('metrics',        [AdminSupportController::class, 'metrics']);
    Route::get('agents',         [AdminSupportController::class, 'agents']);
    Route::get('/',              [AdminSupportController::class, 'index']);
    Route::post('/',             [AdminSupportController::class, 'store']);
    Route::post('{uuid}/resolve',[AdminSupportController::class, 'resolve']);
});
