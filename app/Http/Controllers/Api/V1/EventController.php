<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventRequest;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class EventController extends Controller
{
    /**
     * Get all events with pagination, search, and filters
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Event::class);
            
            // Generate cache key based on search/filter parameters
            $cacheKey = 'events_list_' . md5(json_encode($request->all()));
            
            // Cache events for 60 minutes (3600 seconds)
            $events = Cache::remember($cacheKey, 3600, function () use ($request) {
                $query = Event::query();

                // Search by title or description
                if ($request->search) {
                    $query->searchByTitle($request->search);
                }

                // Filter by date range
                if ($request->start_date && $request->end_date) {
                    $query->filterByDate($request->start_date, $request->end_date);
                }

                // Filter by location
                if ($request->location) {
                    $query->filterByLocation($request->location);
                }

                return $query->with('tickets', 'user')
                    ->paginate($request->per_page ?? 15);
            });
            
            // Track cache key for future invalidation
            $cacheKeys = Cache::get('event_cache_keys', []);
            if (!in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put('event_cache_keys', $cacheKeys, 86400);
            }

            return response()->json([
                'success' => true,
                'data' => $events
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch events: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single event with tickets
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $event = Event::with('tickets', 'user')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $event
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Create new event (organizer and admin only)
     * 
     * @param EventRequest $request
     * @return JsonResponse
     */
    public function store(EventRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            // Use policy to check authorization
            $this->authorize('create', Event::class);

            $event = Event::create([
                'title' => $request->title,
                'description' => $request->description,
                'date' => $request->date,
                'location' => $request->location,
                'created_by' => $request->user()->id,
            ]);
            
            // Clear all event list caches
            $this->clearEventCache();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Event created successfully',
                'data' => $event
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear event cache
     */
    private function clearEventCache()
    {
        // Get all cached event keys and clear them
        if (Cache::has('event_cache_keys')) {
            $keys = Cache::get('event_cache_keys', []);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
            Cache::forget('event_cache_keys');
        }
    }

    /**
     * Update event (organizer can update own, admin can update any)
     * 
     * @param EventRequest $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(EventRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $event = Event::findOrFail($id);

            // Use policy to check authorization
            $this->authorize('update', $event);

            $event->update([
                'title' => $request->title ?? $event->title,
                'description' => $request->description ?? $event->description,
                'date' => $request->date ?? $event->date,
                'location' => $request->location ?? $event->location,
            ]);
            
            // Clear all event list caches
            $this->clearEventCache();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Event updated successfully',
                'data' => $event
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete event (organizer can delete own, admin can delete any)
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            $event = Event::findOrFail($id);

            // Use policy to check authorization
            $this->authorize('delete', $event);

            $event->delete();
            
            // Clear all event list caches
            $this->clearEventCache();
            
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event: ' . $e->getMessage()
            ], 500);
        }
    }
}
