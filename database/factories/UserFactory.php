<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password === null? Hash::make('Test@123'):static::$password,
            'phone' => fake()->phoneNumber(),
            'role' => fake()->randomElement(['admin', 'organizer', 'customer'])
          
        ];
    }
}
