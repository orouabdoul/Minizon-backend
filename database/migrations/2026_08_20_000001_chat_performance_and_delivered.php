<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── messages : statut "délivré" + index performances ──────────────────
        Schema::table('messages', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('read_at');

            // Optimise les requêtes since_id (polling incrémental)
            $table->index(['conversation_id', 'id'], 'messages_conv_id_idx');
        });

        // ── users : dernière activité (statut en ligne fiable) ────────────────
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable()->after('updated_at');
            }
        });

        // ── conversation_participants : compteur non-lus par participant ───────
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->integer('unread_count')->default(0)->after('user_id');
            $table->timestamp('last_read_at')->nullable()->after('unread_count');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conv_id_idx');
            $table->dropColumn('delivered_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_seen_at');
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn(['unread_count', 'last_read_at']);
        });
    }
};
