<?php

use App\Http\Controllers\Admin\AdminSanctionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin — Sanctions conducteur
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {

    // Émettre un avertissement (déclenche suspension automatique au 3e)
    Route::post('users/{uuid}/warn',      [AdminSanctionController::class, 'warn'])
        ->name('admin.sanctions.warn');

    // Suspension manuelle
    Route::post('users/{uuid}/suspend',   [AdminSanctionController::class, 'suspend'])
        ->name('admin.sanctions.suspend');

    // Lever une suspension
    Route::post('users/{uuid}/unsuspend', [AdminSanctionController::class, 'unsuspend'])
        ->name('admin.sanctions.unsuspend');
});
