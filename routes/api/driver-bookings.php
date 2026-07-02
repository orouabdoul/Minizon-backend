<?php

use App\Http\Controllers\Booking\BookingController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🚗 DRIVER — Réservations reçues (page Réservations)
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    Route::get('bookings', [BookingController::class, 'driverBookings'])->name('driver.bookings.index');

});

Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    Route::post('bookings/{uuid}/accept', [BookingController::class, 'accept'])->name('bookings.accept');
    Route::post('bookings/{uuid}/reject', [BookingController::class, 'reject'])->name('bookings.reject');

});
