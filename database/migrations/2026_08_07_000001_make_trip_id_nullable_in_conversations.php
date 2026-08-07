<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // Supprimer l'ancienne FK NOT NULL
            $table->dropForeign(['trip_id']);

            // Rendre trip_id nullable (conversations admin directes n'ont pas de trajet)
            $table->foreignId('trip_id')
                ->nullable()
                ->change()
                ->constrained('trips')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
            $table->foreignId('trip_id')
                ->change()
                ->constrained('trips')
                ->cascadeOnDelete();
        });
    }
};
