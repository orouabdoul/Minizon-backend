<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Colonne désormais incluse dans create_bookings_table — ce fichier est conservé
// pour les bases existantes qui n'ont pas encore cette colonne.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'picked_up_at')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->timestamp('picked_up_at')->nullable()->after('payment_status');
            });
        }
    }

    public function down(): void
    {
        // Géré par create_bookings_table sur migrate:fresh
    }
};
