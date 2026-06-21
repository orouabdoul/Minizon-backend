<?php

use App\Http\Controllers\Admin\AdminDisputeController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  ⚖️ ROUTES ADMIN — Gestion des litiges (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/disputes')->group(function () {

    Route::get('metrics',          [AdminDisputeController::class, 'metrics']);
    Route::get('/',                [AdminDisputeController::class, 'index']);
    Route::get('{id}',             [AdminDisputeController::class, 'show']);
    Route::post('{id}/assign',     [AdminDisputeController::class, 'assign']);
    Route::post('{id}/refund',     [AdminDisputeController::class, 'refund']);
    Route::post('{id}/pay-driver', [AdminDisputeController::class, 'payDriver']);
});
