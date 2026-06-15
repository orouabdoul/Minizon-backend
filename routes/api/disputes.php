<?php

use App\Http\Controllers\Dispute\DisputeController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  ⚖️  LITIGES — Signalements conducteur / passager
// ============================================================

// 🔒 Utilisateurs approuvés
Route::middleware(['auth:sanctum', 'approved'])->group(function () {

    // Ouvrir un litige sur une réservation
    Route::post('bookings/{uuid}/disputes', [DisputeController::class, 'store'])->name('disputes.store');

    // Mes litiges
    Route::get('disputes',      [DisputeController::class, 'index'])->name('disputes.index');
    Route::get('disputes/{id}', [DisputeController::class, 'show'])->name('disputes.show');
});

// 🔒 Admin
Route::middleware('auth:sanctum')->group(function () {

    Route::get('admin/disputes',                  [DisputeController::class, 'adminIndex'])->name('admin.disputes.index');
    Route::post('admin/disputes/{id}/assign',     [DisputeController::class, 'assign'])->name('admin.disputes.assign');
    Route::post('admin/disputes/{id}/resolve',    [DisputeController::class, 'resolve'])->name('admin.disputes.resolve');
});
