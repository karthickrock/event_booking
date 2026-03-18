<?php
namespace Database\Seeders;

use App\Models\Booking;
use App\Models\User;
use App\Models\Ticket;
use Illuminate\Database\Seeder;

class BookingSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();
        $tickets = Ticket::all();

        // Create exactly 20 Bookings
        for ($i = 0; $i < 20; $i++) {
            Booking::factory()->create([
                'user_id' => $customers->random()->id,
                'ticket_id' => $tickets->random()->id,
                'quantity' => rand(1, 3),
                'status' => 'confirmed'
            ]);
        }
    }
}
