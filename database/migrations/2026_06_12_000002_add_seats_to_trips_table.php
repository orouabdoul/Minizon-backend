<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'total_seats')) {
                $table->unsignedInteger('total_seats')->default(1)->after('description');
            }
            if (!Schema::hasColumn('trips', 'available_seats')) {
                $table->unsignedInteger('available_seats')->default(1)->after('total_seats');
            }

            // Index pour les recherches fréquentes (ignorés s'ils existent déjà)
            try { $table->index('departure_city'); } catch (\Exception $e) {}
            try { $table->index('arrival_city'); } catch (\Exception $e) {}
            try { $table->index('departure_time'); } catch (\Exception $e) {}
            try { $table->index('status'); } catch (\Exception $e) {}
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
