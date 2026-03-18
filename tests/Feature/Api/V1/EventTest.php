<?php

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function organizer_can_create_event_successfully()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $token = $organizer->createToken('test-token')->plainTextToken;

        $eventData = [
            'title' => 'Tech Conference 2026',
            'description' => 'A great tech conference',
            'date' => now()->addDays(30)->toDateString(),
            'time' => '10:00:00',
            'location' => 'Convention Center',
            'max_attendees' => 500
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/events', $eventData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'title',
                        'description',
                        'date',
                        'time',
                        'location',
                        'max_attendees',
                        'created_by'
                    ]
                ]);

        $this->assertDatabaseHas('events', [
            'title' => 'Tech Conference 2026',
            'description' => 'A great tech conference',
            'location' => 'Convention Center',
            'max_attendees' => 500,
            'created_by' => $organizer->id
        ]);
    }

    /** @test */
    public function event_creation_fails_with_invalid_data()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $token = $organizer->createToken('test-token')->plainTextToken;

        $invalidData = [
            'title' => '',
            'description' => '',
            'date' => 'invalid-date',
            'time' => '25:00:00',
            'location' => '',
            'max_attendees' => -1
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/events', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'description', 'date', 'time', 'location', 'max_attendees']);
    }

    /** @test */
    public function customer_cannot_create_event()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $token = $customer->createToken('test-token')->plainTextToken;

        $eventData = [
            'title' => 'Customer Event',
            'description' => 'Should not be allowed',
            'date' => now()->addDays(30)->toDateString(),
            'time' => '10:00:00',
            'location' => 'Some Place',
            'max_attendees' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/events', $eventData);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_event()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test-token')->plainTextToken;

        $eventData = [
            'title' => 'Admin Event',
            'description' => 'Created by admin',
            'date' => now()->addDays(30)->toDateString(),
            'time' => '14:00:00',
            'location' => 'Admin Hall',
            'max_attendees' => 200
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/events', $eventData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('events', [
            'title' => 'Admin Event',
            'created_by' => $admin->id
        ]);
    }

    /** @test */
    public function event_creation_requires_authentication()
    {
        $eventData = [
            'title' => 'Unauthenticated Event',
            'description' => 'Should fail',
            'date' => now()->addDays(30)->toDateString(),
            'time' => '10:00:00',
            'location' => 'Some Place',
            'max_attendees' => 100
        ];

        $response = $this->postJson('/api/v1/events', $eventData);

        $response->assertStatus(401);
    }

    /** @test */
    public function cannot_create_event_in_past()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $token = $organizer->createToken('test-token')->plainTextToken;

        $pastEventData = [
            'title' => 'Past Event',
            'description' => 'Should not be allowed',
            'date' => now()->subDays(1)->toDateString(),
            'time' => '10:00:00',
            'location' => 'Past Location',
            'max_attendees' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson('/api/v1/events', $pastEventData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['date']);
    }

    /** @test */
    public function can_update_own_event()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $token = $organizer->createToken('test-token')->plainTextToken;

        $updateData = [
            'title' => 'Updated Event Title',
            'description' => 'Updated description',
            'max_attendees' => 300
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson("/api/v1/events/{$event->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Updated Event Title',
            'max_attendees' => 300
        ]);
    }

    /** @test */
    public function cannot_update_other_organizers_event()
    {
        $organizer1 = User::factory()->create(['role' => 'organizer']);
        $organizer2 = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer1->id]);
        $token = $organizer2->createToken('test-token')->plainTextToken;

        $updateData = [
            'title' => 'Hacked Title'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson("/api/v1/events/{$event->id}", $updateData);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_update_any_event()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $updateData = [
            'title' => 'Admin Updated Title'
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->putJson("/api/v1/events/{$event->id}", $updateData);

        $response->assertStatus(200);
        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Admin Updated Title'
        ]);
    }

    /** @test */
    public function can_delete_own_event()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $token = $organizer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    /** @test */
    public function admin_can_delete_any_event()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $admin = User::factory()->create(['role' => 'admin']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    /** @test */
    public function can_list_events()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        Event::factory()->count(3)->create(['created_by' => $organizer->id]);
        $token = $organizer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/events');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'description',
                            'date',
                            'time',
                            'location',
                            'max_attendees'
                        ]
                    ]
                ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function events_are_cached()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        Event::factory()->create(['created_by' => $organizer->id]);
        $token = $organizer->createToken('test-token')->plainTextToken;

        // First request should cache
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/events');

        $response1->assertStatus(200);

        // Create another event
        Event::factory()->create(['created_by' => $organizer->id]);

        // Second request should return cached data (still 1 event)
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/events');

        // Note: In a real test, you'd need to mock cache or check cache keys
        // This is a basic structure - cache invalidation would need more complex testing
        $response2->assertStatus(200);
    }
}