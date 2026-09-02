<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seats_booked')->default(1);

            // ── Point de montée — commune → arrondissement → quartier → point précis ──
            $table->string('pickup_city', 100)->default('');
            $table->string('pickup_arrondissement', 150)->nullable();
            $table->string('pickup_neighborhood', 100)->nullable();
            $table->string('pickup_address', 500)->default('');
            $table->decimal('pickup_latitude',  10, 7)->default(0);
            $table->decimal('pickup_longitude', 10, 7)->default(0);

            // ── Point de descente — commune → arrondissement → quartier → point précis ──
            $table->string('dropoff_city', 100)->default('');
            $table->string('dropoff_arrondissement', 150)->nullable();
            $table->string('dropoff_neighborhood', 100)->nullable();
            $table->string('dropoff_address', 500)->default('');
            $table->decimal('dropoff_latitude',  10, 7)->default(0);
            $table->decimal('dropoff_longitude', 10, 7)->default(0);

            // ── Prix calculé automatiquement (Haversine) ──────────────────────────
            $table->decimal('passenger_distance_km', 8, 2)->default(0);
            $table->unsignedInteger('calculated_price')->default(0);
            $table->unsignedInteger('service_fee')->default(0);
            $table->unsignedInteger('total_price')->default(0);

            // ── Statut ───────────────────────────────────────────────────────────
            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending')->index();
            $table->enum('payment_status', ['unpaid', 'escrow_locked', 'released_to_driver', 'refunded'])->default('unpaid')->index();

            // ── Timestamps du cycle de vie ───────────────────────────────────────
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('passenger_confirmed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
