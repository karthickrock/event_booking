<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketRequest;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Create ticket for event (organizer and admin only)
     * 
     * @param TicketRequest $request
     * @param string $eventId
     * @return JsonResponse
     */
    public function store(TicketRequest $request, string $eventId): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $event = Event::findOrFail($eventId);

            // Use policy to check authorization
            $this->authorize('create', Ticket::class);

         

            $ticket = Ticket::create([
                'type' => $request->type,
                'price' => $request->price,
                'quantity' => $request->quantity,
                'event_id' => $eventId,
                'filled_quantity' => 0,
            ]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket created successfully',
                'data' => $ticket
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ticket (organizer can update own, admin can update any)
     * 
     * @param TicketRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(TicketRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $ticket = Ticket::findOrFail($id);

            // Use policy to check authorization
            $this->authorize('update', $ticket);

            $ticket->update([
                'type' => $request->type ?? $ticket->type,
                'price' => $request->price ?? $ticket->price,
                'quantity' => $request->quantity ?? $ticket->quantity,
            ]);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket updated successfully',
                'data' => $ticket
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete ticket (organizer can delete own, admin can delete any)
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $ticket = Ticket::findOrFail($id);

            // Use policy to check authorization
            $this->authorize('delete', $ticket);

            $ticket->delete();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Ticket deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket: ' . $e->getMessage()
            ], 500);
        }
    }
}
