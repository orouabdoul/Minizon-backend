<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Numéro Mobile Money utilisé pour le paiement (format local 8 chiffres)
            $table->string('phone_number', 20)->nullable()->after('provider');

            // transaction_reference peut être null avant la réponse FedaPay
            $table->string('transaction_reference')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('phone_number');
            $table->string('transaction_reference')->nullable(false)->change();
        });
    }
};
