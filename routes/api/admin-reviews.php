<?php

use App\Http\Controllers\Admin\AdminReviewController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Modération des évaluations (ReviewsScreen)
//
//  GET   /api/admin/reviews/stats          → KPIs (total, moyenne, signalés, masqués)
//  GET   /api/admin/reviews                → liste filtrée (status, direction, rating, search)
//  PATCH /api/admin/reviews/{uuid}/status  → changer statut (visible|masqué|signalé)
//  DELETE /api/admin/reviews/{uuid}         → supprimer définitivement
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/reviews')->group(function () {

    // Route nommée AVANT le wildcard {uuid}
    Route::get('stats', [AdminReviewController::class, 'stats']);
    Route::get('/',     [AdminReviewController::class, 'index']);

    // Wildcards
    Route::patch('{uuid}/status', [AdminReviewController::class, 'setStatus']);
    Route::delete('{uuid}',       [AdminReviewController::class, 'destroy']);

});
