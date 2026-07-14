<?php

use App\Http\Controllers\Passenger\PassengerDetailMessagerController;
use App\Http\Controllers\Passenger\PassengerMessagerController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Messagerie
// ============================================================

Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('passenger')->group(function () {

    // Liste des threads formatés pour MessengerThread Flutter
    Route::get('messager', [PassengerMessagerController::class, 'inbox'])
        ->name('passenger.messager.inbox');

    // Contexte + messages d'une conversation (pour DetailMessagerView)
    Route::get('conversations/{uuid}/thread', [PassengerDetailMessagerController::class, 'thread'])
        ->name('passenger.messager.thread');

    // ✏️  Modifier un message (expéditeur uniquement)
    Route::patch('messages/{uuid}', [PassengerDetailMessagerController::class, 'editMessage'])
        ->name('passenger.messages.edit');

    // 🗑️  Supprimer un message (expéditeur uniquement)
    Route::delete('messages/{uuid}', [PassengerDetailMessagerController::class, 'deleteMessage'])
        ->name('passenger.messages.delete');

    // 💬 Ouvrir / récupérer la conversation d'une réservation
    Route::post('bookings/{uuid}/conversation', [PassengerDetailMessagerController::class, 'getOrCreate'])
        ->name('passenger.conversations.getOrCreate');

    // 📨 Messages paginés (scroll infini)
    Route::get('conversations/{uuid}/messages', [PassengerDetailMessagerController::class, 'messages'])
        ->name('passenger.conversations.messages');

    // ✉️  Envoyer un message (texte ou fichier)
    Route::post('conversations/{uuid}/messages', [PassengerDetailMessagerController::class, 'send'])
        ->name('passenger.conversations.send');

    // ✅ Marquer tous les messages comme lus
    Route::post('conversations/{uuid}/read', [PassengerDetailMessagerController::class, 'markRead'])
        ->name('passenger.conversations.read');

});
