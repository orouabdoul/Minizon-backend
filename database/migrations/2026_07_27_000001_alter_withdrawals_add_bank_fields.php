<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL stocke les enum Laravel comme varchar + CHECK constraint.
        // ->change() génère du SQL invalide pour PostgreSQL → raw SQL requis.
        DB::statement('ALTER TABLE withdrawals DROP CONSTRAINT IF EXISTS withdrawals_provider_check');
        DB::statement("ALTER TABLE withdrawals ADD CONSTRAINT withdrawals_provider_check CHECK (provider IN ('mtn', 'moov', 'celtiis', 'bank'))");

        // Rendre phone_number nullable (requis seulement pour MoMo)
        DB::statement('ALTER TABLE withdrawals ALTER COLUMN phone_number DROP NOT NULL');

        // Colonnes bancaires
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('bank_name', 100)->nullable()->after('phone_number');
            $table->string('account_number', 100)->nullable()->after('bank_name');
            $table->string('account_holder_name', 150)->nullable()->after('account_number');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_number', 'account_holder_name']);
        });

        DB::statement('ALTER TABLE withdrawals DROP CONSTRAINT IF EXISTS withdrawals_provider_check');
        DB::statement("ALTER TABLE withdrawals ADD CONSTRAINT withdrawals_provider_check CHECK (provider IN ('mtn', 'moov', 'celtiis'))");

        // Remettre phone_number NOT NULL (vider les NULL d'abord pour éviter l'erreur)
        DB::statement("UPDATE withdrawals SET phone_number = '' WHERE phone_number IS NULL");
        DB::statement('ALTER TABLE withdrawals ALTER COLUMN phone_number SET NOT NULL');
    }
};
