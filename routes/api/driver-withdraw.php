<?php

use App\Http\Controllers\Driver\DriverWithdrawController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {
    Route::get('wallet',   [DriverWithdrawController::class, 'wallet'])->name('driver.wallet');
    Route::post('withdraw',[DriverWithdrawController::class, 'withdraw'])->name('driver.withdraw');
});
