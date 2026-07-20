<?php

use App\Http\Controllers\Admin\AdminPayoutsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Virements Conducteurs (PayoutsScreen)
//
//  Routes nommées AVANT les wildcards {uuid} :
//    GET  /api/admin/payouts/summary       → KPIs
//    GET  /api/admin/payouts               → liste (filtrable par ?status=)
//    GET  /api/admin/payouts/export        → CSV
//    POST /api/admin/payouts/generate      → générer depuis les gains non payés
//    POST /api/admin/payouts/batch-process → traiter plusieurs en une fois
//
//  Routes par virement :
//    POST /api/admin/payouts/{uuid}/process   → en_attente → en_traitement
//    POST /api/admin/payouts/{uuid}/mark-paid → en_traitement → payé
//    POST /api/admin/payouts/{uuid}/retry     → échoué → en_attente
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/payouts')->group(function () {

    // Routes nommées — AVANT {uuid} wildcards
    Route::get('summary',        [AdminPayoutsController::class, 'summary']);
    Route::get('export',         [AdminPayoutsController::class, 'export']);
    Route::get('/',              [AdminPayoutsController::class, 'index']);
    Route::post('generate',      [AdminPayoutsController::class, 'generate']);
    Route::post('batch-process', [AdminPayoutsController::class, 'batchProcess']);

    // Wildcards — APRÈS
    Route::post('{uuid}/process',   [AdminPayoutsController::class, 'process']);
    Route::post('{uuid}/mark-paid', [AdminPayoutsController::class, 'markPaid']);
    Route::post('{uuid}/retry',     [AdminPayoutsController::class, 'retry']);

});
