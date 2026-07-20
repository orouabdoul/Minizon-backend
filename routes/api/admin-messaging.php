<?php

use App\Http\Controllers\Admin\AdminMessagingController;
use Illuminate\Support\Facades\Route;

// ============================================================
//  👑 ADMIN — Messagerie directe Admin ↔ Conducteur
//
//  Distinct de /admin/conversations (modération driver-passenger).
//  Ces conversations n'ont pas de trip_id ni booking_id.
//
//  Flux frontend (MessagingScreen) :
//    GET  /api/admin/messaging/conversations            → liste + totalUnread
//    GET  /api/admin/messaging/conversations/{uuid}     → messages + marque lu
//    POST /api/admin/messaging/conversations/{uuid}/messages → envoyer
//    POST /api/admin/messaging/broadcast                → diffuser (tous|en_ligne|en_trajet)
// ============================================================

Route::middleware('auth:sanctum')->prefix('admin/messaging')->group(function () {

    Route::get('conversations',
        [AdminMessagingController::class, 'conversations']);

    Route::get('conversations/{uuid}',
        [AdminMessagingController::class, 'show']);

    Route::post('conversations/{uuid}/messages',
        [AdminMessagingController::class, 'sendMessage']);

    Route::post('broadcast',
        [AdminMessagingController::class, 'broadcast']);

});
