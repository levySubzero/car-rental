<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the renter their payment could not be matched (M10.8), with staff's reason and the
 * link to pay again.
 */
class PaymentRejected extends BookingNotification
{
    public function __construct(Booking $booking, public readonly string $reason, public readonly ?string $token)
    {
        parent::__construct($booking);
    }

    public function event(): string
    {
        return 'payment_rejected';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['car', 'driver']);

        return (new MailMessage)
            ->subject("We could not confirm your payment for {$booking->reference}")
            ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
            ->line("We could not match your payment for booking **{$booking->reference}** ({$booking->car->name}).")
            ->line("**Reason:** {$this->reason}")
            ->line('The car is still held for you for a short while. Please pay again or choose another method.')
            ->when($this->token !== null, fn (MailMessage $mail) => $mail
                ->action('Pay again', route('bookings.show', $this->token))
                ->line('This link replaces the one in our earlier emails.'));
    }
}
