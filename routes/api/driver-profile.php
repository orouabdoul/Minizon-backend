<?php

use App\Http\Controllers\Driver\DriverProfileController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page Profil
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    Route::get('profile',     [DriverProfileController::class, 'profile'])->name('driver.profile.show');
    Route::put('profile',     [DriverProfileController::class, 'update'])->name('driver.profile.update');
    Route::patch('preferences', [DriverProfileController::class, 'updatePreferences'])->name('driver.profile.preferences');

});
