<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'departure_point'))
                $table->string('departure_point', 200)->nullable();
            if (!Schema::hasColumn('trips', 'arrival_point'))
                $table->string('arrival_point', 200)->nullable();
            if (!Schema::hasColumn('trips', 'departure_latitude'))
                $table->decimal('departure_latitude', 10, 7)->nullable();
            if (!Schema::hasColumn('trips', 'departure_longitude'))
                $table->decimal('departure_longitude', 10, 7)->nullable();
            if (!Schema::hasColumn('trips', 'arrival_latitude'))
                $table->decimal('arrival_latitude', 10, 7)->nullable();
            if (!Schema::hasColumn('trips', 'arrival_longitude'))
                $table->decimal('arrival_longitude', 10, 7)->nullable();
            if (!Schema::hasColumn('trips', 'booking_mode'))
                $table->enum('booking_mode', ['instant', 'approval'])->default('instant');
            if (!Schema::hasColumn('trips', 'max_per_booking'))
                $table->unsignedTinyInteger('max_per_booking')->default(4);
            if (!Schema::hasColumn('trips', 'estimated_duration_minutes'))
                $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            if (!Schema::hasColumn('trips', 'estimated_arrival_time'))
                $table->dateTime('estimated_arrival_time')->nullable();
            if (!Schema::hasColumn('trips', 'waypoints'))
                $table->json('waypoints')->nullable();
            if (!Schema::hasColumn('trips', 'preferences'))
                $table->json('preferences')->nullable();
            if (!Schema::hasColumn('trips', 'cancellation_policy'))
                $table->enum('cancellation_policy', ['flexible', 'moderate', 'strict'])->default('flexible');
            if (!Schema::hasColumn('trips', 'is_recurring'))
                $table->boolean('is_recurring')->default(false);
            if (!Schema::hasColumn('trips', 'recurring_days'))
                $table->json('recurring_days')->nullable();
            if (!Schema::hasColumn('trips', 'recurring_end_date'))
                $table->date('recurring_end_date')->nullable();
            if (!Schema::hasColumn('trips', 'commission_rate'))
                $table->unsignedTinyInteger('commission_rate')->default(10);
            if (!Schema::hasColumn('trips', 'is_published'))
                $table->boolean('is_published')->default(true);
            if (!Schema::hasColumn('trips', 'published_at'))
                $table->timestamp('published_at')->nullable();
            if (!Schema::hasColumn('trips', 'is_flagged'))
                $table->boolean('is_flagged')->default(false);
            if (!Schema::hasColumn('trips', 'moderation_note'))
                $table->text('moderation_note')->nullable();
            if (!Schema::hasColumn('trips', 'view_count'))
                $table->unsignedInteger('view_count')->default(0);
        });
    }

    public function down(): void {}
};
