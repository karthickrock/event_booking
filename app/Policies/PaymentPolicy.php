<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    /**
     * Determine if the user can view all payments
     * Admin sees all, Organizer sees their event payments, Customer sees their payments
     */
    public function viewAny(User $user): bool
    {
        // All roles can view payments based on their access level
        return in_array($user->role, ['admin', 'organizer', 'customer']);
    }

    /**
     * Determine if the user can view a specific payment
     */
    public function view(User $user, Payment $payment): bool
    {
        // Admin can view any payment
        if ($user->role === 'admin') {
            return true;
        }

        // Customer can only view their own payments
        if ($user->role === 'customer' && $payment->booking->user_id === $user->id) {
            return true;
        }

        // Organizer can view payments for their event bookings
        if ($user->role === 'organizer') {
            return $payment->booking->ticket->event->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can create a payment
     */
    public function create(User $user): bool
    {
        // Only customers and admins can create payments
        return in_array($user->role, ['customer', 'admin']);
    }
}
