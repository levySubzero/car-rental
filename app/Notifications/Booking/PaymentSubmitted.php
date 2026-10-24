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
 * coming to pay cash. An online payment (M11B) is already confirmed, so staff are told it
 * arrived and that nothing needs checking.
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
        $payment = $this->payment->loadMissing(['method', 'gatewayTransaction']);
        $method = $payment->method->name ?? $payment->payment_method_code->value;
        $online = $payment->gatewayTransaction;

        $mail = (new MailMessage)
            ->subject(match (true) {
                $online !== null => "Paid online for {$booking->reference}",
                $payment->isCash() => "Cash payment coming for {$booking->reference}",
                default => "Payment to check for {$booking->reference}",
            })
            ->greeting(match (true) {
                $online !== null => 'A payment was received online and the booking is confirmed',
                $payment->isCash() => 'A renter will pay in cash',
                default => 'A payment is waiting to be checked',
            })
            ->line("**Reference:** {$booking->reference}")
            ->line("**Renter:** {$this->renterName()}")
            ->line("**Car:** {$booking->car->name}")
            ->line('**Method:** '.($online !== null ? $online->gateway->label() : $method))
            ->line("**Reservation due:** {$this->money($booking->reservation_minor)}");

        if ($online !== null) {
            $mail->line("**Paid:** {$this->money($online->amount_minor)}")
                ->line("**Receipt:** {$payment->reference}")
                ->line('Confirmed automatically: nothing to check. The renter has been sent the voucher.')
                ->action('Open the booking', route('admin.bookings.show', $booking->reference));
        } else {
            if ($payment->transaction_message !== null) {
                $mail->line('**Transaction message:** '.Str::limit($payment->transaction_message, 300));
            }

            $mail->action('Open the payments queue', route('admin.payments.index', ['search' => $booking->reference]));
        }

        $chat = WhatsAppLinkBuilder::linkTo($booking->driver?->phone);

        return $chat === null ? $mail : $mail->line("[Message the renter on WhatsApp]({$chat})");
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        $payment = $this->payment->loadMissing(['method', 'gatewayTransaction']);
        $method = $payment->method->name ?? $payment->payment_method_code->value;
        $online = $payment->gatewayTransaction;

        return new WhatsAppMessage(
            to: $this->phoneFor($notifiable),
            text: match (true) {
                $online !== null => "{$this->booking->reference}: {$this->renterName()} paid {$this->money($online->amount_minor)} by {$online->gateway->label()} (receipt {$payment->reference}). Confirmed automatically, nothing to check.",
                $payment->isCash() => "{$this->booking->reference}: {$this->renterName()} will pay the reservation in cash.",
                default => "{$this->booking->reference}: {$this->renterName()} paid by {$method}. Please check and verify.",
            },
            notification: $this->event(),
            subject: $this->booking,
        );
    }
}
