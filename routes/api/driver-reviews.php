<?php

use App\Http\Controllers\Driver\DriverReviewsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {
    Route::get('reviews',                [DriverReviewsController::class, 'index'])->name('driver.reviews.index');
    Route::post('reviews/{uuid}/reply',  [DriverReviewsController::class, 'reply'])->name('driver.reviews.reply');
});
