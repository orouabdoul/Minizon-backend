<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            // -------------------------------------------------------------------
            //  Identifiants
            // -------------------------------------------------------------------
            $table->id();
            $table->uuid('uuid')->unique()->comment('Identifiant public exposé dans les URLs');

            // -------------------------------------------------------------------
            //  Acteurs
            // -------------------------------------------------------------------
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete()
                  ->comment('Conducteur auteur du trajet');

            $table->foreignId('vehicle_id')
                  ->constrained('vehicles')
                  ->restrictOnDelete()
                  ->comment('Véhicule assigné — suppression bloquée si trajet lié');

            // -------------------------------------------------------------------
            //  Géographie
            // -------------------------------------------------------------------
            $table->string('departure_city');
            $table->string('departure_neighborhood');
            $table->string('arrival_city');
            $table->string('arrival_neighborhood');

            // -------------------------------------------------------------------
            //  Conditions du trajet
            // -------------------------------------------------------------------
            $table->unsignedInteger('price_per_seat')->comment('Prix en FCFA');
            $table->dateTime('departure_time')->comment('Date et heure de départ prévues');
            $table->text('description')->nullable()->comment('Instructions pour les passagers');

            // -------------------------------------------------------------------
            //  Statut
            // -------------------------------------------------------------------
            $table->enum('status', ['pending', 'active', 'completed'])
                  ->default('pending')
                  ->comment('pending = ouvert | active = en cours | completed = terminé');

            // -------------------------------------------------------------------
            //  Télémétrie GPS (mis à jour en temps réel)
            // -------------------------------------------------------------------
            $table->decimal('current_latitude',  10, 7)->nullable()->comment('WGS 84');
            $table->decimal('current_longitude', 10, 7)->nullable()->comment('WGS 84');

            // -------------------------------------------------------------------
            //  Horodatage
            // -------------------------------------------------------------------
            $table->timestamps();

            // -------------------------------------------------------------------
            //  Index pour les recherches fréquentes
            // -------------------------------------------------------------------
            $table->index('status');
            $table->index('departure_city');
            $table->index('arrival_city');
            $table->index('departure_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};