<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit la table audit_logs pour le back-office admin :
 * - action_type : type structuré visible dans l'UI (connexion, suspension…)
 * - severity    : info | avertissement | critique
 * - description : texte lisible affiché dans la colonne "Description"
 * - target_type : type de la ressource ciblée (user, trip, booking…)
 * - target_name : nom affiché de la cible (ex: "Koffi Mensah")
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('action_type', 50)->nullable()->after('action');
            $table->string('severity', 20)->default('info')->after('action_type');
            $table->text('description')->nullable()->after('severity');
            $table->string('target_type', 30)->nullable()->after('description');
            $table->string('target_name', 191)->nullable()->after('target_type');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['action_type', 'severity', 'description', 'target_type', 'target_name']);
        });
    }
};
