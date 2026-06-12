<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();

            $table->enum('reason_type', ['driver_absent', 'passenger_absent', 'scam', 'bad_behavior'])->index();
            $table->text('description');
            $table->string('proof_path')->nullable(); // Capture d'écran ou preuve

            $table->enum('status', ['pending', 'investigating', 'resolved_refunded', 'resolved_paid_to_driver'])
                  ->default('pending')
                  ->index();

            $table->foreignId('assigned_admin_id')->nullable()->constrained('users');
            $table->text('admin_decision_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
