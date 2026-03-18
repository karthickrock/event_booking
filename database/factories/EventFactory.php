<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'date' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'location' => fake()->city(),
            'created_by' => User::factory(), // Will be overridden in seeder
        ];
    }
}