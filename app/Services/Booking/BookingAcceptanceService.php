<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\User;
use App\Settings\PricingSettings;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Staff accepting a website request: the car is held from here, and the renter's payment
 * window opens. Declining uses CancellationService.
 */
class BookingAcceptanceService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingQuoteAdjuster $adjuster,
        private readonly BookingStatusService $statuses,
        private readonly PricingSettings $pricing,
    ) {}

    public function accept(Booking $booking, ?Money $total, ?Money $deposit, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $total, $deposit, $actor) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if (! $booking->status->canTransitionTo(BookingStatus::AwaitingPayment)) {
                throw BookingException::invalidTransition($booking->status, BookingStatus::AwaitingPayment);
            }

            if ($booking->pickup_at->isPast()) {
                throw BookingException::pickupPassed();
            }

            if (! $this->availability->isAvailable($booking->car, $booking->range(), $booking)) {
                throw BookingException::carTakenForRequest();
            }

            $until = now()->addMinutes($this->pricing->hold_minutes);
            $until = $until->isAfter($booking->pickup_at) ? $booking->pickup_at : $until;

            // The car row is locked here, so a second request for these dates waits and then fails.
            $hold = $this->availability->lockAndHold($booking, $until);
            $adjusted = $this->adjuster->adjust($booking, $total, $deposit);

            $booking->expires_at = $hold->expires_at;
            $booking->save();

            $this->statuses->transition($booking, BookingStatus::AwaitingPayment, $actor);

            activity(BookingDeskService::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties(['before' => $adjusted, 'total_minor' => $booking->total_minor, 'security_deposit_minor' => $booking->security_deposit_minor])
                ->event('accepted')
                ->log('request accepted');

            return $booking;
        });
    }
}
