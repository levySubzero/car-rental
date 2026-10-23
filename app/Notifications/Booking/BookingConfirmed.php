<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use App\Models\Media;
use App\Services\Notifications\VoucherService;
use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use App\Settings\NotificationSettings;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Mail\Attachment;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The renter's confirmation once the reservation is verified (M10.7, PRD P-5): the voucher
 * PDF attached to the mail, and the same confirmation as a WhatsApp message.
 */
class BookingConfirmed extends BookingNotification
{
    public function __construct(Booking $booking, public readonly ?string $token, public readonly ?Media $voucher)
    {
        parent::__construct($booking);
    }

    public function event(): string
    {
        return 'booking_confirmed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = app(NotificationSettings::class);
        $booking = $this->booking->loadMissing(['car', 'driver']);

        $mail = (new MailMessage)
            ->subject(WhatsAppLinkBuilder::fill($settings->voucher_email_subject, ['reference' => $booking->reference]))
            ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
            ->line(WhatsAppLinkBuilder::fill($settings->voucher_email_intro, ['reference' => $booking->reference]))
            ->line("**Reference:** {$booking->reference}")
            ->line("**Car:** {$booking->car->name}")
            ->line("**Dates:** {$this->dates()}")
            ->line("**Balance due at handover:** {$this->money($booking->balance_minor)}")
            ->when($this->token !== null, fn (MailMessage $mail) => $mail->action('Open your booking', route('bookings.show', $this->token)));

        if ($this->voucher !== null) {
            $mail->attach(
                Attachment::fromStorageDisk($this->voucher->disk, $this->voucher->path)
                    ->as("Voucher {$booking->reference}.pdf")
                    ->withMime('application/pdf'),
            );
        }

        return $mail;
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return new WhatsAppMessage(
            to: $this->phoneFor($notifiable),
            text: app(VoucherService::class)->whatsAppText($this->booking),
            notification: $this->event(),
            subject: $this->booking,
        );
    }
}
