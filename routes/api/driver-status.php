<?php

use App\Http\Controllers\Driver\DriverStatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Driver — Statut de sanction
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('driver/status', [DriverStatusController::class, 'index'])
        ->name('driver.status');
});
