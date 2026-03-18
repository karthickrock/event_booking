<?php
namespace Database\Seeders;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        // Get the organizers we just created in the UserSeeder
        $organizers = User::where('role', 'organizer')->get();

        // Create 5 Events assigned to random organizers
        Event::factory()->count(5)->make()->each(function ($event) use ($organizers) {
            $event->created_by = $organizers->random()->id;
            $event->save();
        });
    }
}
