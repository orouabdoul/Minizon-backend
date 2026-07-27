<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL: ADD COLUMN IF NOT EXISTS avec CHECK constraint inline
        DB::statement("
            ALTER TABLE reviews
            ADD COLUMN IF NOT EXISTS driver_reaction VARCHAR(10) NULL
            CHECK (driver_reaction IN ('ok', 'disputed', 'reported'))
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reviews DROP COLUMN IF EXISTS driver_reaction');
    }
};
