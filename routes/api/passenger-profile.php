<?php

use App\Http\Controllers\Passenger\EmergencyContactController;
use App\Http\Controllers\Passenger\PassengerMyReviewsController;
use App\Http\Controllers\Passenger\PassengerProfileController;
use App\Http\Controllers\Passenger\PassengerStatsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Profil"
// ============================================================

Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('passenger')->group(function () {

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

    // Contacts d'urgence
    Route::prefix('emergency-contacts')->name('passenger.emergency.')->group(function () {
        Route::get('/',      [EmergencyContactController::class, 'index'])  ->name('index');
        Route::post('/',     [EmergencyContactController::class, 'store'])  ->name('store');
        Route::put('{id}',   [EmergencyContactController::class, 'update']) ->name('update');
        Route::delete('{id}',[EmergencyContactController::class, 'destroy'])->name('destroy');
    });

});
