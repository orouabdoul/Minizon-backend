<?php

use App\Http\Controllers\Chat\ChatController;
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

    // 💬 Ouvrir / récupérer la conversation d'une réservation
    Route::post('bookings/{uuid}/conversation', [ChatController::class, 'getOrCreate'])
        ->name('driver.conversations.getOrCreate');

    // 📨 Messages paginés (scroll infini)
    Route::get('conversations/{uuid}/messages', [ChatController::class, 'messages'])
        ->name('driver.conversations.messages');

    // ✉️  Envoyer un message (texte ou fichier)
    Route::post('conversations/{uuid}/messages', [ChatController::class, 'send'])
        ->name('driver.conversations.send');

    // ✅ Marquer tous les messages comme lus
    Route::post('conversations/{uuid}/read', [ChatController::class, 'markRead'])
        ->name('driver.conversations.read');

});
