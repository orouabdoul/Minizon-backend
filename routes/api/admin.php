<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ROUTES ADMIN — Tableau de bord & Audit
// ============================================================

Route::middleware('auth:sanctum')->group(function () {

    // Tableau de bord global
    Route::get('admin/dashboard',          [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('admin/dashboard/activity', [DashboardController::class, 'activity'])->name('admin.dashboard.activity');

    // Journal d'audit de sécurité
    Route::get('admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
});
