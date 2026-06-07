<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin',     'label' => 'Administrateur'],
            ['name' => 'passenger', 'label' => 'Passager'],
            ['name' => 'driver',    'label' => 'Conducteur'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }

        $this->command->info('✅ Rôles créés : admin, passenger, driver');
    }
}