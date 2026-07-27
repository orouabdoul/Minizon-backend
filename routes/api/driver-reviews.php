<?php

use App\Http\Controllers\Driver\DriverReviewsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Liste globale de tous les avis reçus par le conducteur
    Route::get('reviews', [DriverReviewsController::class, 'index'])
        ->name('driver.reviews.index');

    // Avis reçus pour un trajet terminé spécifique
    Route::get('trips/{uuid}/reviews', [DriverReviewsController::class, 'tripReviews'])
        ->name('driver.reviews.trip');

    // Répondre à un avis (depuis la liste globale ou depuis un trajet)
    Route::post('reviews/{uuid}/reply', [DriverReviewsController::class, 'reply'])
        ->name('driver.reviews.reply');

});
