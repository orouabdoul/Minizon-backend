<?php

use App\Http\Controllers\Admin\AdminReportController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  📊 ROUTES ADMIN — Rapports & Statistiques (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/reports')->group(function () {

    // Route nommée — AVANT les wildcards
    Route::get('export', [AdminReportController::class, 'export']);

    // Liste principale (KPIs + graphiques)
    Route::get('/', [AdminReportController::class, 'index']);
});
