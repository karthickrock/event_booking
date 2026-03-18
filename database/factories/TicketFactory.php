<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['VIP', 'Standard', 'Basic']),
            'price' => fake()->randomFloat(2000, 1000, 3000),
            'quantity' => fake()->numberBetween(50, 500,30),
            'event_id' => Event::factory(),
        ];
    }
}