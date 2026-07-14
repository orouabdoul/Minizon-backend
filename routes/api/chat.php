<?php

use App\Http\Controllers\Admin\AdminConversationController;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------
// 👑 ADMIN — Modération des conversations
// -----------------------------------------------------------------------
Route::middleware(['auth:sanctum', 'not_blocked'])->prefix('admin')->name('admin.conversations.')->group(function () {

    Route::get('conversations',
        [AdminConversationController::class, 'index'])->name('index');

    Route::get('conversations/{uuid}/messages',
        [AdminConversationController::class, 'messages'])->name('messages');

    Route::delete('conversations/{uuid}/messages/{id}',
        [AdminConversationController::class, 'deleteMessage'])->name('message.delete');

    Route::delete('conversations/{uuid}',
        [AdminConversationController::class, 'destroy'])->name('delete');
});
