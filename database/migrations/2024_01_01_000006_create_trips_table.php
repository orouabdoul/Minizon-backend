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

            // ── Géographie départ — commune → arrondissement → quartier → point précis ──
            $table->string('departure_city', 100);
            $table->string('departure_arrondissement', 150)->nullable();
            $table->string('departure_neighborhood', 100)->nullable();
            $table->string('departure_point', 200)->nullable();
            $table->decimal('departure_latitude',  10, 7)->nullable();
            $table->decimal('departure_longitude', 10, 7)->nullable();

            // ── Géographie arrivée — commune → arrondissement → quartier → point précis ──
            $table->string('arrival_city', 100);
            $table->string('arrival_arrondissement', 150)->nullable();
            $table->string('arrival_neighborhood', 100)->nullable();
            $table->string('arrival_point', 200)->nullable();
            $table->decimal('arrival_latitude',  10, 7)->nullable();
            $table->decimal('arrival_longitude', 10, 7)->nullable();

            // ── Route calculée ────────────────────────────────────────────────────
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedInteger('estimated_duration_minutes')->nullable();
            $table->dateTime('estimated_arrival_time')->nullable();

            // ── Prix & conditions ────────────────────────────────────────────────
            $table->unsignedInteger('price_per_seat');     // En FCFA
            $table->dateTime('departure_time');
            $table->text('description')->nullable();

            // ── Capacité & mode de réservation ───────────────────────────────────
            $table->unsignedTinyInteger('total_seats')->default(1);
            $table->unsignedTinyInteger('available_seats')->default(1);
            $table->enum('booking_mode', ['instant', 'approval'])->default('instant');
            $table->unsignedTinyInteger('max_per_booking')->nullable();

            // ── Préférences & arrêts ─────────────────────────────────────────────
            $table->json('waypoints')->nullable();
            $table->json('preferences')->nullable();
            $table->enum('cancellation_policy', ['flexible', 'moderate', 'strict'])->default('flexible');

            // ── Récurrence ───────────────────────────────────────────────────────
            $table->boolean('is_recurring')->default(false);
            $table->json('recurring_days')->nullable();
            $table->date('recurring_end_date')->nullable();

            // ── Financier ────────────────────────────────────────────────────────
            $table->unsignedTinyInteger('commission_rate')->default(10);   // %

            // ── Statut du cycle de vie ───────────────────────────────────────────
            // pending   → trajet publié, en attente de départ
            // active    → voyage en cours
            // completed → arrivé à destination
            // cancelled → annulé par le conducteur
            $table->enum('status', ['pending', 'active', 'completed', 'cancelled'])->default('pending');

            // ── Visibilité ───────────────────────────────────────────────────────
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();

            // ── Timestamps du cycle de vie ───────────────────────────────────────
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // ── Télémétrie GPS temps réel ────────────────────────────────────────
            $table->decimal('current_latitude',  10, 7)->nullable();
            $table->decimal('current_longitude', 10, 7)->nullable();
            $table->decimal('current_speed', 5, 2)->nullable();    // km/h
            $table->timestamp('location_updated_at')->nullable();

            // ── Modération ───────────────────────────────────────────────────────
            $table->boolean('is_flagged')->default(false);
            $table->text('moderation_note')->nullable();

            // ── Statistiques ─────────────────────────────────────────────────────
            $table->unsignedInteger('view_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
