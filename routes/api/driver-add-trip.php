<?php

use App\Http\Controllers\Driver\DriverAddTripController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Page Ajouter un trajet
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Initialisation du formulaire (véhicules, villes, préférences)
    Route::get('trip-form',    [DriverAddTripController::class, 'formData'])->name('driver.add_trip.form');
    // Publier un nouveau trajet
    Route::post('trip-publish',  [DriverAddTripController::class, 'publish'])->name('driver.add_trip.publish');
    // Estimer la distance et la durée avant publication
    Route::post('trip-estimate', [DriverAddTripController::class, 'estimate'])->name('driver.add_trip.estimate');

});
