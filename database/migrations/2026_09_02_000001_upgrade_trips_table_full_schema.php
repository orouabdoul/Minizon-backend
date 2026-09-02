<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mise à niveau du schéma trips depuis la version initiale minimaliste
 * vers le schéma complet (géographie enrichie, GPS, capacité, préférences,
 * récurrence, financier, cycle de vie, télémétrie, modération).
 *
 * Idempotent : chaque colonne est ajoutée seulement si elle n'existe pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {

            // ── Géographie départ — ajouts ────────────────────────────────────
            if (! Schema::hasColumn('trips', 'departure_arrondissement')) {
                $table->string('departure_arrondissement', 150)->nullable()->after('departure_city');
            }
            // departure_neighborhood existe déjà mais était NOT NULL — le rendre nullable
            if (Schema::hasColumn('trips', 'departure_neighborhood')) {
                $table->string('departure_neighborhood', 100)->nullable()->change();
            }
            if (! Schema::hasColumn('trips', 'departure_point')) {
                $table->string('departure_point', 200)->nullable()->after('departure_neighborhood');
            }
            if (! Schema::hasColumn('trips', 'departure_latitude')) {
                $table->decimal('departure_latitude', 10, 7)->nullable()->after('departure_point');
            }
            if (! Schema::hasColumn('trips', 'departure_longitude')) {
                $table->decimal('departure_longitude', 10, 7)->nullable()->after('departure_latitude');
            }

            // ── Géographie arrivée — ajouts ────────────────────────────────────
            if (! Schema::hasColumn('trips', 'arrival_arrondissement')) {
                $table->string('arrival_arrondissement', 150)->nullable()->after('arrival_city');
            }
            // arrival_neighborhood existe déjà mais était NOT NULL — le rendre nullable
            if (Schema::hasColumn('trips', 'arrival_neighborhood')) {
                $table->string('arrival_neighborhood', 100)->nullable()->change();
            }
            if (! Schema::hasColumn('trips', 'arrival_point')) {
                $table->string('arrival_point', 200)->nullable()->after('arrival_neighborhood');
            }
            if (! Schema::hasColumn('trips', 'arrival_latitude')) {
                $table->decimal('arrival_latitude', 10, 7)->nullable()->after('arrival_point');
            }
            if (! Schema::hasColumn('trips', 'arrival_longitude')) {
                $table->decimal('arrival_longitude', 10, 7)->nullable()->after('arrival_latitude');
            }

            // ── Route calculée ────────────────────────────────────────────────
            if (! Schema::hasColumn('trips', 'distance_km')) {
                $table->decimal('distance_km', 8, 2)->nullable()->after('arrival_longitude');
            }
            if (! Schema::hasColumn('trips', 'estimated_duration_minutes')) {
                $table->unsignedInteger('estimated_duration_minutes')->nullable()->after('distance_km');
            }
            if (! Schema::hasColumn('trips', 'estimated_arrival_time')) {
                $table->dateTime('estimated_arrival_time')->nullable()->after('estimated_duration_minutes');
            }

            // ── Capacité & mode de réservation ───────────────────────────────
            if (! Schema::hasColumn('trips', 'total_seats')) {
                $table->unsignedTinyInteger('total_seats')->default(1)->after('estimated_arrival_time');
            }
            if (! Schema::hasColumn('trips', 'available_seats')) {
                $table->unsignedTinyInteger('available_seats')->default(1)->after('total_seats');
            }
            if (! Schema::hasColumn('trips', 'booking_mode')) {
                $table->enum('booking_mode', ['instant', 'approval'])->default('instant')->after('available_seats');
            }
            if (! Schema::hasColumn('trips', 'max_per_booking')) {
                $table->unsignedTinyInteger('max_per_booking')->nullable()->after('booking_mode');
            }

            // ── Préférences & arrêts ─────────────────────────────────────────
            if (! Schema::hasColumn('trips', 'waypoints')) {
                $table->json('waypoints')->nullable()->after('max_per_booking');
            }
            if (! Schema::hasColumn('trips', 'preferences')) {
                $table->json('preferences')->nullable()->after('waypoints');
            }
            if (! Schema::hasColumn('trips', 'cancellation_policy')) {
                $table->enum('cancellation_policy', ['flexible', 'moderate', 'strict'])->default('flexible')->after('preferences');
            }

            // ── Récurrence ───────────────────────────────────────────────────
            if (! Schema::hasColumn('trips', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false)->after('cancellation_policy');
            }
            if (! Schema::hasColumn('trips', 'recurring_days')) {
                $table->json('recurring_days')->nullable()->after('is_recurring');
            }
            if (! Schema::hasColumn('trips', 'recurring_end_date')) {
                $table->date('recurring_end_date')->nullable()->after('recurring_days');
            }

            // ── Financier ────────────────────────────────────────────────────
            if (! Schema::hasColumn('trips', 'commission_rate')) {
                $table->unsignedTinyInteger('commission_rate')->default(10)->after('recurring_end_date');
            }

            // ── Visibilité ───────────────────────────────────────────────────
            if (! Schema::hasColumn('trips', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('commission_rate');
            }
            if (! Schema::hasColumn('trips', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('is_published');
            }

            // ── Timestamps du cycle de vie ───────────────────────────────────
            if (! Schema::hasColumn('trips', 'started_at')) {
                $table->timestamp('started_at')->nullable()->after('published_at');
            }
            if (! Schema::hasColumn('trips', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }

            // ── Télémétrie GPS (current_speed + location_updated_at) ─────────
            // current_latitude et current_longitude existent déjà
            if (! Schema::hasColumn('trips', 'current_speed')) {
                $table->decimal('current_speed', 5, 2)->nullable()->after('current_longitude');
            }
            if (! Schema::hasColumn('trips', 'location_updated_at')) {
                $table->timestamp('location_updated_at')->nullable()->after('current_speed');
            }

            // ── Modération ───────────────────────────────────────────────────
            if (! Schema::hasColumn('trips', 'is_flagged')) {
                $table->boolean('is_flagged')->default(false)->after('location_updated_at');
            }
            if (! Schema::hasColumn('trips', 'moderation_note')) {
                $table->text('moderation_note')->nullable()->after('is_flagged');
            }

            // ── Statistiques ─────────────────────────────────────────────────
            if (! Schema::hasColumn('trips', 'view_count')) {
                $table->unsignedInteger('view_count')->default(0)->after('moderation_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('trips', 'departure_arrondissement') ? 'departure_arrondissement' : null,
                Schema::hasColumn('trips', 'departure_point')          ? 'departure_point'          : null,
                Schema::hasColumn('trips', 'departure_latitude')       ? 'departure_latitude'       : null,
                Schema::hasColumn('trips', 'departure_longitude')      ? 'departure_longitude'      : null,
                Schema::hasColumn('trips', 'arrival_arrondissement')   ? 'arrival_arrondissement'   : null,
                Schema::hasColumn('trips', 'arrival_point')            ? 'arrival_point'            : null,
                Schema::hasColumn('trips', 'arrival_latitude')         ? 'arrival_latitude'         : null,
                Schema::hasColumn('trips', 'arrival_longitude')        ? 'arrival_longitude'        : null,
                Schema::hasColumn('trips', 'distance_km')              ? 'distance_km'              : null,
                Schema::hasColumn('trips', 'estimated_duration_minutes') ? 'estimated_duration_minutes' : null,
                Schema::hasColumn('trips', 'estimated_arrival_time')   ? 'estimated_arrival_time'   : null,
                Schema::hasColumn('trips', 'total_seats')              ? 'total_seats'              : null,
                Schema::hasColumn('trips', 'available_seats')          ? 'available_seats'          : null,
                Schema::hasColumn('trips', 'booking_mode')             ? 'booking_mode'             : null,
                Schema::hasColumn('trips', 'max_per_booking')          ? 'max_per_booking'          : null,
                Schema::hasColumn('trips', 'waypoints')                ? 'waypoints'                : null,
                Schema::hasColumn('trips', 'preferences')              ? 'preferences'              : null,
                Schema::hasColumn('trips', 'cancellation_policy')      ? 'cancellation_policy'      : null,
                Schema::hasColumn('trips', 'is_recurring')             ? 'is_recurring'             : null,
                Schema::hasColumn('trips', 'recurring_days')           ? 'recurring_days'           : null,
                Schema::hasColumn('trips', 'recurring_end_date')       ? 'recurring_end_date'       : null,
                Schema::hasColumn('trips', 'commission_rate')          ? 'commission_rate'          : null,
                Schema::hasColumn('trips', 'is_published')             ? 'is_published'             : null,
                Schema::hasColumn('trips', 'published_at')             ? 'published_at'             : null,
                Schema::hasColumn('trips', 'started_at')               ? 'started_at'               : null,
                Schema::hasColumn('trips', 'completed_at')             ? 'completed_at'             : null,
                Schema::hasColumn('trips', 'current_speed')            ? 'current_speed'            : null,
                Schema::hasColumn('trips', 'location_updated_at')      ? 'location_updated_at'      : null,
                Schema::hasColumn('trips', 'is_flagged')               ? 'is_flagged'               : null,
                Schema::hasColumn('trips', 'moderation_note')          ? 'moderation_note'          : null,
                Schema::hasColumn('trips', 'view_count')               ? 'view_count'               : null,
            ]));
        });
    }
};
