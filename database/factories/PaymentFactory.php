<?php
namespace Database\Factories;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'amount' => fake()->randomFloat(10000, 2000, 3000),
            'status' => fake()->randomElement(['success', 'failed', 'refunded']),
        ];
    }
}