<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BookingConfirmed extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Booking $booking)
    {
        $this->queue = 'default';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable)
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->greeting('Booking Confirmed!')
            ->line('Your booking has been successfully confirmed.')
            ->line('Booking ID: ' . $this->booking->id)
            ->line('Event: ' . $this->booking->ticket->event->title)
            ->line('Tickets: ' . $this->booking->quantity)
            ->line('Total Amount: $' . ($this->booking->quantity * $this->booking->ticket->price))
            ->action('View Booking', url('/bookings/' . $this->booking->id))
            ->line('Thank you for your booking!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'event' => $this->booking->ticket->event->title,
            'quantity' => $this->booking->quantity,
            'amount' => $this->booking->quantity * $this->booking->ticket->price,
            'status' => 'confirmed',
        ];
    }
}
