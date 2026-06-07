<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');

            // Identité civile
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['M', 'F']);
            $table->string('email')->nullable()->unique();

            // Adresse
            $table->string('city', 100);
            $table->string('neighborhood', 100);
            $table->text('address_details')->nullable();

            // Selfies biométriques
            $table->string('selfie_front')->nullable();
            $table->string('selfie_left')->nullable();
            $table->string('selfie_right')->nullable();

            // Pièces d'identité
            $table->string('id_card_front')->nullable();
            $table->string('id_card_back')->nullable();

            // KYC
            $table->enum('kyc_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->float('kyc_matching_score')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Conducteur uniquement
            $table->string('driving_license_number', 50)->nullable();
            $table->string('driving_license_photo')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};