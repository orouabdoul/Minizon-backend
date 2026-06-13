<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // uuid déjà présent via une migration précédente — rien à faire
    }

    public function down(): void
    {
        // rien à faire
    }
};
