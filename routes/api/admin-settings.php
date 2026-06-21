<?php

use App\Http\Controllers\Admin\AdminSettingsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  ⚙️ ROUTES ADMIN — Paramètres plateforme (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/settings')->group(function () {

    Route::get('summary',               [AdminSettingsController::class, 'summary']);

    // Paramètres généraux
    Route::get('general',               [AdminSettingsController::class, 'getGeneral']);
    Route::put('general',               [AdminSettingsController::class, 'updateGeneral']);

    // Commissions
    Route::get('commissions',           [AdminSettingsController::class, 'commissions']);
    Route::put('commissions/{uuid}',    [AdminSettingsController::class, 'updateCommission']);

    // Fournisseurs de paiement
    Route::get('payments',              [AdminSettingsController::class, 'payments']);

    // Journal sécurité (audit_logs)
    Route::get('security',              [AdminSettingsController::class, 'securityLogs']);

    // Gestion des administrateurs
    Route::get('admins',                [AdminSettingsController::class, 'admins']);
    Route::post('admins',               [AdminSettingsController::class, 'addAdmin']);
    Route::put('admins/{uuid}',         [AdminSettingsController::class, 'updateAdmin']);
    Route::delete('admins/{uuid}',      [AdminSettingsController::class, 'deleteAdmin']);

    // Analytics BI
    Route::get('analytics',             [AdminSettingsController::class, 'analytics']);
});
