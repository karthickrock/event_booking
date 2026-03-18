<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;

class PaymentService
{
    /**
     * Process payment with simulated success/failure
     * 
     * @param Booking $booking
     * @param array $paymentData
     * @return Payment|null
     */
    public function processPayment(Booking $booking, array $paymentData): ?Payment
    {
        // Calculate amount from booking
        $amount = $paymentData['amount'] ?? ($booking->quantity * $booking->ticket->price);

        if($amount < $booking->quantity * $booking->ticket->price){
            return null; // Invalid amount
        }
        // Simulate payment processing (mock success/failure based on random)
        $isSuccessful = $this->simulatePaymentGateway($paymentData);

        // Create payment record
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'status' => $isSuccessful ? 'success' : 'failed',
        ]);

        // Update booking status if payment successful
        if ($isSuccessful) {
            $booking->update(['status' => 'confirmed']);
            
            // Update ticket filled quantity
            $ticket = $booking->ticket;
            $ticket->increment('filled_quantity', $booking->quantity);
        } else {
            $booking->update(['status' => 'failed']);
        }

        return $payment;
    }

    /**
     * Simulate payment gateway response
     * In production, integrate with real payment gateway (Stripe, PayPal, etc.)
     * 
     * @param array $paymentData
     * @return bool
     */
    public function simulatePaymentGateway(array $paymentData): bool
    {
        // Check if forced result is provided for testing
        if (isset($paymentData['force_success'])) {
            return $paymentData['force_success'];
        }

        // Simulate 90% success rate for demo purposes
        return rand(1, 100) <= 90;
    }

    /**
     * Verify payment status
     * 
     * @param Payment $payment
     * @return bool
     */
    public function verifyPayment(Payment $payment): bool
    {
        return $payment->status === 'success';
    }

    /**
     * Refund payment
     * 
     * @param Payment $payment
     * @return bool
     */
    public function refundPayment(Payment $payment): bool
    {
        if ($payment->status !== 'success') {
            return false;
        }

        $payment->update(['status' => 'refunded']);
        
        // Update booking and ticket
        $booking = $payment->booking;
        $booking->update(['status' => 'cancelled']);
        
        $ticket = $booking->ticket;
        $ticket->decrement('filled_quantity', $booking->quantity);

        return true;
    }
}
