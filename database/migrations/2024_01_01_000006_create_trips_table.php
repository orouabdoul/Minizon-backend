 <?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->foreignId('vehicle_id')
                  ->constrained('vehicles')
                  ->onDelete('cascade');

            // Géographie
            $table->string('departure_city', 100);
            $table->string('departure_neighborhood', 100);
            $table->string('arrival_city', 100);
            $table->string('arrival_neighborhood', 100);

            // Conditions du trajet
            $table->unsignedInteger('price_per_seat');          // En FCFA
            $table->dateTime('departure_time');
            $table->text('description')->nullable();

            // Statut du cycle de vie
            // pending  → trajet publié, en attente de départ
            // active   → voyage en cours
            // completed→ arrivé à destination
            // cancelled→ annulé par le conducteur
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])
                  ->default('pending');

            // Télémétrie GPS (mise à jour en temps réel)
            $table->decimal('current_latitude',  10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};