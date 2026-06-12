<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount'); // Montant demandé en XOF
            $table->enum('provider', ['mtn', 'moov', 'celtiis'])->index();
            $table->string('phone_number', 20); // Numéro MoMo de réception
            $table->string('reference')->unique();

            $table->enum('status', ['pending', 'approved', 'rejected', 'failed'])->default('pending')->index();
            $table->text('failed_reason')->nullable();
            $table->timestamp('processed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
