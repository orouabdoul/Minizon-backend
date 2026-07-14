<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Point de montée du passager (obligatoire à la réservation)
            $table->string('pickup_address')->after('seats_booked')->default('');
            $table->decimal('pickup_latitude',  10, 7)->after('pickup_address')->default(0);
            $table->decimal('pickup_longitude', 10, 7)->after('pickup_latitude')->default(0);

            // Point de descente du passager (obligatoire à la réservation)
            $table->string('dropoff_address')->after('pickup_longitude')->default('');
            $table->decimal('dropoff_latitude',  10, 7)->after('dropoff_address')->default(0);
            $table->decimal('dropoff_longitude', 10, 7)->after('dropoff_latitude')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn([
                'pickup_address',
                'pickup_latitude',
                'pickup_longitude',
                'dropoff_address',
                'dropoff_latitude',
                'dropoff_longitude',
            ]);
        });
    }
};
