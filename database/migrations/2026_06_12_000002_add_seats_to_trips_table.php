<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedInteger('total_seats')->default(1)->after('description');
            $table->unsignedInteger('available_seats')->default(1)->after('total_seats');

            // Index pour les recherches fréquentes
            $table->index('departure_city');
            $table->index('arrival_city');
            $table->index('departure_time');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['departure_city']);
            $table->dropIndex(['arrival_city']);
            $table->dropIndex(['departure_time']);
            $table->dropIndex(['status']);
            $table->dropColumn(['total_seats', 'available_seats']);
        });
    }
};
