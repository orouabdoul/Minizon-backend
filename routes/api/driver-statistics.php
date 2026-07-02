<?php

use App\Http\Controllers\Driver\DriverStatsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->group(function () {
    Route::get('driver/stats', [DriverStatsController::class, 'index'])->name('driver.stats');
});
