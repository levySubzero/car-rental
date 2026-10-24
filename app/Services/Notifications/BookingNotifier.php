<?php

namespace App\Services\Notifications;

use App\Enums\AlertKind;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Notifications\Booking\BookingConfirmed;
use App\Notifications\Booking\BookingReceived;
use App\Notifications\Booking\DeliveryUpdated;
use App\Notifications\Booking\PaymentRejected;
use App\Notifications\Booking\PaymentSubmitted;
use App\Notifications\Booking\StaffNewBooking;
use App\Services\Booking\BookingTokenService;
use App\Settings\NotificationSettings;
use App\Support\WhatsAppLinkBuilder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/**
 * Who hears about what in a booking's life (M10, TECHNICAL_PLAN §13). Renters are reached
 * at the main driver's email and phone; staff at the recipients in Notification settings.
 *
 * Only the token's hash is stored, so a renter link exists only where the plain token is
 * known: on submit the renter's own, and after a staff decision a fresh one (D39). Rotating
 * there means the mail always carries a working link, and older links stop working.
 */
class BookingNotifier
{
    public function __construct(
        private readonly NotificationSettings $settings,
        private readonly BookingTokenService $tokens,
        private readonly VoucherService $vouchers,
        private readonly AdminAlerts $alerts,
    ) {}

    public function submitted(Booking $booking, string $token): void
    {
        $this->renter($booking)?->notify(new BookingReceived($booking, $token));
        $this->staff()?->notify(new StaffNewBooking($booking));

        $booking->loadMissing(['car', 'driver']);
        $this->alerts->send(
            AlertKind::NewBooking,
            "New booking {$booking->reference}",
            trim(($booking->driver ? "{$booking->driver->first_name} {$booking->driver->surname}, " : '').$booking->car->name),
            $this->queueUrl($booking),
        );
    }

    /**
     * A payment for staff to check, cash on its way, or (M11B) money received online and
     * confirmed already, which staff only need to know about. By the time this queued job
     * runs an online payment is verified, so the newest submission is taken either way.
     */
    public function paymentSubmitted(Booking $booking): void
    {
        $payment = $booking->payments()->with('gatewayTransaction')->latest('id')->first();

        if ($payment === null || ! ($payment->isPending() || $payment->isOnline())) {
            return;
        }

        $this->staff()?->notify(new PaymentSubmitted($booking, $payment));

        [$title, $body] = match (true) {
            $payment->isOnline() => [
                "Paid online for {$booking->reference}",
                ($payment->gatewayTransaction?->gateway->label() ?? 'Online').", receipt {$payment->reference}. Confirmed automatically, nothing to check.",
            ],
            $payment->isCash() => ["Cash coming for {$booking->reference}", 'The renter will pay the reservation in cash.'],
            default => ["Payment to check for {$booking->reference}", 'Paid by '.($payment->method->name ?? $payment->payment_method_code->value).'. Check and verify it.'],
        };

        $this->alerts->send(AlertKind::PaymentSubmitted, $title, $body, $this->queueUrl($booking));
    }

    /**
     * The voucher is generated whether or not the mail is on, so staff can always resend it.
     */
    public function confirmed(Booking $booking): void
    {
        $voucher = $this->vouchers->generate($booking);
        $renter = $this->renter($booking);

        $renter?->notify(new BookingConfirmed($booking, $this->freshToken($booking, 'booking_confirmed'), $voucher));
    }

    /**
     * Send the confirmation again from the desk (M11.5): the stored voucher, or a new one if
     * it is missing, and a fresh renter link. False when the mail is off or there is no renter.
     */
    public function resendConfirmation(Booking $booking): bool
    {
        $renter = $this->renter($booking);

        if ($renter === null || ! $this->settings->enabled('booking_confirmed', 'mail')) {
            return false;
        }

        $voucher = $this->vouchers->current($booking) ?? $this->vouchers->generate($booking);
        $renter->notify(new BookingConfirmed($booking, $this->freshToken($booking, 'booking_confirmed'), $voucher));

        return true;
    }

    public function deliveryUpdated(Booking $booking): void
    {
        $this->renter($booking)?->notify(new DeliveryUpdated($booking));
    }

    public function rejected(Booking $booking): void
    {
        $payment = $booking->payments()->where('status', PaymentStatus::Rejected)->latest('id')->first();
        $renter = $this->renter($booking);

        $renter?->notify(new PaymentRejected($booking, (string) $payment?->rejection_reason, $this->freshToken($booking, 'payment_rejected')));
    }

    /**
     * The wa.me link staff open to send the confirmation themselves (click-to-chat). Null
     * when the WhatsApp confirmation is turned off or the renter has no phone.
     */
    public function confirmationWhatsAppLink(Booking $booking): ?string
    {
        if (! $this->settings->enabled('booking_confirmed', 'whatsapp')) {
            return null;
        }

        return WhatsAppLinkBuilder::linkTo($booking->driver?->phone, $this->vouchers->whatsAppText($booking));
    }

    /**
     * A new link for a mail that will go out. Without that mail the renter's current link
     * is left working.
     */
    private function freshToken(Booking $booking, string $event): ?string
    {
        return $this->settings->enabled($event, 'mail') ? $this->tokens->rotate($booking) : null;
    }

    private function queueUrl(Booking $booking): string
    {
        return route('admin.payments.index', ['search' => $booking->reference]);
    }

    private function renter(Booking $booking): ?AnonymousNotifiable
    {
        $driver = $booking->driver;

        if ($driver === null) {
            return null;
        }

        return Notification::route('mail', $driver->email)->route('whatsapp', $driver->phone);
    }

    private function staff(): ?AnonymousNotifiable
    {
        $routes = array_filter([
            'mail' => $this->settings->staff_emails,
            'whatsapp' => $this->settings->staff_whatsapp_number,
        ]);

        if ($routes === []) {
            return null;
        }

        $notifiable = new AnonymousNotifiable;

        foreach ($routes as $channel => $route) {
            $notifiable->route($channel, $route);
        }

        return $notifiable;
    }
}
