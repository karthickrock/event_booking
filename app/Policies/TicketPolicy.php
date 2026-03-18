<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine if the user can create tickets
     */
    public function create(User $user): bool
    {
          if ($user->role === 'admin') {
            return true;
        }

        // Organizer can only update tickets for their events
        if ($user->role === 'organizer') {
            return $ticket->event->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can update a ticket
     */
    public function update(User $user, Ticket $ticket): bool
    {
        // Admin can update any ticket
        if ($user->role === 'admin') {
            return true;
        }

        // Organizer can only update tickets for their events
        if ($user->role === 'organizer') {
            return $ticket->event->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can delete a ticket
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        // Admin can delete any ticket
        if ($user->role === 'admin') {
            return true;
        }

        // Organizer can only delete tickets for their events
        if ($user->role === 'organizer') {
            return $ticket->event->created_by === $user->id;
        }

        return false;
    }
}
