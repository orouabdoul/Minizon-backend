<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        // Remplir les lignes existantes avec un UUID généré
        DB::table('disputes')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            DB::table('disputes')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
