<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;

class EventPolicy
{
    /**
     * Determine if the user can view all events
     */
    public function viewAny(?User $user): bool
    {
        // All authenticated users can view all events
        return in_array($user->role ?? null, ['admin', 'organizer', 'customer']);
    }

    /**
     * Determine if the user can view a specific event
     */
    public function view(?User $user, Event $event): bool
    {
        // Anyone can view any event
        return true;
    }

    /**
     * Determine if the user can create events
     */
    public function create(User $user): bool
    {
        // Only admin and organizer can create events
        return in_array($user->role, ['admin', 'organizer']);
    }

    /**
     * Determine if the user can update an event
     */
    public function update(User $user, Event $event): bool
    {
        // Admin can update any event
        if ($user->role === 'admin') {
            return true;
        }

        // Organizer can only update their own events
        if ($user->role === 'organizer') {
            return $event->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can delete an event
     */
    public function delete(User $user, Event $event): bool
    {
        // Admin can delete any event
        if ($user->role === 'admin') {
            return true;
        }

        // Organizer can only delete their own events
        if ($user->role === 'organizer') {
            return $event->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can restore a soft-deleted event
     */
    public function restore(User $user, Event $event): bool
    {
        return $this->delete($user, $event);
    }

    /**
     * Determine if the user can permanently delete an event
     */
    public function forceDelete(User $user, Event $event): bool
    {
        return $this->delete($user, $event);
    }
}
