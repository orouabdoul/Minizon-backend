<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Colonnes désormais incluses dans create_bookings_table — ce fichier est conservé
// pour les bases existantes qui n'ont pas encore ces colonnes.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'pickup_city')) {
            Schema::table('bookings', function (Blueprint $table) {
                // ── Point de montée — commune → arrondissement → quartier → point précis ──
                $table->string('pickup_city', 100)->after('seats_booked')->default('');
                $table->string('pickup_arrondissement', 150)->after('pickup_city')->nullable();
                $table->string('pickup_neighborhood', 100)->after('pickup_arrondissement')->nullable();
                $table->string('pickup_address', 500)->after('pickup_neighborhood')->default('');
                $table->decimal('pickup_latitude',  10, 7)->after('pickup_address')->default(0);
                $table->decimal('pickup_longitude', 10, 7)->after('pickup_latitude')->default(0);

                // ── Point de descente — commune → arrondissement → quartier → point précis ──
                $table->string('dropoff_city', 100)->after('pickup_longitude')->default('');
                $table->string('dropoff_arrondissement', 150)->after('dropoff_city')->nullable();
                $table->string('dropoff_neighborhood', 100)->after('dropoff_arrondissement')->nullable();
                $table->string('dropoff_address', 500)->after('dropoff_neighborhood')->default('');
                $table->decimal('dropoff_latitude',  10, 7)->after('dropoff_address')->default(0);
                $table->decimal('dropoff_longitude', 10, 7)->after('dropoff_latitude')->default(0);

                // ── Prix calculé (Haversine) ──────────────────────────────────────
                $table->decimal('passenger_distance_km', 8, 2)->after('dropoff_longitude')->default(0);
                $table->unsignedInteger('calculated_price')->after('passenger_distance_km')->default(0);
            });
        } elseif (! Schema::hasColumn('bookings', 'pickup_arrondissement')) {
            // Colonne pickup_city existe mais arrondissement manquant
            Schema::table('bookings', function (Blueprint $table) {
                $table->string('pickup_arrondissement', 150)->after('pickup_city')->nullable();
                $table->string('dropoff_arrondissement', 150)->after('dropoff_city')->nullable();
                $table->string('pickup_neighborhood', 100)->nullable()->change();
                $table->string('dropoff_neighborhood', 100)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Géré par create_bookings_table sur migrate:fresh
    }
};
