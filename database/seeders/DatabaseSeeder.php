<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Démarrage du seeding Minizon...');
        $this->command->newLine();

        $this->call([
            RoleSeeder::class,
            VehicleTypeSeeder::class,
            AdminSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('🎉 Seeding terminé avec succès !');
    }
}
