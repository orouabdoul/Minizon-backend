<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();

            $table->boolean('passenger_confirmed')->default(false);
            $table->timestamp('passenger_confirmed_at')->nullable();

            // Heure d'arrivée théorique + 24 h — libération automatique des fonds
            $table->timestamp('auto_release_at')->nullable()->index();

            $table->enum('status', ['waiting', 'released', 'disputed'])->default('waiting')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_validations');
    }
};
