<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine if the user can view bookings
     * Admin sees all, Organizer sees their event bookings, Customer sees own bookings
     */
    public function viewAny(User $user): bool
    {
        // All roles can view bookings based on their access level
        return in_array($user->role, ['admin', 'organizer', 'customer']);
    }

    /**
     * Determine if the user can view a specific booking
     */
    public function view(User $user, Booking $booking): bool
    {
        // Admin can view any booking
        if ($user->role === 'admin') {
            return true;
        }

        // Customer can only view their own bookings
        if ($user->role === 'customer' && $booking->user_id === $user->id) {
            return true;
        }

        // Organizer can view bookings for their events
        if ($user->role === 'organizer') {
            return $booking->ticket->event->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can create a booking
     */
    public function create(User $user): bool
    {
        // Only customers and admins can create bookings
        return in_array($user->role, ['customer', 'admin']);
    }

    /**
     * Determine if the user can cancel a booking
     */
    public function cancel(User $user, Booking $booking): bool
    {
        // Admin can cancel any booking
        if ($user->role === 'admin') {
            return true;
        }

        // Customer can only cancel their own bookings
        if ($user->role === 'customer' && $booking->user_id === $user->id) {
            return true;
        }

        return false;
    }
}
