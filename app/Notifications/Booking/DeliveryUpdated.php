<?php

namespace App\Notifications\Booking;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the renter staff have set or corrected the delivery fee (M11.13, D33). The reservation
 * already paid stays; only the balance due at handover changes. It carries no link, so the
 * renter's current link keeps working (D39).
 */
class DeliveryUpdated extends BookingNotification
{
    public function event(): string
    {
        return 'delivery_updated';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['car', 'driver', 'deliveryZone']);

        return (new MailMessage)
            ->subject("Delivery for your booking {$booking->reference}")
            ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
            ->line("We have confirmed the delivery for booking **{$booking->reference}** ({$booking->car->name}, {$this->dates()}).")
            ->line('**Delivery area:** '.($booking->deliveryZone->name ?? '—'))
            ->line('**Delivery fee:** '.$this->money($booking->delivery_fee_minor))
            ->line('**Balance due at handover:** '.$this->money($booking->balance_minor))
            ->line('The reservation you have paid is unchanged. If anything looks wrong, just reply to this email.');
    }
}
