<?php

use App\Http\Controllers\Driver\DriverDashboardController;
use App\Http\Controllers\Driver\DriverStatsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page d'accueil (Home)
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Données agrégées de la home (summary, metrics, next_trip, wallet, level)
    Route::get('dashboard',      [DriverDashboardController::class, 'dashboard'])->name('driver.dashboard');

    // Toggle disponibilité : is_online + mode (normal | pause | night)
    Route::patch('availability', [DriverDashboardController::class, 'updateAvailability'])->name('driver.availability');

    // Stats financières historiques (endpoint hérité, corrigé ici)
    Route::get('stats',          [DriverStatsController::class,     'index'])->name('driver.stats');

    // Note : les demandes en attente (QuickRequests) et récentes (RequestList)
    // utilisent GET /api/driver/bookings?status=pending&upcoming=true (routes/api/bookings.php)
    // Accepter / Refuser : POST /api/bookings/{uuid}/accept|reject (routes/api/bookings.php)
    // Notifications      : GET  /api/notifications                  (routes/api/notifications.php)
});
