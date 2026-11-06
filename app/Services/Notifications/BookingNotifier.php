<?php

namespace App\Services\Notifications;

use App\Enums\AlertKind;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Notifications\Booking\BookingAccepted;
use App\Notifications\Booking\BookingCancelled;
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
            "New request {$booking->reference}",
            trim(($booking->driver ? "{$booking->driver->first_name} {$booking->driver->surname}, " : '').$booking->car->name),
            $this->bookingUrl($booking),
        );
    }

    /**
     * Staff accepted the request: the renter gets the link to pay.
     */
    public function accepted(Booking $booking): void
    {
        $this->renter($booking)?->notify(new BookingAccepted($booking, $this->freshToken($booking, 'booking_accepted')));

        $this->alerts->send(AlertKind::BookingUpdate, "Request accepted: {$booking->reference}", 'The renter has been sent the link to pay.', $this->bookingUrl($booking));
    }

    /**
     * A booking ended without a hire. A draft nobody finished stays silent.
     */
    public function closed(Booking $booking, BookingStatus $from, BookingStatus $to): void
    {
        if ($from === BookingStatus::Draft) {
            return;
        }

        $paid = $booking->paid_at !== null;
        $reason = match (true) {
            $to === BookingStatus::Cancelled => (string) $booking->cancellation_reason,
            $from === BookingStatus::Requested => 'We were not able to review your request before the pick-up time.',
            default => 'We did not receive the reservation payment in time.',
        };

        $this->renter($booking)?->notify(new BookingCancelled($booking, $reason));

        $title = match (true) {
            $to === BookingStatus::Cancelled => $paid ? "Booking cancelled: {$booking->reference}" : "Request declined: {$booking->reference}",
            $from === BookingStatus::Requested => "Request expired: {$booking->reference}",
            default => "Unpaid booking expired: {$booking->reference}",
        };

        $this->alerts->send(AlertKind::BookingUpdate, $title, $reason, $this->bookingUrl($booking));
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
                ($payment->gatewayTransaction?->gateway->label() ?? 'Online').", receipt {$payment->reference}. Confirmed automatically: review the voucher and send it.",
            ],
            $payment->isCash() => ["Cash coming for {$booking->reference}", 'The renter will pay the reservation in cash.'],
            default => ["Payment to check for {$booking->reference}", 'Paid by '.($payment->method->name ?? $payment->payment_method_code->value).'. Check and verify it.'],
        };

        $this->alerts->send(AlertKind::PaymentSubmitted, $title, $body, $this->queueUrl($booking));
    }

    /**
     * Confirmed: the voucher is ready for staff to review, and nothing goes to the renter until
     * they send it. Generated whether or not the mail is on, so staff can always download it.
     */
    public function confirmed(Booking $booking): void
    {
        $this->vouchers->generate($booking);

        // Whoever entered a client's booking is already on its voucher page.
        if ($booking->source === BookingSource::Admin) {
            $this->alerts->send(AlertKind::BookingUpdate, "Booking entered: {$booking->reference}", 'Entered by staff for a client who did not book online.', $this->bookingUrl($booking));

            return;
        }

        $this->alerts->send(
            AlertKind::VoucherReady,
            "Voucher ready: {$booking->reference}",
            'Review it and send it to the renter.',
            route('admin.bookings.voucher.show', $booking->reference),
        );
    }

    /**
     * Staff send the voucher, the first time or again (M11.5): the stored PDF, or a new one if
     * it is missing, and a fresh renter link. False when the mail is off or there is no email.
     */
    public function resendConfirmation(Booking $booking): bool
    {
        $renter = $this->renter($booking);

        if ($renter === null || blank($booking->driver?->email) || ! $this->settings->enabled('booking_confirmed', 'mail')) {
            return false;
        }

        if ($this->vouchers->current($booking) === null) {
            $this->vouchers->generate($booking);
        }

        $renter->notify(new BookingConfirmed($booking, $this->freshToken($booking, 'booking_confirmed')));
        $booking->forceFill(['voucher_sent_at' => now()])->save();

        $this->alerts->send(AlertKind::BookingUpdate, "Voucher sent: {$booking->reference}", "Emailed to {$booking->driver?->email}.", $this->bookingUrl($booking));

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

        $this->alerts->send(AlertKind::PaymentSubmitted, "Payment rejected for {$booking->reference}", (string) $payment?->rejection_reason, $this->queueUrl($booking));
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

    private function bookingUrl(Booking $booking): string
    {
        return route('admin.bookings.show', $booking->reference);
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
