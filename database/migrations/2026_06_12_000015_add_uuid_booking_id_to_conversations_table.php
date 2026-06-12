<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->foreignId('booking_id')
                ->nullable()
                ->unique()
                ->constrained('bookings')
                ->nullOnDelete()
                ->after('trip_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropColumn(['uuid', 'booking_id']);
        });
    }
};
