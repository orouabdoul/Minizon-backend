<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // ── Point de montée du passager ───────────────────────────────────
            $table->string('pickup_city')->after('seats_booked')->default('');
            $table->string('pickup_neighborhood')->after('pickup_city')->default('');
            $table->string('pickup_address')->after('pickup_neighborhood')->default('');
            $table->decimal('pickup_latitude',  10, 7)->after('pickup_address')->default(0);
            $table->decimal('pickup_longitude', 10, 7)->after('pickup_latitude')->default(0);

            // ── Point de descente du passager ─────────────────────────────────
            $table->string('dropoff_city')->after('pickup_longitude')->default('');
            $table->string('dropoff_neighborhood')->after('dropoff_city')->default('');
            $table->string('dropoff_address')->after('dropoff_neighborhood')->default('');
            $table->decimal('dropoff_latitude',  10, 7)->after('dropoff_address')->default(0);
            $table->decimal('dropoff_longitude', 10, 7)->after('dropoff_latitude')->default(0);

            // ── Prix automatique calculé (Haversine) ──────────────────────────
            $table->decimal('passenger_distance_km', 8, 2)->after('dropoff_longitude')->default(0);
            $table->unsignedInteger('calculated_price')->after('passenger_distance_km')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_city', 'pickup_neighborhood', 'pickup_address',
                'pickup_latitude', 'pickup_longitude',
                'dropoff_city', 'dropoff_neighborhood', 'dropoff_address',
                'dropoff_latitude', 'dropoff_longitude',
                'passenger_distance_km', 'calculated_price',
            ]);
        });
    }
};
