<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\InteractsWithNotifications;

class BookingTest extends TestCase
{
    use RefreshDatabase, InteractsWithNotifications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpNotifications();
    }

    /** @test */
    public function customer_can_create_booking_successfully()
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'customer']);
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'date' => now()->addDays(30)
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'quantity' => 100,
            'filled_quantity' => 0,
            'price' => 50
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $bookingData = [
            'quantity' => 2
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/tickets/{$ticket->id}/bookings", $bookingData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'user_id',
                        'ticket_id',
                        'quantity',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);

        Notification::assertSentTo($customer, \App\Notifications\BookingConfirmed::class);
    }

    /** @test */
    public function booking_fails_when_insufficient_tickets_available()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'date' => now()->addDays(30)
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'quantity' => 5,
            'filled_quantity' => 4, // Only 1 left
            'price' => 50
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $bookingData = [
            'quantity' => 3 // Trying to book 3, but only 1 available
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/tickets/{$ticket->id}/bookings", $bookingData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Not enough tickets available'
                ]);

        $this->assertDatabaseMissing('bookings', [
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id
        ]);
    }

    /** @test */
    public function booking_fails_for_past_events()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'date' => now()->subDays(1) // Past event
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'quantity' => 100,
            'filled_quantity' => 0,
            'price' => 50
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $bookingData = [
            'quantity' => 1
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/tickets/{$ticket->id}/bookings", $bookingData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Cannot book tickets for past events'
                ]);
    }

    /** @test */
    public function booking_requires_authentication()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'date' => now()->addDays(30)
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'quantity' => 100,
            'filled_quantity' => 0,
            'price' => 50
        ]);

        $bookingData = [
            'quantity' => 1
        ];

        $response = $this->postJson("/api/v1/tickets/{$ticket->id}/bookings", $bookingData);

        $response->assertStatus(401);
    }

    /** @test */
    public function booking_fails_with_invalid_quantity()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'date' => now()->addDays(30)
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'quantity' => 100,
            'filled_quantity' => 0,
            'price' => 50
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $invalidData = [
            'quantity' => 0 // Invalid quantity
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/tickets/{$ticket->id}/bookings", $bookingData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['quantity']);
    }

    /** @test */
    public function organizer_cannot_book_own_event_tickets()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'date' => now()->addDays(30)
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'quantity' => 100,
            'filled_quantity' => 0,
            'price' => 50
        ]);
        $token = $organizer->createToken('test-token')->plainTextToken;

        $bookingData = [
            'quantity' => 1
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/tickets/{$ticket->id}/bookings", $bookingData);

        $response->assertStatus(403);
    }

    /** @test */
    public function can_cancel_own_booking()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'date' => now()->addDays(30)
        ]);
        $ticket = Ticket::factory()->create([
            'event_id' => $event->id,
            'quantity' => 100,
            'filled_quantity' => 10,
            'price' => 50
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'confirmed'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/bookings/{$booking->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Booking cancelled successfully'
                ]);

        $this->assertEquals('cancelled', $booking->fresh()->status);
        $this->assertEquals(8, $ticket->fresh()->filled_quantity); // 10 - 2
    }

    /** @test */
    public function cannot_cancel_already_cancelled_booking()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'status' => 'cancelled'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/bookings/{$booking->id}");

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Booking cannot be cancelled'
                ]);
    }

    /** @test */
    public function admin_can_cancel_any_booking()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'status' => 'confirmed'
        ]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/bookings/{$booking->id}");

        $response->assertStatus(200);
        $this->assertEquals('cancelled', $booking->fresh()->status);
    }

    /** @test */
    public function cannot_cancel_other_users_booking()
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create([
            'user_id' => $customer1->id,
            'status' => 'confirmed'
        ]);
        $token = $customer2->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/bookings/{$booking->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function can_list_own_bookings()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        Booking::factory()->count(3)->create(['user_id' => $customer->id]);
        Booking::factory()->count(2)->create(); // Other users' bookings
        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function organizer_can_view_bookings_for_own_events()
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);
        Booking::factory()->count(2)->create(['ticket_id' => $ticket->id]);
        Booking::factory()->count(1)->create(); // Other event booking
        $token = $organizer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function admin_can_view_all_bookings()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Booking::factory()->count(5)->create();
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson('/api/v1/bookings');

        $response->assertStatus(200);
        $this->assertCount(5, $response->json('data'));
    }
}