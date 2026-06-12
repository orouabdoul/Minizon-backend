<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason'); // ex: 'late_cancellation', 'minor_dispute'
            $table->unsignedInteger('points_added'); // Cumulatif — à 10 points = Suspension
            $table->unsignedInteger('financial_fine')->default(0); // Amende déduite du prochain retrait (XOF)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
