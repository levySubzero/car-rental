<?php

namespace App\Services\Notifications;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\Booking\BookingAccepted;
use App\Notifications\Booking\BookingCancelled;
use App\Notifications\Booking\BookingConfirmed;
use App\Notifications\Booking\BookingReceived;
use App\Notifications\Booking\DeliveryUpdated;
use App\Notifications\Booking\PaymentRejected;
use App\Notifications\Booking\PaymentSubmitted;
use App\Notifications\Booking\StaffNewBooking;
use App\Notifications\IntegrationTestMail;
use App\Notifications\PortalCode;
use Closure;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Test sends from the Resend integration page (M12B.14, M12B.3): one plain message, or every
 * booking email built from a real booking, all to the one address staff type. Sent at once,
 * not queued, so a Resend error shows on the page. The booking is never changed: links carry
 * a throwaway token, and nothing is recorded as sent.
 */
class TestMailService
{
    public function __construct(private readonly VoucherService $vouchers) {}

    /**
     * @return array{sent: list<string>, skipped: list<string>, failed: list<string>, error: string|null}
     */
    public function send(string $email, ?Booking $booking, User $actor): array
    {
        $result = ['sent' => [], 'skipped' => [], 'failed' => [], 'error' => null];
        $to = Notification::route('mail', $email);

        foreach ($this->messages($booking) as $label => $build) {
            $notification = $build();

            if ($notification === null || ! in_array('mail', $notification->via($to), true)) {
                $result['skipped'][] = $label;

                continue;
            }

            try {
                $to->notifyNow($notification);
                $result['sent'][] = $label;
            } catch (Throwable $exception) {
                $result['failed'][] = $label;
                $result['error'] ??= Str::limit($exception->getMessage(), 200);
            }
        }

        // The address is not kept: only that a test went out, and how it went.
        activity('integrations')->causedBy($actor)
            ->withProperties(['booking' => $booking?->reference, 'sent' => count($result['sent']), 'skipped' => count($result['skipped']), 'failed' => count($result['failed'])])
            ->event('test-mail')
            ->log('test emails sent');

        return $result;
    }

    /**
     * What to send, by label. A builder returns null when the booking lacks what that email
     * needs (a payment, a voucher, a delivery).
     *
     * @return array<string, Closure(): (BaseNotification|null)>
     */
    private function messages(?Booking $booking): array
    {
        if ($booking === null) {
            return ['Test message' => fn () => new IntegrationTestMail];
        }

        $booking->loadMissing(['car.carType', 'driver', 'deliveryZone']);
        // Links in test emails open nothing: only the hash of a real token is stored.
        $token = Str::random(64);
        $payment = $booking->payments()->with('gatewayTransaction')->latest('id')->first();

        return [
            'Request under review' => fn () => new BookingReceived($booking, $token),
            'New request (staff)' => fn () => new StaffNewBooking($booking),
            'Request approved' => fn () => new BookingAccepted($booking, $token),
            'Request declined' => fn () => new BookingCancelled($booking, 'This is a test email.'),
            'Payment to check (staff)' => fn () => $payment === null ? null : new PaymentSubmitted($booking, $payment),
            'Payment rejected' => fn () => new PaymentRejected($booking, 'This is a test email.', $token),
            'Confirmation with voucher' => fn () => $this->vouchers->current($booking) === null ? null : new BookingConfirmed($booking, $token),
            'Delivery updated' => fn () => $booking->needsDeliveryZone() ? new DeliveryUpdated($booking) : null,
            'Portal sign-in code' => fn () => new PortalCode('000000', 10),
        ];
    }
}
