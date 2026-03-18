<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        //   $this->call([
        //     UserSeeder::class,
        //     EventSeeder::class,
        //     TicketSeeder::class,
        //     BookingSeeder::class,
        //     PaymentSeeder::class,
        // ]);

        //Manual Seeders for the purpose doocumentation


        for ($i = 1; $i <= 2; $i++) {
            User::create([
                'name' => "Admin User $i",
                'email' => "admin$i@test.com",
                'password' => Hash::make('Test@123'),
                'role' => 'admin',
                'phone' => "1234567890$i"
            ]);
        }

        // 3 Organizers
        for ($i = 1; $i <= 3; $i++) {
            User::create([
                'name' => "Event Organizer $i",
                'email' => "organizer$i@test.com",
                'password' => Hash::make('Test@123'),
                'role' => 'organizer',
                'phone' => "2345678901$i"
            ]);
        }

        // 10 Customers
        for ($i = 1; $i <= 10; $i++) {
            User::create([
                'name' => "Customer User $i",
                'email' => "customer$i@test.com",
                'password' => Hash::make('Test@123'),
                'role' => 'customer',
                'phone' => "3456789012$i"
            ]);
        }
     
        $organizers = User::where('role', 'organizer')->get();
       
        for ($i = 1; $i <= 5; $i++) {
            Event::create([
                'created_by' => $organizers->random()->id,
                'title' => "Music Festival $i",
                'description' => "This is a detailed description for manual event $i.",
                'date' => now()->addDays($i * 10),
                'location' => "City Arena $i"
            ]);
        }

        $events = Event::all();

        //3 tickets per event (Total: 5 events * 3 tickets = 15 tickets)
        foreach ($events as $event) {
            Ticket::create([
                'event_id' => $event->id,
                'type' => 'VIP',
                'price' => 3000.00,
                'quantity' => 50
            ]);

            Ticket::create([
                'event_id' => $event->id,
                'type' => 'Standard',
                'price' => 2000.00,
                'quantity' => 200
            ]);

            Ticket::create([
                'event_id' => $event->id,
                'type' => 'Basic',
                'price' => 1000.00,
                'quantity' => 100
            ]);
        }

        $customers = User::where('role', 'customer')->get();
        $tickets = Ticket::all();

        // 20 Manual Bookings
        for ($i = 1; $i <= 20; $i++) {
           $booking= Booking::create([
                'user_id' => $customers->random()->id,
                'ticket_id' => $tickets->random()->id,
                'quantity' => rand(1, 4), // Random amount between 1 and 4 tickets per booking
                'status' => 'confirmed'
            ]);
            $totalAmount = $booking->quantity * $booking->ticket->price;

            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $totalAmount,
                'status' => 'success'
            ]);
        }




    }

   

       
   
}
