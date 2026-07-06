<?php

use App\Http\Controllers\Driver\DriverBookingsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Réservations reçues (page Réservations)
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    Route::get('bookings', [DriverBookingsController::class, 'driverBookings'])->name('driver.bookings.index');

});

Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    Route::post('bookings/{uuid}/accept', [DriverBookingsController::class, 'accept'])->name('bookings.accept');
    Route::post('bookings/{uuid}/reject', [DriverBookingsController::class, 'reject'])->name('bookings.reject');

});
