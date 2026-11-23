<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use App\Services\Notifications\VoucherService;
use App\Support\Money;
use Illuminate\Mail\Attachment;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * An extension is paid for (D50): the renter gets the original booking's voucher again, now
 * showing the extension and the new return. Sent with the same channel switch as the first
 * confirmation. `$booking` is the root; the PDF is looked up when sending, like BookingConfirmed.
 */
class BookingExtended extends BookingNotification
{
    public function __construct(Booking $root, public readonly Booking $extension, public readonly ?string $token)
    {
        parent::__construct($root);
    }

    public function event(): string
    {
        return 'booking_confirmed';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $root = $this->booking->loadMissing(['car', 'driver']);
        $vouchers = app(VoucherService::class);
        $fields = $vouchers->fields($root);
        $voucher = $vouchers->current($root);
        $paid = new Money((int) ($this->extension->reservation_paid_minor ?? $this->extension->total_minor));

        $mail = (new MailMessage)
            ->subject("Your booking {$root->reference} is extended")
            ->greeting('Hello '.($root->driver->first_name ?? 'there').',')
            ->line("Your extension **{$this->extension->reference}** is confirmed, so you can keep the {$fields['car']} longer. Your updated voucher is attached.")
            ->line("**Booking:** {$root->reference}")
            ->line("**Car:** {$fields['car']}")
            ->when($fields['area_of_use'] !== '', fn (MailMessage $mail) => $mail->line("**Area of use:** {$fields['area_of_use']}"))
            ->line("**New return:** {$fields['return']['date']} at {$fields['return']['time']}")
            ->line('**Extra days:** '.(int) ($this->extension->quote['days'] ?? 0))
            ->line("**Paid for the extension:** {$paid->format()}")
            ->when($this->token !== null, fn (MailMessage $mail) => $mail->action('Open your booking', route('bookings.show', $this->token)));

        if ($voucher !== null) {
            $mail->attach(
                Attachment::fromStorageDisk($voucher->disk, $voucher->path)
                    ->as("Voucher {$root->reference}.pdf")
                    ->withMime('application/pdf'),
            );
        }

        return $mail;
    }
}
