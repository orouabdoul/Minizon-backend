<?php

use App\Http\Controllers\Withdrawal\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    // 🔒 Conducteur
    Route::post('withdrawals',      [WithdrawalController::class, 'store'])->name('withdrawals.store');
    Route::get('withdrawals',       [WithdrawalController::class, 'index'])->name('withdrawals.index');

    // 🔒 Admin
    Route::get('admin/withdrawals',                   [WithdrawalController::class, 'adminIndex'])->name('admin.withdrawals.index');
    Route::get('admin/withdrawals/balance',           [WithdrawalController::class, 'balance'])->name('admin.withdrawals.balance');
    Route::post('admin/withdrawals/{id}/process',     [WithdrawalController::class, 'process'])->name('admin.withdrawals.process');
    Route::post('admin/withdrawals/{id}/reject',      [WithdrawalController::class, 'reject'])->name('admin.withdrawals.reject');
});
