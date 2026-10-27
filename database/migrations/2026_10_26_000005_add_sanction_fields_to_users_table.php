<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('warning_count')->default(0)->after('role');
            $table->unsignedSmallInteger('visibility_score')->default(100)->after('warning_count');
            $table->timestamp('suspended_until')->nullable()->after('visibility_score');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['warning_count', 'visibility_score', 'suspended_until']);
        });
    }
};
