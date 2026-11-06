<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the renter a request was declined or lapsed, or a paid booking was cancelled, and why.
 * A request that never reached payment is invited to pick another car of the same type.
 */
class BookingCancelled extends BookingNotification
{
    public function __construct(Booking $booking, public readonly string $reason)
    {
        parent::__construct($booking);
    }

    public function event(): string
    {
        return 'booking_cancelled';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['car.carType', 'driver']);
        $paid = $booking->paid_at !== null;

        return (new MailMessage)
            ->subject($paid ? "Your booking {$booking->reference} is cancelled" : "We could not go ahead with your request {$booking->reference}")
            ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
            ->line($paid
                ? "Your booking **{$booking->reference}** for the {$booking->car->name} is cancelled."
                : "We could not go ahead with your request **{$booking->reference}** for the {$booking->car->name}.")
            ->line("**Reason:** {$this->reason}")
            ->when((int) $booking->refund_minor > 0, fn (MailMessage $mail) => $mail
                ->line("**Refund:** {$this->money($booking->refund_minor)}, which we will send back to you."))
            ->when(! $paid, fn (MailMessage $mail) => $mail
                ->line('You have not been charged. You are welcome to choose another car for your dates.')
                ->action('View other cars', route('fleet', array_filter(['type' => $booking->car->carType?->slug]))));
    }
}
