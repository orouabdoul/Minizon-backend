<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'boarding_code')) {
                $table->char('boarding_code', 4)->nullable()->after('passenger_confirmed_at');
            }
            if (! Schema::hasColumn('bookings', 'boarding_method')) {
                $table->string('boarding_method', 20)->nullable()->after('boarding_code');
                // 'app' | 'code' | 'forced'
            }
            if (! Schema::hasColumn('bookings', 'driver_lat_at_boarding')) {
                $table->decimal('driver_lat_at_boarding', 10, 7)->nullable()->after('boarding_method');
            }
            if (! Schema::hasColumn('bookings', 'driver_lng_at_boarding')) {
                $table->decimal('driver_lng_at_boarding', 10, 7)->nullable()->after('driver_lat_at_boarding');
            }
            if (! Schema::hasColumn('bookings', 'passenger_lat_at_boarding')) {
                $table->decimal('passenger_lat_at_boarding', 10, 7)->nullable()->after('driver_lng_at_boarding');
            }
            if (! Schema::hasColumn('bookings', 'passenger_lng_at_boarding')) {
                $table->decimal('passenger_lng_at_boarding', 10, 7)->nullable()->after('passenger_lat_at_boarding');
            }
            if (! Schema::hasColumn('bookings', 'boarding_refused_at')) {
                $table->timestamp('boarding_refused_at')->nullable()->after('passenger_lng_at_boarding');
            }
        });
    }

    public function down(): void
    {
        // Géré par create_bookings_table sur migrate:fresh
    }
};
