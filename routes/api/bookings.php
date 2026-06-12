<?php

use App\Http\Controllers\Booking\BookingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // 🔒 Passager — réserver
    Route::post('trips/{uuid}/bookings',  [BookingController::class, 'store'])->name('bookings.store');

    // 🔒 Passager — mes réservations
    Route::get('bookings',                [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{uuid}',         [BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{uuid}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // 🔒 Conducteur — gérer les demandes
    Route::post('bookings/{uuid}/accept', [BookingController::class, 'accept'])->name('bookings.accept');
    Route::post('bookings/{uuid}/reject', [BookingController::class, 'reject'])->name('bookings.reject');
    Route::get('driver/bookings',         [BookingController::class, 'driverBookings'])->name('driver.bookings');

    // 🔒 Admin
    Route::get('admin/bookings',          [BookingController::class, 'adminIndex'])->name('admin.bookings.index');
});
