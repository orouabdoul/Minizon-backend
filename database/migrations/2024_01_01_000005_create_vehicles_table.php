<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->unique()
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('vehicle_type_id')
                  ->constrained('vehicle_types')
                  ->onDelete('restrict');

            // Infos véhicule
            $table->string('brand', 100);
            $table->string('model', 100);
            $table->string('color', 50);
            $table->string('license_plate', 20)->unique();
            $table->unsignedTinyInteger('available_seats');

            // Documents
            $table->string('vehicle_photo')->nullable();
            $table->string('registration_doc')->nullable();   // Carte grise
            $table->string('insurance_doc')->nullable();      // Assurance
            $table->string('tvm_doc')->nullable();            // TVM (optionnel)
            $table->string('technical_control_doc')->nullable(); // Visite technique (optionnel)

            // Statut
            $table->boolean('is_approved')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};