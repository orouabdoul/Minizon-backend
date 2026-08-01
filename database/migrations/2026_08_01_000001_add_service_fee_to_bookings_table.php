<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// NOTE: doublon — la colonne service_fee est créée par 2026_08_01_220122_add_service_fee_to_bookings_table.php
// Ce fichier est conservé mais ne fait rien pour éviter une erreur si 220122 a déjà tourné.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'service_fee')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedInteger('service_fee')->default(0)->after('calculated_price');
            });
        }
    }

    public function down(): void
    {
        // Suppression gérée par 2026_08_01_220122_add_service_fee_to_bookings_table.php
    }
};
