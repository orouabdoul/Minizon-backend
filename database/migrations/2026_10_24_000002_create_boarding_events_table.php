<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boarding_events')) return;

        Schema::create('boarding_events', function (Blueprint $table) {
            $table->id();
            $table->string('booking_uuid', 36)->index();
            $table->string('trip_uuid',    36)->index();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->unsignedBigInteger('passenger_id')->nullable();

            // 'request_sent' | 'confirmed_app' | 'confirmed_no_gps' | 'code_verified'
            // | 'refused' | 'forced'
            $table->string('event_type', 30);

            $table->decimal('driver_lat',    10, 7)->nullable();
            $table->decimal('driver_lng',    10, 7)->nullable();
            $table->decimal('passenger_lat', 10, 7)->nullable();
            $table->decimal('passenger_lng', 10, 7)->nullable();
            $table->decimal('distance_meters', 8, 2)->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('driver_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('passenger_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_events');
    }
};
