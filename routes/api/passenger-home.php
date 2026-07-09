<?php

use App\Http\Controllers\Passenger\PassengerHomeController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page d'accueil (Home)
// ============================================================

Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('passenger')->group(function () {

    Route::get('home', [PassengerHomeController::class, 'dashboard'])->name('passenger.home.dashboard');

});
