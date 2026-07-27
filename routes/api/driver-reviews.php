<?php

use App\Http\Controllers\Driver\DriverReviewsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Liste globale de tous les avis reçus
    Route::get('reviews', [DriverReviewsController::class, 'index'])
        ->name('driver.reviews.index');

    // Avis d'un trajet terminé spécifique
    Route::get('trips/{uuid}/reviews', [DriverReviewsController::class, 'tripReviews'])
        ->name('driver.reviews.trip');

    // Répondre à un avis (💬 Reply)
    Route::post('reviews/{uuid}/reply', [DriverReviewsController::class, 'reply'])
        ->name('driver.reviews.reply');

    // Réagir à un avis (✅ OK | ❌ Non | 🚨 Signaler)
    Route::patch('reviews/{uuid}/react', [DriverReviewsController::class, 'react'])
        ->name('driver.reviews.react');

});
