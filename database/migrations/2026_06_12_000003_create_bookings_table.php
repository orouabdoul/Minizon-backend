<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('seats_booked')->default(1);

            $table->enum('status', ['pending', 'accepted', 'rejected', 'cancelled'])->default('pending')->index();
            $table->enum('payment_status', ['unpaid', 'escrow_locked', 'released_to_driver', 'refunded'])->default('unpaid')->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
