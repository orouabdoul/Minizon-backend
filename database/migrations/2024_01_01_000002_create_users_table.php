<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('phone', 20)->unique();
            $table->string('password')->nullable();          // null pour les users OTP uniquement
            $table->foreignId('role_id')
                  ->default(2)                               // 2 = passenger par défaut
                  ->constrained('roles')
                  ->onDelete('restrict');

            // OTP
            $table->string('otp_code', 6)->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();

            // Statut compte
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('blocked_until')->nullable();
            $table->unsignedInteger('penalty_points')->default(0);

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};