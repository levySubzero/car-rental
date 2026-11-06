<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The renter's acknowledgement on submit (M10.3, PRD P-7): the request is under review, with
 * the link to their booking.
 */
class BookingReceived extends BookingNotification
{
    public function __construct(Booking $booking, public readonly string $token)
    {
        parent::__construct($booking);
    }

    public function event(): string
    {
        return 'booking_received';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['car', 'driver']);

        return (new MailMessage)
            ->subject("Your booking request {$booking->reference} is under review")
            ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
            ->line("Thank you for your request for the {$booking->car->name}. Your reference is **{$booking->reference}**.")
            ->line("**Dates:** {$this->dates()}")
            ->line("**Total rental:** {$this->money($booking->total_minor)}")
            ->line("**Reservation to pay once approved:** {$this->money($booking->reservation_minor)}")
            ->line('Our team is reviewing your request. We will email you whether it is approved. There is nothing to pay yet, and the car is held for you only once it is approved.')
            ->action('Open your booking', route('bookings.show', $this->token))
            ->line('Keep this email: the link above is private to your booking.');
    }
}
