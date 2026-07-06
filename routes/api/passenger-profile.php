<?php

use App\Http\Controllers\Passenger\PassengerMyReviewsController;
use App\Http\Controllers\Passenger\PassengerProfileController;
use App\Http\Controllers\Passenger\PassengerStatsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Profil"
// ============================================================

Route::middleware(['auth:sanctum'])->prefix('passenger')->group(function () {

    // Page profil complète (summary, metrics, trust, settings, payment_methods, recent_trips)
    Route::get('profile', [PassengerProfileController::class, 'show'])
        ->name('passenger.profile.show');

    // Mise à jour des infos personnelles
    Route::patch('profile', [PassengerProfileController::class, 'update'])
        ->name('passenger.profile.update');

    // MyReviewsView — avis donnés par le passager aux conducteurs
    Route::get('reviews', [PassengerMyReviewsController::class, 'index'])
        ->name('passenger.reviews.index');

    // Statistiques passager
    Route::get('stats', [PassengerStatsController::class, 'index'])
        ->name('passenger.stats');

});
