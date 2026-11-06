<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use App\Services\Notifications\VoucherService;
use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use App\Settings\NotificationSettings;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Mail\Attachment;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The renter's confirmation, sent by staff once they have reviewed the voucher (M10.7, PRD
 * P-5): the voucher PDF attached to the mail, and the same confirmation as a WhatsApp message.
 * Its details come from the voucher, so the two agree after an edit. The PDF is looked up when
 * sending, because an edit made while this waits on the queue replaces the file.
 */
class BookingConfirmed extends BookingNotification
{
    public function __construct(Booking $booking, public readonly ?string $token)
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
        $vouchers = app(VoucherService::class);
        $fields = $vouchers->fields($booking);
        $voucher = $vouchers->current($booking);

        $mail = (new MailMessage)
            ->subject(WhatsAppLinkBuilder::fill($settings->voucher_email_subject, ['reference' => $booking->reference]))
            ->greeting('Hello '.($booking->driver->first_name ?? 'there').',')
            ->line(WhatsAppLinkBuilder::fill($settings->voucher_email_intro, ['reference' => $booking->reference]))
            ->line("**Reference:** {$booking->reference}")
            ->line("**Car:** {$fields['car']}")
            ->line("**Dates:** {$fields['pickup']['date']} at {$fields['pickup']['time']} to {$fields['return']['date']} at {$fields['return']['time']}")
            ->line("**Balance due at handover:** {$fields['balance']}")
            ->when($this->token !== null, fn (MailMessage $mail) => $mail->action('Open your booking', route('bookings.show', $this->token)));

        if ($voucher !== null) {
            $mail->attach(
                Attachment::fromStorageDisk($voucher->disk, $voucher->path)
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
