<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        if (Schema::hasColumn('bookings', 'service_fee')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('service_fee');
            });
        }
    }
};
