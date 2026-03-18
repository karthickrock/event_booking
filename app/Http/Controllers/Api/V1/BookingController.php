<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingRequest;
use App\Jobs\SendBookingConfirmationNotification;
use App\Models\Booking;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Create booking for ticket (customer and admin only)
     * 
     * @param BookingRequest $request
     * @param string $ticketId
     * @return JsonResponse
     */
    public function store(BookingRequest $request, string $ticketId): JsonResponse
    {
        try {
            DB::beginTransaction();

            $ticket = Ticket::findOrFail($ticketId);

            // Use policy to check authorization
            $this->authorize('create', Booking::class);

            // Check if event date has not passed
            if ($ticket->event->date < now()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot book tickets for past events'
                ], 400);
            }

            $booking = Booking::create([
                'user_id' => $request->user()->id,
                'ticket_id' => $ticketId,
                'quantity' => $request->quantity,
                'status' => 'pending',
            ]);
            
            // Dispatch booking confirmation notification to queue
            SendBookingConfirmationNotification::dispatch($booking);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking created successfully',
                'data' => $booking->load('ticket', 'user')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create booking: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bookings (admin sees all, others see their own)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Booking::class);
            
            $user = $request->user();

            // Admin can see all bookings
            if ($user->role === 'admin') {
                $bookings = Booking::with('ticket', 'payment', 'user')
                    ->paginate($request->per_page ?? 15);
            } 
            // Organizer can see bookings for their events
            else if ($user->role === 'organizer') {
                $bookings = Booking::whereHas('ticket.event', function ($query) use ($user) {
                    $query->where('created_by', $user->id);
                })
                    ->with('ticket', 'payment', 'user')
                    ->paginate($request->per_page ?? 15);
            } 
            // Customer only sees their own bookings
            else {
                $bookings = Booking::where('user_id', $user->id)
                    ->with('ticket', 'payment')
                    ->paginate($request->per_page ?? 15);
            }

            return response()->json([
                'success' => true,
                'data' => $bookings
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bookings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel booking (customer can cancel own, admin can cancel any)
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $booking = Booking::findOrFail($id);

            // Use policy to check authorization
            $this->authorize('cancel', $booking);

            // Check if booking can be cancelled
            if (in_array($booking->status, ['cancelled', 'refunded'])) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Booking cannot be cancelled'
                ], 400);
            }

            $booking->update(['status' => 'cancelled']);

            // Handle payment refund if payment was processed
            if ($booking->payment && $booking->payment->status === 'success') {
                $booking->payment->update(['status' => 'refunded']);
                $booking->update(['status' => 'refunded']);
            }

            // Refund the ticket quantity
            $ticket = $booking->ticket;
            $ticket->decrement('filled_quantity', $booking->quantity);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => $booking->load('payment')
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel booking: ' . $e->getMessage()
            ], 500);
        }
    }
}
