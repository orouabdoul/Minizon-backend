<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users'); // Qui paye

            $table->unsignedInteger('gross_amount');      // Montant total payé par le passager (XOF)
            $table->unsignedInteger('commission_amount'); // Part MINIZON
            $table->unsignedInteger('net_amount');        // Part nette pour le conducteur

            $table->enum('provider', ['mtn', 'moov', 'celtiis', 'card'])->index();

            // Anti double-débit sur réseau instable
            $table->string('idempotency_key')->unique();
            $table->string('transaction_reference')->unique()->index(); // Référence interne MINIZON
            $table->string('provider_reference')->nullable()->unique(); // Référence opérateur

            $table->enum('status', ['pending', 'locked', 'success', 'failed', 'refunded'])->default('pending')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
