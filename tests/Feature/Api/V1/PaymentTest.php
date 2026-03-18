<?php

namespace Tests\Feature\Api\V1;

use App\Models\Booking;
use App\Models\Event;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function can_process_payment_successfully()
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
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $paymentData = [
            'amount' => 100 // 2 tickets * 50 = 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/bookings/{$booking->id}/payments", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'booking_id',
                        'amount',
                        'status'
                    ]
                ]);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'amount' => 100,
            'status' => 'success'
        ]);

        $this->assertEquals('confirmed', $booking->fresh()->status);
        $this->assertEquals(2, $ticket->fresh()->filled_quantity);
    }

    /** @test */
    public function payment_fails_when_insufficient_tickets_available()
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
            'filled_quantity' => 4, // Only 1 available
            'price' => 50
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'quantity' => 3, // Trying to pay for 3 tickets
            'status' => 'pending'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $paymentData = [
            'amount' => 150
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/bookings/{$booking->id}/payments", $paymentData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Not enough tickets available'
                ]);

        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id
        ]);

        $this->assertEquals('pending', $booking->fresh()->status);
    }

    /** @test */
    public function payment_fails_when_booking_already_has_payment()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'status' => 'pending'
        ]);
        Payment::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'success'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $paymentData = [
            'amount' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/bookings/{$booking->id}/payments", $paymentData);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Payment already exists for this booking'
                ]);
    }

    /** @test */
    public function payment_requires_authentication()
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $paymentData = [
            'amount' => 100
        ];

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/payments", $paymentData);

        $response->assertStatus(401);
    }

    /** @test */
    public function cannot_pay_for_other_users_booking()
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create([
            'user_id' => $customer1->id,
            'status' => 'pending'
        ]);
        $token = $customer2->createToken('test-token')->plainTextToken;

        $paymentData = [
            'amount' => 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/bookings/{$booking->id}/payments", $paymentData);

        $response->assertStatus(403);
    }

    /** @test */
    public function payment_fails_with_invalid_amount()
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
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $invalidData = [
            'amount' => 50 // Less than required 100
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->postJson("/api/v1/bookings/{$booking->id}/payments", $invalidData);

        $response->assertStatus(500) // Service returns null, controller returns 500
                ->assertJson([
                    'success' => false,
                    'message' => 'Payment processing failed'
                ]);

        $this->assertDatabaseMissing('payments', [
            'booking_id' => $booking->id
        ]);
    }

    /** @test */
    public function can_view_own_payment_details()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id]);
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 100,
            'status' => 'success'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'id',
                        'booking_id',
                        'amount',
                        'status'
                    ]
                ]);

        $this->assertEquals(100, $response->json('data.amount'));
        $this->assertEquals('success', $response->json('data.status'));
    }

    /** @test */
    public function cannot_view_other_users_payment()
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer1->id]);
        $payment = Payment::factory()->create(['booking_id' => $booking->id]);
        $token = $customer2->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_view_any_payment()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id]);
        $payment = Payment::factory()->create(['booking_id' => $booking->id]);
        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200);
    }

    /** @test */
    public function payment_handles_simulated_gateway_failure()
    {
        // This test would need to mock the payment service to simulate failure
        // Since the service uses random simulation, we can run multiple times to catch failure

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
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'quantity' => 1,
            'status' => 'pending'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        $paymentData = [
            'amount' => 50
        ];

        // Run payment multiple times until we get a failure (since it's random)
        $attempts = 0;
        $failed = false;

        do {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->postJson("/api/v1/bookings/{$booking->id}/payments", $paymentData);

            if ($response->status() === 201) {
                $payment = Payment::where('booking_id', $booking->id)->first();
                if ($payment && $payment->status === 'failed') {
                    $failed = true;
                    break;
                } else {
                    // Clean up successful payment for retry
                    $payment->delete();
                    $booking->update(['status' => 'pending']);
                    $ticket->update(['filled_quantity' => 0]);
                }
            }
            $attempts++;
        } while ($attempts < 20); // Try up to 20 times

        if ($failed) {
            $this->assertEquals('failed', $booking->fresh()->status);
            $this->assertEquals(0, $ticket->fresh()->filled_quantity);
        } else {
            $this->markTestSkipped('Could not simulate payment failure in reasonable attempts');
        }
    }

    /** @test */
    public function booking_cancellation_refunds_payment()
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
            'filled_quantity' => 5,
            'price' => 50
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'confirmed'
        ]);
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'amount' => 100,
            'status' => 'success'
        ]);
        $token = $customer->createToken('test-token')->plainTextToken;

        // Cancel booking
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token
        ])->deleteJson("/api/v1/bookings/{$booking->id}");

        $response->assertStatus(200);

        $this->assertEquals('refunded', $payment->fresh()->status);
        $this->assertEquals('refunded', $booking->fresh()->status);
        $this->assertEquals(3, $ticket->fresh()->filled_quantity); // 5 - 2
    }
}