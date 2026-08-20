<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── messages : soft delete + reply threading + indexes ────────────────
        Schema::table('messages', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('reply_to_id')->nullable()->after('body')
                  ->constrained('messages')->nullOnDelete();

            $table->index('sender_id',   'messages_sender_id_idx');
            $table->index('created_at',  'messages_created_at_idx');
            $table->index(['conversation_id', 'deleted_at'], 'messages_conv_deleted_idx');
        });

        // ── conversation_user : index user_id pour les requêtes de boîte ──────
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->index('user_id', 'conv_user_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['reply_to_id']);
            $table->dropIndex('messages_sender_id_idx');
            $table->dropIndex('messages_created_at_idx');
            $table->dropIndex('messages_conv_deleted_idx');
            $table->dropColumn(['deleted_at', 'reply_to_id']);
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropIndex('conv_user_user_id_idx');
        });
    }
};
