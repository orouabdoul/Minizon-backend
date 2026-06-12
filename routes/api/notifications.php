<?php

use App\Http\Controllers\Device\DeviceController;
use App\Http\Controllers\Notification\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // 🔔 Notifications in-app
    Route::get('notifications',              [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read-all',    [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('notifications/{id}/read',   [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('notifications/{id}',      [NotificationController::class, 'destroy'])->name('notifications.destroy');

    // 📱 Token FCM (appareil)
    Route::post('device/token',    [DeviceController::class, 'register'])->name('device.token.register');
    Route::delete('device/token',  [DeviceController::class, 'revoke'])->name('device.token.revoke');
});
