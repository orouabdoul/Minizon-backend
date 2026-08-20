<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // 'booking'  = conversation entre driver et passenger via réservation
            // 'support'  = conversation entre un user et l'admin (support Minizon)
            $table->enum('type', ['booking', 'support'])->default('booking')->after('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
