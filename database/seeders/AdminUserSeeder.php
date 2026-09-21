<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', 'admin')->first();
        if (! $role) {
            $this->command->warn('AdminUserSeeder: rôle admin introuvable, skipped.');
            return;
        }

        $user = User::firstOrCreate(
            ['phone' => env('ADMIN_USER_PHONE', '+22900000000')],
            [
                'role_id'              => $role->id,
                'password'             => Hash::make(env('ADMIN_USER_PASSWORD', 'minizon@229')),
                'is_verified'          => true,
                'phone_verified_at'    => now(),
                'is_profile_complete'  => true,
                'is_blocked'           => false,
            ]
        );

        // Update role if user already existed without it
        if (! $user->role_id) {
            $user->update(['role_id' => $role->id]);
        }

        Profile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => 'Admin',
                'last_name'  => 'Minizon',
            ]
        );

        $this->command->info('✅ Utilisateur admin (users table) prêt : ' . $user->phone);
    }
}
