<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Conducteur marque passager absent
            $table->timestamp('no_show_at')->nullable()->after('dropped_off_at')->index();
            // Passager signale conducteur absent
            $table->timestamp('driver_absent_reported_at')->nullable()->after('no_show_at');
        });

        // Ajouter 'no_show' dans le enum status si pas déjà présent
        // (Laravel ne modifie pas les enums directement, on le laisse comme string)
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['no_show_at', 'driver_absent_reported_at']);
        });
    }
};
