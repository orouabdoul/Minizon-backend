<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de gestion admin des virements conducteurs (PayoutsScreen).
 *
 * Distincte de "withdrawals" (demandes driver-initiated).
 * Peut être alimentée manuellement par l'admin ou générée depuis les paiements.
 *
 * Status : en_attente → en_traitement → payé
 *                      ↘ échoué → en_attente (retry)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_payouts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('driver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('gross_amount');      // Revenus bruts (FCFA)
            $table->unsignedInteger('commission_amount'); // Commission plateforme
            $table->unsignedInteger('net_amount');        // Net à verser
            $table->unsignedSmallInteger('trips_count')->default(0);
            $table->string('method', 40)->default('MTN Mobile Money'); // Méthode de paiement
            $table->string('phone_number', 20)->nullable();
            $table->string('reference')->unique();
            $table->string('status', 20)->default('en_attente'); // en_attente|en_traitement|payé|échoué
            $table->string('failed_reason')->nullable();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable(); // Quand mis en traitement
            $table->timestamp('paid_at')->nullable();      // Quand confirmé payé
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payouts');
    }
};
