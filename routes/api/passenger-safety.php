<?php

use App\Http\Controllers\Passenger\PassengerSafetyController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Sécurité" (SafetyView)
// ============================================================

Route::middleware(['auth:sanctum'])->prefix('passenger')->group(function () {

    // SafetyView — données initiales (sos_active + trip_share + contacts)
    Route::get('safety', [PassengerSafetyController::class, 'context'])
        ->name('passenger.safety.context');

    // SOS
    Route::post('safety/sos',    [PassengerSafetyController::class, 'activateSOS'])->name('passenger.safety.sos.activate');
    Route::delete('safety/sos',  [PassengerSafetyController::class, 'cancelSOS'])->name('passenger.safety.sos.cancel');

    // Partage de trajet
    Route::post('safety/trip-share',   [PassengerSafetyController::class, 'startShare'])->name('passenger.safety.share.start');
    Route::delete('safety/trip-share', [PassengerSafetyController::class, 'stopShare'])->name('passenger.safety.share.stop');

    // Contacts d'urgence
    Route::get('safety/emergency-contacts',          [PassengerSafetyController::class, 'emergencyContacts'])->name('passenger.safety.emergency-contacts.index');
    Route::post('safety/emergency-contacts',         [PassengerSafetyController::class, 'addEmergencyContact'])->name('passenger.safety.emergency-contacts.store');
    Route::delete('safety/emergency-contacts/{id}',  [PassengerSafetyController::class, 'removeEmergencyContact'])->name('passenger.safety.emergency-contacts.destroy');

});
