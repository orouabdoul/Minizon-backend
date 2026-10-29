<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Withdrawal\WithdrawalController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ROUTES ADMIN — Tableau de bord & Audit
// ============================================================

// ── Route publique — page de login (sans auth) ────────────────────────────────
// Métriques vitrine : utilisateurs actifs, trajets du jour, transactions sécurisées, uptime
Route::get('admin/platform-stats', [DashboardController::class, 'platformStats'])
    ->name('admin.platform-stats');

// ── Routes protégées ──────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Tableau de bord global
    Route::get('admin/dashboard',          [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('admin/dashboard/activity', [DashboardController::class, 'activity'])->name('admin.dashboard.activity');

    // Journal d'audit de sécurité
    Route::get('admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');

    // Gestion des retraits conducteurs
    Route::get('admin/withdrawals',                              [WithdrawalController::class, 'adminIndex'])->name('admin.withdrawals.index');
    Route::get('admin/withdrawals/balance',                      [WithdrawalController::class, 'balance'])->name('admin.withdrawals.balance');
    Route::post('admin/withdrawals/{id}/process',                [WithdrawalController::class, 'process'])->name('admin.withdrawals.process');
    Route::post('admin/withdrawals/{id}/reject',                 [WithdrawalController::class, 'reject'])->name('admin.withdrawals.reject');
});
