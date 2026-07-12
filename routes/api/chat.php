<?php

use App\Http\Controllers\Chat\ChatController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'not_blocked'])->group(function () {

    // 📋 Liste des conversations de l'utilisateur
    Route::get('conversations', [ChatController::class, 'index'])->name('conversations.index');

    // 💬 Ouvrir / récupérer la conversation d'une réservation acceptée
    Route::post('bookings/{uuid}/conversation', [ChatController::class, 'getOrCreate'])->name('conversations.getOrCreate');

    // 📨 Messages d'une conversation (scroll infini)
    Route::get('conversations/{uuid}/messages', [ChatController::class, 'messages'])->name('conversations.messages');

    // ✉️  Envoyer un message (texte ou image)
    Route::post('conversations/{uuid}/messages', [ChatController::class, 'send'])->name('conversations.send');

    // ✅ Marquer tous les messages comme lus
    Route::post('conversations/{uuid}/read', [ChatController::class, 'markRead'])->name('conversations.read');

    // ✏️  Modifier le texte d'un message (expéditeur uniquement)
    Route::patch('messages/{uuid}', [ChatController::class, 'editMessage'])->name('messages.edit');

    // 🗑️  Supprimer un message (expéditeur uniquement)
    Route::delete('messages/{uuid}', [ChatController::class, 'deleteMessage'])->name('messages.delete');

    // -----------------------------------------------------------------------
    // 👑 ADMIN — Modération des conversations
    // -----------------------------------------------------------------------
    Route::prefix('admin')->name('admin.conversations.')->group(function () {

        // Toutes les conversations de la plateforme
        Route::get('conversations',
            [ChatController::class, 'adminIndex'])->name('index');

        // Messages d'une conversation spécifique
        Route::get('conversations/{uuid}/messages',
            [ChatController::class, 'adminMessages'])->name('messages');

        // Supprimer un message (modération)
        Route::delete('conversations/{uuid}/messages/{id}',
            [ChatController::class, 'adminDeleteMessage'])->name('message.delete');

        // Supprimer / fermer une conversation
        Route::delete('conversations/{uuid}',
            [ChatController::class, 'adminDelete'])->name('delete');
    });
});
