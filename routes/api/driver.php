<?php

use App\Http\Controllers\Driver\DriverDashboardController;
use App\Http\Controllers\Driver\DriverStatsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 ROUTES DRIVER — Espace conducteur
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // ── Dashboard & disponibilité ──────────────────────────────────────────
    Route::get('dashboard',     [DriverDashboardController::class, 'dashboard']);
    Route::patch('availability', [DriverDashboardController::class, 'updateAvailability']);

    // ── Stats (endpoint historique) ────────────────────────────────────────
    Route::get('stats', [DriverStatsController::class, 'index']);

    // Note : driver/trips    → routes/api/trips.php
    //        driver/bookings → routes/api/bookings.php
});
