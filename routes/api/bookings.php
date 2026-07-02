<?php

use App\Http\Controllers\Booking\BookingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    // 🔒 Passager — réserver
    Route::post('trips/{uuid}/bookings',  [BookingController::class, 'store'])->name('bookings.store');

    // 🔒 Passager — mes réservations
    Route::get('bookings',                [BookingController::class, 'index'])->name('bookings.index');
    Route::get('bookings/{uuid}',         [BookingController::class, 'show'])->name('bookings.show');
    Route::post('bookings/{uuid}/cancel',  [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('bookings/{uuid}/contact',  [BookingController::class, 'contact'])->name('bookings.contact');

    // 🔒 Admin
    Route::get('admin/bookings',          [BookingController::class, 'adminIndex'])->name('admin.bookings.index');
});
