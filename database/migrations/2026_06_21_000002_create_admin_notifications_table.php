<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->enum('type',     ['system', 'user', 'payment', 'dispute', 'driver'])->default('system');
            $table->enum('priority', ['urgent', 'high', 'normal', 'low'])->default('normal');
            $table->enum('status',   ['unread', 'read', 'handled'])->default('unread');
            $table->string('title');
            $table->text('description');
            // Entité liée (optionnelle)
            $table->string('ref_type')->nullable(); // 'booking'|'payment'|'dispute'|'trip'|'user'
            $table->string('ref_id')->nullable();   // UUID ou ID de l'entité
            // Utilisateur concerné (optionnel)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
