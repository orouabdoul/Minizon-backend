<?php

use App\Http\Controllers\Admin\AdminMessagingController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Messagerie directe Admin ↔ Conducteur/Passager
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/messaging')->group(function () {

    // ── Recherche utilisateurs (pour démarrer une nouvelle conversation) ──────
    Route::get('users', [AdminMessagingController::class, 'searchUsers']);

    // ── Démarrer / retrouver une conversation avec un utilisateur spécifique ──
    Route::post('start', [AdminMessagingController::class, 'startConversation']);

    // ── Diffuser un message à tous les utilisateurs d'un groupe ──────────────
    Route::post('broadcast', [AdminMessagingController::class, 'broadcast']);

    // ── Envoyer un message à des utilisateurs sélectionnés (multi-select) ────
    Route::post('send-to-selected', [AdminMessagingController::class, 'sendToSelected']);

    // ── CRUD conversations — routes nommées AVANT les wildcards {uuid} ────────
    Route::get('conversations', [AdminMessagingController::class, 'conversations']);

    // ── Wildcards ─────────────────────────────────────────────────────────────
    Route::get('conversations/{uuid}',          [AdminMessagingController::class, 'show']);
    Route::post('conversations/{uuid}/messages', [AdminMessagingController::class, 'sendMessage'])
        ->middleware('throttle:60,1');

    // ── Modifier / Supprimer un message admin ─────────────────────────────────
    Route::patch('messages/{uuid}',  [AdminMessagingController::class, 'editMessage']);
    Route::delete('messages/{uuid}', [AdminMessagingController::class, 'deleteMessage']);
});
