<?php

namespace App\Notifications\Booking;

use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Staff alert for a submitted booking (M10.4, PRD P-7), to the staff emails and the staff
 * WhatsApp number in Notification settings.
 */
class StaffNewBooking extends BookingNotification
{
    public function event(): string
    {
        return 'staff_new_booking';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['car', 'driver']);
        $driver = $booking->driver;

        $mail = (new MailMessage)
            ->subject("New booking {$booking->reference}: {$booking->car->name}")
            ->greeting('New booking')
            ->line("**Reference:** {$booking->reference}")
            ->line("**Renter:** {$this->renterName()}");

        foreach (array_filter(['Phone' => $driver?->phone, 'Email' => $driver?->email]) as $label => $value) {
            $mail->line("**{$label}:** {$value}");
        }

        $mail->line("**Car:** {$booking->car->name}")
            ->line("**Dates:** {$this->dates()}")
            ->line("**Total:** {$this->money($booking->total_minor)}, reservation {$this->money($booking->reservation_minor)}")
            ->line('The car is held while the renter pays the reservation.')
            ->action('Open the payments queue', route('admin.payments.index', ['search' => $booking->reference]));

        $chat = WhatsAppLinkBuilder::linkTo($driver?->phone);

        return $chat === null ? $mail : $mail->line("[Message the renter on WhatsApp]({$chat})");
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $this->booking->loadMissing('car');

        return new WhatsAppMessage(
            to: $this->phoneFor($notifiable),
            text: "New booking {$this->booking->reference}: {$this->booking->car->name}, {$this->dates()}, from {$this->renterName()}. Total {$this->money($this->booking->total_minor)}.",
            notification: $this->event(),
            subject: $this->booking,
        );
    }
}
