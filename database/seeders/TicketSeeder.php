<?php
namespace Database\Seeders;

use App\Models\Ticket;
use App\Models\Event;
use Illuminate\Database\Seeder;

class TicketSeeder extends Seeder
{
    public function run(): void
    {
        $events = Event::all();

      
        foreach ($events as $event) {
            Ticket::factory()->count(3)->create(['event_id' => $event->id]);
        }
    }
}