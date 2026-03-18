<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
    }

    /** @test */
    public function it_processes_successful_payment_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 100, 'quantity' => 10, 'filled_quantity' => 0]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);

        // Act - Force successful payment
        $payment = $this->paymentService->processPayment($booking, ['amount' => 200, 'force_success' => true]);

        // Assert
        $this->assertNotNull($payment);
        $this->assertEquals(200, $payment->amount);
        $this->assertEquals('success', $payment->status);
        $this->assertEquals('confirmed', $booking->fresh()->status);
        $this->assertEquals(2, $ticket->fresh()->filled_quantity);
    }

    /** @test */
    public function it_processes_failed_payment_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 100, 'quantity' => 10, 'filled_quantity' => 0]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);

        // Act - Force failed payment
        $payment = $this->paymentService->processPayment($booking, ['amount' => 200, 'force_success' => false]);

        // Assert
        $this->assertNotNull($payment);
        $this->assertEquals('failed', $payment->status);
        $this->assertEquals('failed', $booking->fresh()->status);
        $this->assertEquals(0, $ticket->fresh()->filled_quantity);
    }

    /** @test */
    public function it_rejects_payment_with_insufficient_amount()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 100, 'quantity' => 10, 'filled_quantity' => 0]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);

        // Act
        $payment = $this->paymentService->processPayment($booking, ['amount' => 150]); // Less than 200 required

        // Assert
        $this->assertNull($payment);
        $this->assertEquals('pending', $booking->fresh()->status);
        $this->assertEquals(0, $ticket->fresh()->filled_quantity);
    }

    /** @test */
    public function it_calculates_amount_automatically_when_not_provided()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 50, 'quantity' => 10, 'filled_quantity' => 0]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 3,
            'status' => 'pending'
        ]);

        // Act - Force successful payment
        $payment = $this->paymentService->processPayment($booking, ['force_success' => true]); // No amount provided

        // Assert
        $this->assertNotNull($payment);
        $this->assertEquals(150, $payment->amount); // 3 * 50
        $this->assertEquals('success', $payment->status);
    }

    /** @test */
    public function it_handles_exceptions_during_payment_processing()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 100, 'quantity' => 10, 'filled_quantity' => 0]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);

        // Mock service to throw exception
        $service = $this->getMockBuilder(PaymentService::class)
            ->onlyMethods(['simulatePaymentGateway'])
            ->getMock();

        $service->expects($this->once())
            ->method('simulatePaymentGateway')
            ->willThrowException(new \Exception('Gateway error'));

        // Act
        $payment = $service->processPayment($booking, ['amount' => 200]);

        // Assert - Service catches exceptions and returns null
        $this->assertNull($payment);
        // Booking status should remain pending since payment failed
        $this->assertEquals('pending', $booking->fresh()->status);
    }

    /** @test */
    public function it_verifies_successful_payment()
    {
        // Arrange
        $payment = Payment::factory()->create(['status' => 'success']);

        // Act
        $isVerified = $this->paymentService->verifyPayment($payment);

        // Assert
        $this->assertTrue($isVerified);
    }

    /** @test */
    public function it_fails_verification_for_non_successful_payment()
    {
        // Arrange
        $payment = Payment::factory()->create(['status' => 'failed']);

        // Act
        $isVerified = $this->paymentService->verifyPayment($payment);

        // Assert
        $this->assertFalse($isVerified);
    }

    /** @test */
    public function it_refunds_successful_payment_correctly()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 100, 'quantity' => 10, 'filled_quantity' => 5]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'confirmed'
        ]);
        $payment = Payment::factory()->create([
            'booking_id' => $booking->id,
            'status' => 'success',
            'amount' => 200
        ]);

        // Act
        $refunded = $this->paymentService->refundPayment($payment);

        // Assert
        $this->assertTrue($refunded);
        $this->assertEquals('refunded', $payment->fresh()->status);
        $this->assertEquals('cancelled', $booking->fresh()->status);
        $this->assertEquals(3, $ticket->fresh()->filled_quantity); // 5 - 2
    }

    /** @test */
    public function it_fails_to_refund_non_successful_payment()
    {
        // Arrange
        $payment = Payment::factory()->create(['status' => 'failed']);

        // Act
        $refunded = $this->paymentService->refundPayment($payment);

        // Assert
        $this->assertFalse($refunded);
        $this->assertEquals('failed', $payment->fresh()->status);
    }

    /** @test */
    public function it_handles_refund_exceptions()
    {
        // Arrange
        $payment = Payment::factory()->create(['status' => 'success']);
        $payment->booking = null; // Simulate missing booking relationship

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->paymentService->refundPayment($payment);
    }

    /** @test */
    public function it_handles_payment_with_zero_amount()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 0, 'quantity' => 10, 'filled_quantity' => 0]); // Free tickets
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 1,
            'status' => 'pending'
        ]);

        // Act - Force successful payment
        $payment = $this->paymentService->processPayment($booking, ['amount' => 0, 'force_success' => true]);

        // Assert
        $this->assertNotNull($payment);
        $this->assertEquals(0, $payment->amount);
        $this->assertEquals('success', $payment->status);
    }

    /** @test */
    public function it_handles_payment_with_negative_amount()
    {
        // Arrange
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['price' => 100, 'quantity' => 10, 'filled_quantity' => 0]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'ticket_id' => $ticket->id,
            'quantity' => 2,
            'status' => 'pending'
        ]);

        // Act
        $payment = $this->paymentService->processPayment($booking, ['amount' => -50]);

        // Assert
        $this->assertNull($payment); // Should fail due to insufficient amount
    }
}