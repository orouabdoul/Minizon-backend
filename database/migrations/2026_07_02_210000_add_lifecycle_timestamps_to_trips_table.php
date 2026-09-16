<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'started_at'))
                $table->timestamp('started_at')->nullable();
            if (!Schema::hasColumn('trips', 'completed_at'))
                $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void {}
};
