<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariff_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key')->unique();           // Clé machine (ex: base_rate_per_km)
            $table->string('name');                    // Nom affiché (ex: "Tarif de base")
            $table->text('description')->nullable();
            $table->decimal('value', 10, 2);           // Valeur numérique
            $table->string('unit', 30)->default('%');  // Unité d'affichage (FCFA/km, %, FCFA)
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Données de référence — tarifs de base pour le Bénin
        $now = now();
        $rows = [
            ['key' => 'base_rate_per_km',        'name' => 'Tarif de base',           'description' => 'Prix par kilomètre parcouru',              'value' => 50,  'unit' => 'FCFA/km'],
            ['key' => 'platform_commission',      'name' => 'Commission plateforme',   'description' => 'Pourcentage prélevé sur chaque réservation', 'value' => 10,  'unit' => '%'],
            ['key' => 'night_surcharge',          'name' => 'Majoration nuit',         'description' => 'Majoration pour trajets entre 22h et 06h',   'value' => 25,  'unit' => '%'],
            ['key' => 'weekend_surcharge',        'name' => 'Majoration week-end',     'description' => 'Majoration pour samedis et dimanches',        'value' => 15,  'unit' => '%'],
            ['key' => 'booking_fee',              'name' => 'Frais de réservation',    'description' => 'Frais fixes par réservation confirmée',        'value' => 100, 'unit' => 'FCFA'],
            ['key' => 'min_trip_price',           'name' => 'Prix minimum',            'description' => 'Prix plancher pour tout trajet',              'value' => 500, 'unit' => 'FCFA'],
        ];

        foreach ($rows as $row) {
            DB::table('tariff_rules')->insert(array_merge($row, [
                'uuid'       => (string) \Illuminate\Support\Str::uuid(),
                'active'     => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tariff_rules');
    }
};
