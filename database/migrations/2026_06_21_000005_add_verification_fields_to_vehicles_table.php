<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->enum('verification_status', ['pending', 'approved', 'rejected', 'suspended'])
                  ->default('pending')
                  ->after('is_approved');
            $table->text('rejection_reason')->nullable()->after('verification_status');
            $table->timestamp('verified_at')->nullable()->after('rejection_reason');
            $table->unsignedBigInteger('verified_by')->nullable()->after('verified_at');
            $table->foreign('verified_by')->references('id')->on('users')->nullOnDelete();
        });

        // Synchroniser is_approved → verification_status pour les véhicules existants
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE vehicles SET verification_status = CASE WHEN is_approved = true THEN 'approved' ELSE 'pending' END"
        );
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn(['verification_status', 'rejection_reason', 'verified_at', 'verified_by']);
        });
    }
};
