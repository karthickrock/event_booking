<?php
namespace Database\Seeders;

use App\Models\Payment;
use App\Models\Booking;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $bookings = Booking::all();

        
        foreach ($bookings as $booking) {
            Payment::factory()->create([
                'booking_id' => $booking->id,
                'status' => 'success'
            ]);
        }
    }
}