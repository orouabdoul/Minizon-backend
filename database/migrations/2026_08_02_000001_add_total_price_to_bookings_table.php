<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'total_price')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedInteger('total_price')->default(0)->after('service_fee');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'total_price')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('total_price');
            });
        }
    }
};
