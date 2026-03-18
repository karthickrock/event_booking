<?php
namespace Database\Factories;

use App\Models\User;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ticket_id' => Ticket::factory(),
            'quantity' => fake()->numberBetween(1, 10),
            'status' => fake()->randomElement(['pending', 'confirmed', 'cancelled']),
        ];
    }
}