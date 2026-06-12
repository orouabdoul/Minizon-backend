<?php

use App\Http\Controllers\Trip\TripController;
use App\Http\Controllers\Review\ReviewController;
use Illuminate\Support\Facades\Route;

// 🔓 Consultation publique — accessible sans token
Route::get('trips',                        [TripController::class, 'index'])->name('trips.index');
Route::get('trips/{uuid}',                 [TripController::class, 'show'])->name('trips.show');
Route::get('trips/{uuid}/tracking',        [TripController::class, 'getTracking'])->name('trips.tracking');
Route::get('trips/{uuid}/reviews',         [ReviewController::class, 'tripReviews'])->name('trips.reviews.index');
Route::get('drivers/{uuid}/reviews',       [ReviewController::class, 'driverReviews'])->name('drivers.reviews.index');

// 🔒 Actions authentifiées — token requis
Route::middleware('auth:sanctum')->group(function () {
    Route::post('trips',                 [TripController::class, 'store'])->name('trips.store');
    Route::put('trips/{uuid}',           [TripController::class, 'update'])->name('trips.update');
    Route::delete('trips/{uuid}',        [TripController::class, 'destroy'])->name('trips.destroy');
    Route::get('driver/trips',           [TripController::class, 'driverTrips'])->name('driver.trips');
    Route::post('trips/{uuid}/start',    [TripController::class, 'startTrip'])->name('trips.start');
    Route::post('trips/{uuid}/end',      [TripController::class, 'endTrip'])->name('trips.end');
    Route::post('trips/{uuid}/location', [TripController::class, 'updateLocation'])->name('trips.location');
    Route::get('admin/trips',            [TripController::class, 'adminIndex'])->name('admin.trips.index');

    // ⭐ Avis passagers
    Route::post('trips/{uuid}/reviews',  [ReviewController::class, 'store'])->name('trips.reviews.store');
});