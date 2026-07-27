<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // Ajouter 'bank' au provider + rendre phone_number nullable
            $table->enum('provider', ['mtn', 'moov', 'celtiis', 'bank'])->change();
            $table->string('phone_number', 20)->nullable()->change();

            // Champs spécifiques au retrait bancaire
            $table->string('bank_name', 100)->nullable()->after('phone_number');
            $table->string('account_number', 100)->nullable()->after('bank_name');
            $table->string('account_holder_name', 150)->nullable()->after('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_number', 'account_holder_name']);
            $table->enum('provider', ['mtn', 'moov', 'celtiis'])->change();
            $table->string('phone_number', 20)->nullable(false)->change();
        });
    }
};
