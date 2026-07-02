<?php

use App\Http\Controllers\Driver\DriverVehiclesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {
    Route::get('vehicles',          [DriverVehiclesController::class, 'index'])->name('driver.vehicles.index');
    Route::post('vehicles',         [DriverVehiclesController::class, 'store'])->name('driver.vehicles.store');
    Route::put('vehicles/{uuid}',   [DriverVehiclesController::class, 'update'])->name('driver.vehicles.update');
    Route::delete('vehicles/{uuid}',[DriverVehiclesController::class, 'destroy'])->name('driver.vehicles.destroy');
});
