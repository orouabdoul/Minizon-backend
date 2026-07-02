<?php

use App\Http\Controllers\Driver\DriverHomeController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page d'accueil (Home)
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    Route::get('dashboard',      [DriverHomeController::class, 'dashboard'])->name('driver.home.dashboard');
    Route::patch('availability', [DriverHomeController::class, 'updateAvailability'])->name('driver.home.availability');
    Route::get('stats',          [DriverHomeController::class, 'stats'])->name('driver.home.stats');

});
