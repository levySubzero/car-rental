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
        $booking = $this->booking->loadMissing(['car', 'driver', 'root']);

        if ($booking->isExtension()) {
            return (new MailMessage)
                ->subject("Your extension request {$booking->reference} is under review")
                ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
                ->line("Thank you for asking to keep the {$booking->car->name} longer. This extends your booking **{$booking->root?->reference}**; the extension's reference is **{$booking->reference}**.")
                ->line("**Extra time:** {$this->dates()}")
                ->line("**Extension price:** {$this->money($booking->total_minor)}, paid in full once approved")
                ->line('Our team is checking the car is free for these dates. We will email you whether it is approved. There is nothing to pay yet.')
                ->action('Open your extension', route('bookings.show', $this->token))
                ->line('Keep this email: the link above is private to your booking.');
        }

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
