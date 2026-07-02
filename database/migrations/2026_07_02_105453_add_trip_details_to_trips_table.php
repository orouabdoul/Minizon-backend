<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajout des champs de gestion avancée aux trajets :
 *  - Points précis (texte + GPS)
 *  - Mode de réservation (instant / approbation)
 *  - Durée et heure d'arrivée estimées
 *  - Arrêts intermédiaires (waypoints)
 *  - Politique d'annulation
 *  - Récurrence
 *  - Préférences conducteur
 *  - Commission plateforme
 *  - Brouillon & modération
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {

            // ── Points de rendez-vous précis ───────────────────────────────
            // Texte libre : "Carrefour Étoile Rouge", "Gare routière de Parakou"
            $table->string('departure_point', 200)->nullable()->after('departure_neighborhood');
            $table->string('arrival_point',   200)->nullable()->after('arrival_neighborhood');

            // ── Coordonnées GPS précises du point de collecte / dépôt ─────
            $table->decimal('departure_latitude',  10, 7)->nullable()->after('departure_point');
            $table->decimal('departure_longitude', 10, 7)->nullable()->after('departure_latitude');
            $table->decimal('arrival_latitude',    10, 7)->nullable()->after('arrival_point');
            $table->decimal('arrival_longitude',   10, 7)->nullable()->after('arrival_latitude');

            // ── Mode de réservation ────────────────────────────────────────
            // instant  : passager réserve → automatiquement accepté
            // approval : conducteur doit valider manuellement chaque demande
            $table->enum('booking_mode', ['instant', 'approval'])->default('instant')->after('status');

            // ── Limite de sièges par réservation ─────────────────────────
            // Ex : 4 → un même passager ne peut réserver que max 4 places
            $table->unsignedTinyInteger('max_per_booking')->default(4)->after('booking_mode');

            // ── Estimation de durée et d'arrivée ──────────────────────────
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable()->after('departure_time');
            $table->dateTime('estimated_arrival_time')->nullable()->after('estimated_duration_minutes');

            // ── Arrêts intermédiaires (waypoints) ─────────────────────────
            // Format :
            // [
            //   {
            //     "city": "Bohicon",
            //     "neighborhood": "Carrefour Bohicon",
            //     "arrival_offset_minutes": 90,
            //     "extra_price": 2000
            //   }
            // ]
            $table->json('waypoints')->nullable()->after('description');

            // ── Préférences conducteur ────────────────────────────────────
            // Ex : ["no_smoking", "music", "ac"]
            $table->json('preferences')->nullable()->after('waypoints');

            // ── Politique d'annulation ─────────────────────────────────────
            // flexible : remboursement complet jusqu'à 1h avant le départ
            // moderate : 50 % remboursé si annulé 24h avant
            // strict   : aucun remboursement
            $table->enum('cancellation_policy', ['flexible', 'moderate', 'strict'])
                  ->default('flexible')
                  ->after('preferences');

            // ── Trajets récurrents ─────────────────────────────────────────
            // Ex : navetteur Cotonou ↔ Bohicon tous les lundis, mercredis, vendredis
            $table->boolean('is_recurring')->default(false)->after('cancellation_policy');
            $table->json('recurring_days')->nullable()->after('is_recurring');
            // Ex : ["monday","wednesday","friday"]
            $table->date('recurring_end_date')->nullable()->after('recurring_days');

            // ── Taux de commission plateforme (% sur chaque paiement) ──────
            // Stocké par trajet pour conserver l'historique même si le taux global change
            $table->unsignedTinyInteger('commission_rate')->default(10)->after('recurring_end_date');

            // ── Visibilité / brouillon ────────────────────────────────────
            // false → brouillon non visible par les passagers
            $table->boolean('is_published')->default(true)->after('commission_rate');
            $table->timestamp('published_at')->nullable()->after('is_published');

            // ── Modération ────────────────────────────────────────────────
            $table->boolean('is_flagged')->default(false)->after('published_at');
            $table->text('moderation_note')->nullable()->after('is_flagged');

            // ── Statistiques légères ──────────────────────────────────────
            $table->unsignedInteger('view_count')->default(0)->after('moderation_note');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'departure_point', 'departure_latitude', 'departure_longitude',
                'arrival_point',   'arrival_latitude',   'arrival_longitude',
                'booking_mode', 'max_per_booking',
                'estimated_duration_minutes', 'estimated_arrival_time',
                'waypoints', 'preferences', 'cancellation_policy',
                'is_recurring', 'recurring_days', 'recurring_end_date',
                'commission_rate',
                'is_published', 'published_at',
                'is_flagged', 'moderation_note',
                'view_count',
            ]);
        });
    }
};
