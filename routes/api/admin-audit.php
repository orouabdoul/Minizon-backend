<?php

use App\Http\Controllers\Admin\AdminAuditController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Journal d'Audit & Sécurité (AuditScreen)
//
//  GET /api/admin/audit/logs    → liste filtrée (search, severity, action_type, admin_id)
//  GET /api/admin/audit/admins  → admins disponibles pour le select de filtre
//  GET /api/admin/audit/export  → téléchargement CSV (format=excel|pdf)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/audit')->group(function () {

    Route::get('logs',   [AdminAuditController::class, 'logs']);
    Route::get('admins', [AdminAuditController::class, 'admins']);
    Route::get('export', [AdminAuditController::class, 'export']);

});
