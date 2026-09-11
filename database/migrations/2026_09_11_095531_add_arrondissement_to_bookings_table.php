<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Corrige la DB Render : pickup_arrondissement et dropoff_arrondissement manquants
// (migration 2026_07_14 marquée exécutée sans créer ces colonnes sur Render)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'pickup_arrondissement')) {
                $table->string('pickup_arrondissement', 150)->after('pickup_city')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'dropoff_arrondissement')) {
                $table->string('dropoff_arrondissement', 150)->after('dropoff_city')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumnIfExists('pickup_arrondissement');
            $table->dropColumnIfExists('dropoff_arrondissement');
        });
    }
};
