<?php
namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->count(2)->create(['role' => 'admin']);
        User::factory()->count(3)->create(['role' => 'organizer']);
        User::factory()->count(10)->create(['role' => 'customer']);
    }

}

?>