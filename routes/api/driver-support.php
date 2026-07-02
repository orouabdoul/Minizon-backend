<?php

use App\Http\Controllers\Driver\DriverSupportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {
    Route::get('support/faq',            [DriverSupportController::class, 'faq'])->name('driver.support.faq');
    Route::get('support/tickets',        [DriverSupportController::class, 'tickets'])->name('driver.support.tickets.index');
    Route::post('support/tickets',       [DriverSupportController::class, 'createTicket'])->name('driver.support.tickets.store');
});
