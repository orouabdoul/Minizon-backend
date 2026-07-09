<?php

use App\Http\Controllers\Passenger\PassengerNotificationsController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Page "Notifications"
// ============================================================

Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('passenger')->group(function () {

    // Liste filtrée pour AppNotification Flutter
    Route::get('notifications', [PassengerNotificationsController::class, 'index'])
        ->name('passenger.notifications.index');

    // Tout marquer comme lu
    Route::post('notifications/read-all', [PassengerNotificationsController::class, 'markAllRead'])
        ->name('passenger.notifications.read-all');

    // Marquer une notification comme lue
    Route::post('notifications/{id}/read', [PassengerNotificationsController::class, 'markRead'])
        ->name('passenger.notifications.read');

    // Supprimer une notification (swipe-to-dismiss)
    Route::delete('notifications/{id}', [PassengerNotificationsController::class, 'destroy'])
        ->name('passenger.notifications.destroy');

});
