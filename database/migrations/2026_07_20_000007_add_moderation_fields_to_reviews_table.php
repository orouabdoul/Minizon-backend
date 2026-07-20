<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les champs de modération à la table reviews :
 * - status     : 'visible' | 'masqué' | 'signalé'
 * - report_count : nombre de signalements reçus par l'avis
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('status', 10)->default('visible')->after('comment');
            $table->unsignedInteger('report_count')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['status', 'report_count']);
        });
    }
};
