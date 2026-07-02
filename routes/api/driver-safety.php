<?php

use App\Http\Controllers\Driver\DriverSafetyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {
    Route::post('safety/sos',                      [DriverSafetyController::class, 'sos'])->name('driver.safety.sos');
    Route::post('safety/incidents',                [DriverSafetyController::class, 'reportIncident'])->name('driver.safety.incidents');
    Route::get('safety/emergency-contacts',        [DriverSafetyController::class, 'emergencyContacts'])->name('driver.safety.emergency-contacts.index');
    Route::post('safety/emergency-contacts',       [DriverSafetyController::class, 'addEmergencyContact'])->name('driver.safety.emergency-contacts.store');
    Route::delete('safety/emergency-contacts/{id}',[DriverSafetyController::class, 'removeEmergencyContact'])->name('driver.safety.emergency-contacts.destroy');
});
