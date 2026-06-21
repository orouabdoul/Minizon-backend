<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();
        });

        $now = now();
        DB::table('platform_settings')->insert([
            ['key' => 'platform_name', 'value' => 'MINIZON',         'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'country',       'value' => 'Bénin',           'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'timezone',      'value' => 'GMT+1 (Cotonou)', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'currency',      'value' => 'XOF (Franc CFA)', 'group' => 'general', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
