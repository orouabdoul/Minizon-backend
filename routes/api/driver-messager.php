<?php

use App\Http\Controllers\Driver\DriverDetailMessagerController;
use App\Http\Controllers\Driver\DriverMessagerController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  💬 DRIVER — Page "Messagerie" (boîte de réception)
// ============================================================

Route::middleware(['auth:sanctum', 'approved'])->prefix('driver')->group(function () {

    // Liste des threads formatés pour MessengerThread Flutter
    Route::get('messager', [DriverMessagerController::class, 'inbox'])
        ->name('driver.messager.inbox');

    // Contexte + messages d'une conversation (pour DetailMessagerView)
    Route::get('conversations/{uuid}/thread', [DriverDetailMessagerController::class, 'thread'])
        ->name('driver.messager.thread');

    // ✏️  Modifier un message (expéditeur uniquement)
    Route::patch('messages/{uuid}', [DriverDetailMessagerController::class, 'editMessage'])
        ->name('driver.messages.edit');

    // 🗑️  Supprimer un message (expéditeur uniquement)
    Route::delete('messages/{uuid}', [DriverDetailMessagerController::class, 'deleteMessage'])
        ->name('driver.messages.delete');

    // ℹ️ Les actions de chat restent dans ChatController :
    //    POST /api/bookings/{uuid}/conversation     → ouvrir/créer une conversation
    //    GET  /api/conversations/{uuid}/messages    → messages du thread
    //    POST /api/conversations/{uuid}/messages    → envoyer un message
    //    POST /api/conversations/{uuid}/read        → marquer comme lu

});
