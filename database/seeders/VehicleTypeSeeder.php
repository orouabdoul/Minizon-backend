<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name'        => 'Voiture',
                'slug'        => 'voiture',
                'description' => 'Véhicule 4 roues — berline, SUV, break',
            ],
            [
                'name'        => 'Moto',
                'slug'        => 'moto',
                'description' => 'Moto ou scooter 2 roues',
            ],
            [
                'name'        => 'Tricycle',
                'slug'        => 'tricycle',
                'description' => 'Tricycle motorisé (Keke / Zem)',
            ],
            [
                'name'        => 'Minibus',
                'slug'        => 'minibus',
                'description' => 'Minibus ou van de transport collectif',
            ],
            [
                'name'        => 'Camionnette',
                'slug'        => 'camionnette',
                'description' => 'Camionnette pour livraison de colis',
            ],
        ];

        foreach ($types as $type) {
            VehicleType::firstOrCreate(['slug' => $type['slug']], $type);
        }

        $this->command->info('✅ Types de véhicules créés : ' . implode(', ', array_column($types, 'slug')));
    }
}