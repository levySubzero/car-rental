<?php

namespace App\Listeners;

use App\Enums\BookingStatus;
use App\Events\BookingStatusChanged;
use App\Services\Notifications\BookingNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Notifications that follow a status change (M10). Queued, so rendering the voucher never
 * slows the staff member's verify click. The submit acknowledgement is sent by
 * BookingService::submit(), which holds the renter's token.
 */
class SendBookingNotifications implements ShouldQueue
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking->fresh() ?? $event->booking;

        match ($event->to) {
            BookingStatus::PaymentSubmitted, BookingStatus::AwaitingCash => $this->notifier->paymentSubmitted($booking),
            BookingStatus::Reserved => $this->notifier->confirmed($booking),
            BookingStatus::PaymentRejected => $this->notifier->rejected($booking),
            default => null,
        };
    }
}
