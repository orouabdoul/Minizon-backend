<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');           // ex: 'auth.failed_otp', 'payment.bypass_attempt'
            $table->string('ip_address', 45);
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable(); // Contexte suspect

            // Pas de updated_at — journal immuable
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
