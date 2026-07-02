<?php

use App\Http\Controllers\Driver\DriverNotificationsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  🔔 DRIVER — Page "Notifications"
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Liste paginée + filtrée pour DriverNotificationModel Flutter
    Route::get('notifications', [DriverNotificationsController::class, 'index'])
        ->name('driver.notifications.index');

    // Tout marquer comme lu
    Route::post('notifications/read-all', [DriverNotificationsController::class, 'markAllRead'])
        ->name('driver.notifications.read-all');

    // Marquer une notification comme lue
    Route::post('notifications/{id}/read', [DriverNotificationsController::class, 'markRead'])
        ->name('driver.notifications.read');

});
