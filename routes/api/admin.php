<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Passenger\PassengerStatsController;
use App\Http\Controllers\Penalty\PenaltyController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ROUTES ADMIN — Tableau de bord, Pénalités, Audit
// ============================================================

Route::middleware('auth:sanctum')->group(function () {

    // Tableau de bord global
    Route::get('admin/dashboard',          [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('admin/dashboard/activity', [DashboardController::class, 'activity'])->name('admin.dashboard.activity');

    // Pénalités — supervision admin
    Route::get('admin/penalties',                          [PenaltyController::class, 'adminIndex'])->name('admin.penalties.index');
    Route::post('admin/users/{uuid}/penalties',            [PenaltyController::class, 'store'])->name('admin.penalties.store');
    Route::post('admin/users/{uuid}/penalties/reset',      [PenaltyController::class, 'reset'])->name('admin.penalties.reset');

    // Journal d'audit de sécurité
    Route::get('admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');
});

// 🔒 Utilisateur — ses propres pénalités (approved requis)
Route::middleware(['auth:sanctum', 'approved'])->group(function () {
    Route::get('penalties',        [PenaltyController::class,       'index'])->name('penalties.index');
    Route::get('passenger/stats',  [PassengerStatsController::class, 'index'])->name('passenger.stats');
});
