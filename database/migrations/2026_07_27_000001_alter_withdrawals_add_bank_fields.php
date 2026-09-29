<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE withdrawals DROP CONSTRAINT IF EXISTS withdrawals_provider_check');
            DB::statement("ALTER TABLE withdrawals ADD CONSTRAINT withdrawals_provider_check CHECK (provider IN ('mtn', 'moov', 'celtiis', 'bank'))");
            DB::statement('ALTER TABLE withdrawals ALTER COLUMN phone_number DROP NOT NULL');
        } else {
            // MySQL / MariaDB
            DB::statement('ALTER TABLE withdrawals MODIFY COLUMN phone_number VARCHAR(20) NULL');
        }

        // Colonnes bancaires (compatible PostgreSQL + MySQL)
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

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE withdrawals DROP CONSTRAINT IF EXISTS withdrawals_provider_check');
            DB::statement("ALTER TABLE withdrawals ADD CONSTRAINT withdrawals_provider_check CHECK (provider IN ('mtn', 'moov', 'celtiis'))");
            DB::statement("UPDATE withdrawals SET phone_number = '' WHERE phone_number IS NULL");
            DB::statement('ALTER TABLE withdrawals ALTER COLUMN phone_number SET NOT NULL');
        } else {
            DB::statement("UPDATE withdrawals SET phone_number = '' WHERE phone_number IS NULL");
            DB::statement('ALTER TABLE withdrawals MODIFY COLUMN phone_number VARCHAR(20) NOT NULL');
        }
    }
};
