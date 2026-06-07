<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid'        => (string) Str::uuid(),
            'phone'       => '+229' . $this->faker->numerify('#########'),
            'password'    => null,
            'role_id'     => 2, // passenger par défaut
            'is_verified' => false,
            'is_blocked'  => false,
        ];
    }
}
