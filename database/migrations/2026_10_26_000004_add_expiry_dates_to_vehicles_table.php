<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->date('insurance_expiry_date')->nullable()->after('insurance_doc');
            $table->date('technical_inspection_expiry_date')->nullable()->after('technical_control_doc');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn(['insurance_expiry_date', 'technical_inspection_expiry_date']);
        });
    }
};
