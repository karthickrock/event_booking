<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    private PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Process payment for booking (mock payment)
     * 
     * @param Request $request
     * @param string $bookingId
     * @return JsonResponse
     */
    public function store(Request $request, string $bookingId): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $booking = Booking::findOrFail($bookingId);
            $this->authorize('create', Payment::class);

            // Check if booking already has a payment
            if ($booking->payment()->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already exists for this booking'
                ], 400);
            }

            // Check ticket availability before payment processing
            $ticket = $booking->ticket;
            if ($ticket->filled_quantity + $booking->quantity > $ticket->quantity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough tickets available'
                ], 400);
            }

            // Process payment
            $paymentData = $request->only('amount');
            $payment = $this->paymentService->processPayment($booking, $paymentData);

            if (!$payment) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment processing failed'
                ], 500);
            }
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully',
                'data' => $payment->load('booking')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment details
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $payment = Payment::findOrFail($id);
            $this->authorize('view', $payment);

            return response()->json([
                'success' => true,
                'data' => $payment->load('booking')
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found: ' . $e->getMessage()
            ], 404);
        }
    }
}
