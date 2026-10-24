<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Cancelling a booking or marking a no-show from the desk (M11.8, M11.14, D34). The refund
 * is suggested from the booking's own tier snapshot; staff may change it with a reason, and
 * both amounts are kept. The money goes back by hand, so staff mark it refunded later.
 */
class CancellationService
{
    public function __construct(
        private readonly BookingStatusService $statuses,
        private readonly RefundCalculator $calculator,
    ) {}

    public function preview(Booking $booking, bool $noShow = false): RefundSuggestion
    {
        return $this->calculator->suggest(
            $booking->cancellation_tiers ?? [],
            $booking->pickup_at,
            now(),
            new Money((int) $booking->reservation_paid_minor),
            $noShow,
        );
    }

    public function cancel(Booking $booking, string $reason, Money $refund, ?string $overrideReason, User $actor): Booking
    {
        return $this->close($booking, BookingStatus::Cancelled, $reason, $refund, $overrideReason, $actor);
    }

    public function noShow(Booking $booking, string $reason, Money $refund, ?string $overrideReason, User $actor): Booking
    {
        return $this->close($booking, BookingStatus::NoShow, $reason, $refund, $overrideReason, $actor);
    }

    public function markRefunded(Booking $booking, string $reference, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $reference, $actor) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ((int) $booking->refund_minor <= 0 || $booking->refunded_at !== null) {
                throw BookingException::noRefundDue();
            }

            $booking->forceFill([
                'refund_reference' => trim($reference),
                'refunded_at' => now(),
                'refunded_by' => $actor->id,
            ])->save();

            activity(BookingDeskService::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties(['refund_minor' => $booking->refund_minor, 'reference' => $booking->refund_reference])
                ->event('refunded')
                ->log('refund paid back');

            return $booking;
        });
    }

    private function close(Booking $booking, BookingStatus $to, string $reason, Money $refund, ?string $overrideReason, User $actor): Booking
    {
        $reason = trim($reason);
        $overrideReason = $overrideReason !== null && trim($overrideReason) !== '' ? trim($overrideReason) : null;

        return DB::transaction(function () use ($booking, $to, $reason, $refund, $overrideReason, $actor) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->status->canTransitionTo($to)) {
                throw BookingException::invalidTransition($booking->status, $to);
            }

            $suggestion = $this->preview($booking, $to === BookingStatus::NoShow);

            if ($refund->minor > (int) $booking->reservation_paid_minor) {
                throw BookingException::refundAboveReservation();
            }

            if ($refund->minor !== $suggestion->amount->minor && $overrideReason === null) {
                throw BookingException::refundOverrideNeedsReason();
            }

            $booking->forceFill([
                'cancellation_reason' => $reason,
                'refund_tier' => $suggestion->tier,
                'refund_suggested_minor' => $suggestion->amount->minor,
                'refund_minor' => $refund->minor,
                'refund_override_reason' => $refund->minor === $suggestion->amount->minor ? null : $overrideReason,
            ])->save();

            // A payment still waiting to be checked is closed with the booking (D41).
            $booking->payments()->where('status', PaymentStatus::Pending)->update([
                'status' => PaymentStatus::Rejected->value,
                'rejection_reason' => 'Booking cancelled',
                'rejected_by' => $actor->id,
                'rejected_at' => now(),
            ]);

            $this->statuses->transition($booking, $to, $actor, $reason);

            activity(BookingDeskService::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties([
                    'notice_hours' => $suggestion->noticeHours,
                    'tier' => $suggestion->tier,
                    'suggested_minor' => $suggestion->amount->minor,
                    'refund_minor' => $refund->minor,
                    'override_reason' => $booking->refund_override_reason,
                ])
                ->event($to === BookingStatus::NoShow ? 'no-show' : 'cancelled')
                ->log($to === BookingStatus::NoShow ? 'marked as no-show' : 'booking cancelled');

            return $booking;
        });
    }
}
