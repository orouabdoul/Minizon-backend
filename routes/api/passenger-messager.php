<?php

use App\Http\Controllers\Passenger\PassengerDetailMessagerController;
use App\Http\Controllers\Passenger\PassengerMessagerController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👤 PASSENGER — Messagerie
// ============================================================

Route::middleware(['auth:sanctum'])->prefix('passenger')->group(function () {

    // Liste des threads formatés pour MessengerThread Flutter
    Route::get('messager', [PassengerMessagerController::class, 'inbox'])
        ->name('passenger.messager.inbox');

    // Contexte + messages d'une conversation (pour DetailMessagerView)
    Route::get('conversations/{uuid}/thread', [PassengerDetailMessagerController::class, 'thread'])
        ->name('passenger.messager.thread');

    // ℹ️ Les actions de chat restent dans ChatController :
    //    POST /api/bookings/{uuid}/conversation     → ouvrir/créer une conversation
    //    POST /api/conversations/{uuid}/messages    → envoyer un message
    //    POST /api/conversations/{uuid}/read        → marquer comme lu

});
