<?php

use App\Http\Controllers\Chat\ChatController;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------
// 👑 ADMIN — Modération des conversations
// -----------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('admin')->name('admin.conversations.')->group(function () {

    Route::get('conversations',
        [ChatController::class, 'adminIndex'])->name('index');

    Route::get('conversations/{uuid}/messages',
        [ChatController::class, 'adminMessages'])->name('messages');

    Route::delete('conversations/{uuid}/messages/{id}',
        [ChatController::class, 'adminDeleteMessage'])->name('message.delete');

    Route::delete('conversations/{uuid}',
        [ChatController::class, 'adminDelete'])->name('delete');
});
