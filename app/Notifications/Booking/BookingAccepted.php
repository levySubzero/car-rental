<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the renter staff approved their request, with the link to pay and the deadline.
 */
class BookingAccepted extends BookingNotification
{
    public function __construct(Booking $booking, public readonly ?string $token)
    {
        parent::__construct($booking);
    }

    public function event(): string
    {
        return 'booking_accepted';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['car', 'driver']);
        $deadline = $booking->expires_at?->setTimezone((string) config('baines.timezone'))->format('D j M Y, H:i');

        return (new MailMessage)
            ->subject("Your booking request {$booking->reference} is approved")
            ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
            ->line("Your request for the {$booking->car->name} is approved. The car is now held for you.")
            ->line("**Dates:** {$this->dates()}")
            ->line("**Total rental:** {$this->money($booking->total_minor)}")
            ->line("**Reservation to pay now:** {$this->money($booking->reservation_minor)}")
            ->when($booking->security_deposit_minor !== null, fn (MailMessage $mail) => $mail
                ->line("**Security deposit due at handover:** {$this->money($booking->security_deposit_minor)}"))
            ->when($deadline !== null, fn (MailMessage $mail) => $mail
                ->line("Please pay by **{$deadline}**, or the car is released."))
            ->when($this->token !== null, fn (MailMessage $mail) => $mail
                ->action('Pay the reservation', route('bookings.show', $this->token))
                ->line('This link replaces the one in our earlier emails.'))
            ->line('Once your payment is confirmed, we will email your voucher.');
    }
}
