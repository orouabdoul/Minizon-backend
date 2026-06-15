<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: drop and recreate the CHECK constraint to add 'cancelled'
        DB::statement("ALTER TABLE trip_validations DROP CONSTRAINT IF EXISTS trip_validations_status_check");
        DB::statement("ALTER TABLE trip_validations ADD CONSTRAINT trip_validations_status_check CHECK (status IN ('waiting', 'released', 'disputed', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trip_validations DROP CONSTRAINT IF EXISTS trip_validations_status_check");
        DB::statement("ALTER TABLE trip_validations ADD CONSTRAINT trip_validations_status_check CHECK (status IN ('waiting', 'released', 'disputed'))");
    }
};
