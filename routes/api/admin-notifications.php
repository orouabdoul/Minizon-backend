<?php

use App\Http\Controllers\Admin\AdminNotificationController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🔔 ROUTES ADMIN — Centre de notifications (Back-Office)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/notifications')->group(function () {

    Route::get('metrics',          [AdminNotificationController::class, 'metrics']);
    Route::get('/',                [AdminNotificationController::class, 'index']);
    Route::post('read-all',        [AdminNotificationController::class, 'markAllRead']);
    Route::post('{uuid}/read',     [AdminNotificationController::class, 'markAsRead']);
    Route::post('{uuid}/handle',   [AdminNotificationController::class, 'markAsHandled']);
    Route::delete('{uuid}',        [AdminNotificationController::class, 'destroy']);
});
