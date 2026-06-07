<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (! $adminRole) {
            $this->command->error('❌ Rôle admin introuvable. Lance RoleSeeder en premier.');
            return;
        }

        // Compte admin principal
        $admin = User::firstOrCreate(
            ['phone' => '+22900000000'],
            [
                'uuid'        => (string) Str::uuid(),
                'password'    => Hash::make('minizon@229'),
                'role_id'     => $adminRole->id,
                'is_verified' => true,
            ]
        );

        // Profil admin
        Profile::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'first_name'  => 'Super',
                'last_name'   => 'ADMIN',
                'gender'      => 'M',
                'email'       => 'admin@minizon.com',
                'city'        => 'Cotonou',
                'neighborhood'=> 'Plateau',
                'kyc_status'  => 'approved',
                'approved_at' => now(),
            ]
        );

        $this->command->info('✅ Compte admin créé');
        $this->command->line('   📧 Email    : admin@minizon.com');
        $this->command->line('   🔑 Password : minizon@229');
        $this->command->line('   📱 Téléphone: +22900000000');
        $this->command->warn('   ⚠️  Changez le mot de passe en production !');
    }
}