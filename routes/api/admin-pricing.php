<?php

use App\Http\Controllers\Admin\AdminPricingController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Tarifs & Promotions (PricingScreen)
//
//  Tarifs :
//    GET   /api/admin/pricing/tariffs              → liste des règles
//    PATCH /api/admin/pricing/tariffs/{uuid}       → modifier la valeur
//    PATCH /api/admin/pricing/tariffs/{uuid}/toggle → activer/désactiver
//
//  Codes promo :
//    GET    /api/admin/pricing/promos              → liste
//    POST   /api/admin/pricing/promos              → créer
//    PATCH  /api/admin/pricing/promos/{uuid}/toggle → activer/désactiver
//    DELETE /api/admin/pricing/promos/{uuid}        → supprimer
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/pricing')->group(function () {

    // ── Tarifs ────────────────────────────────────────────────────────────────
    Route::get('tariffs',                    [AdminPricingController::class, 'tariffs']);
    Route::patch('tariffs/{uuid}',           [AdminPricingController::class, 'updateTariff']);
    Route::patch('tariffs/{uuid}/toggle',    [AdminPricingController::class, 'toggleTariff']);

    // ── Codes promo ───────────────────────────────────────────────────────────
    Route::get('promos',                     [AdminPricingController::class, 'promos']);
    Route::post('promos',                    [AdminPricingController::class, 'createPromo']);
    Route::patch('promos/{uuid}/toggle',     [AdminPricingController::class, 'togglePromo']);
    Route::delete('promos/{uuid}',           [AdminPricingController::class, 'deletePromo']);

});
