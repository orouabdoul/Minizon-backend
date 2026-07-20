<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 30)->unique();  // Code promo (ex: ETE2026)
            $table->unsignedTinyInteger('discount'); // % de réduction (1-100)
            $table->string('description')->nullable();
            $table->date('expires_at');
            $table->unsignedInteger('usage_limit')->default(500);
            $table->unsignedInteger('usage_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
