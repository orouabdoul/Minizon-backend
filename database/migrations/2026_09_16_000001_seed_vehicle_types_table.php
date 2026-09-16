<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $types = [
            ['name' => 'Voiture',     'slug' => 'voiture',     'description' => 'Véhicule 4 roues — berline, SUV, break'],
            ['name' => 'Moto',        'slug' => 'moto',        'description' => 'Moto ou scooter 2 roues'],
            ['name' => 'Tricycle',    'slug' => 'tricycle',    'description' => 'Tricycle motorisé (Keke / Zem)'],
            ['name' => 'Minibus',     'slug' => 'minibus',     'description' => 'Minibus ou van de transport collectif'],
            ['name' => 'Camionnette', 'slug' => 'camionnette', 'description' => 'Camionnette pour livraison de colis'],
        ];

        foreach ($types as $type) {
            DB::table('vehicle_types')->updateOrInsert(
                ['slug' => $type['slug']],
                array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('vehicle_types')
            ->whereIn('slug', ['voiture', 'moto', 'tricycle', 'minibus', 'camionnette'])
            ->delete();
    }
};
