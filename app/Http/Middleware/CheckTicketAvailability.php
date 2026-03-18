<?php

namespace App\Http\Middleware;

use App\Models\Ticket;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTicketAvailability
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check for POST requests (booking creation)
        if ($request->isMethod('post')) {
            // Get ticket ID from route parameters
            $ticketId = $request->route('ticketId') ?? $request->route('ticket_id');

            if ($ticketId) {
                $ticket = Ticket::find($ticketId);

                if (!$ticket) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ticket not found'
                    ], 404);
                }

                $requestedQuantity = $request->input('quantity', 1);

                if ($ticket->filled_quantity + $requestedQuantity > $ticket->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not enough tickets available. Available: ' . ($ticket->quantity - $ticket->filled_quantity)
                    ], 400);
                }
            }
        }

        return $next($request);
    }
}
