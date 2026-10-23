<?php

namespace App\Notifications\Booking;

use App\Models\Booking;
use App\Models\PaymentSubmission;
use App\Services\Notifications\WhatsApp\WhatsAppMessage;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Staff alert for a renter's payment (M10.5): a transaction message to check, or a renter
 * coming to pay cash.
 */
class PaymentSubmitted extends BookingNotification
{
    public function __construct(Booking $booking, public readonly PaymentSubmission $payment)
    {
        parent::__construct($booking);
    }

    public function event(): string
    {
        return 'payment_submitted';
    }

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->booking->loadMissing(['car', 'driver']);
        $payment = $this->payment->loadMissing('method');
        $method = $payment->method->name ?? $payment->payment_method_code->value;

        $mail = (new MailMessage)
            ->subject($payment->isCash()
                ? "Cash payment coming for {$booking->reference}"
                : "Payment to check for {$booking->reference}")
            ->greeting($payment->isCash() ? 'A renter will pay in cash' : 'A payment is waiting to be checked')
            ->line("**Reference:** {$booking->reference}")
            ->line("**Renter:** {$this->renterName()}")
            ->line("**Car:** {$booking->car->name}")
            ->line("**Method:** {$method}")
            ->line("**Reservation due:** {$this->money($booking->reservation_minor)}");

        if ($payment->transaction_message !== null) {
            $mail->line('**Transaction message:** '.Str::limit($payment->transaction_message, 300));
        }

        $mail->action('Open the payments queue', route('admin.payments.index', ['search' => $booking->reference]));

        $chat = WhatsAppLinkBuilder::linkTo($booking->driver?->phone);

        return $chat === null ? $mail : $mail->line("[Message the renter on WhatsApp]({$chat})");
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $method = $this->payment->loadMissing('method')->method->name ?? $this->payment->payment_method_code->value;

        return new WhatsAppMessage(
            to: $this->phoneFor($notifiable),
            text: $this->payment->isCash()
                ? "{$this->booking->reference}: {$this->renterName()} will pay the reservation in cash."
                : "{$this->booking->reference}: {$this->renterName()} paid by {$method}. Please check and verify.",
            notification: $this->event(),
            subject: $this->booking,
        );
    }
}
