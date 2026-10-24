<?php

namespace App\Services\Booking;

use App\Enums\BookingBucket;
use App\Enums\BookingStatus;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\DeliveryZone;
use App\Models\User;
use App\Services\Notifications\BookingNotifier;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Staff checking a delivery against its zone (M11.13, D33): quote the fee for a staff-quoted
 * zone, or move the booking to the right zone. Only the total and the balance change; the
 * reservation already paid and the quote snapshot stay as they were.
 */
class DeliveryAdjustmentService
{
    public function __construct(private readonly BookingNotifier $notifier) {}

    public function setFee(Booking $booking, Money $fee, User $actor): Booking
    {
        return $this->apply($booking, null, $fee, $actor);
    }

    /**
     * A zone without a fee needs one from staff.
     */
    public function changeZone(Booking $booking, DeliveryZone $zone, ?Money $fee, User $actor): Booking
    {
        $fee = $zone->fee() ?? $fee ?? throw BookingException::zoneFeeNeeded();

        return $this->apply($booking, $zone, $fee, $actor);
    }

    /**
     * Staff have checked the address is in the zone.
     */
    public function markChecked(Booking $booking, User $actor): Booking
    {
        $this->ensureDelivery($booking);

        $booking->forceFill(['delivery_checked_at' => now(), 'delivery_checked_by' => $actor->id])->save();

        activity(BookingDeskService::LOG_NAME)->performedOn($booking)->causedBy($actor)
            ->event('delivery-checked')
            ->log('delivery address checked');

        return $booking;
    }

    private function apply(Booking $booking, ?DeliveryZone $zone, Money $fee, User $actor): Booking
    {
        $booking = DB::transaction(function () use ($booking, $zone, $fee, $actor) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->ensureDelivery($booking);

            $before = [
                'zone' => $booking->delivery_zone_id,
                'fee_minor' => $booking->deliveryFeeMinor(),
                'total_minor' => $booking->total_minor,
                'balance_minor' => $booking->balance_minor,
            ];

            $total = (int) ($booking->quote['total']['minor'] ?? $booking->total_minor) - (int) $booking->snapshotDeliveryMinor() + $fee->minor;
            $paid = $booking->reservation_paid_minor ?? (int) $booking->reservation_minor;

            $booking->forceFill([
                'delivery_zone_id' => $zone->id ?? $booking->delivery_zone_id,
                'delivery_fee_minor' => $fee->minor,
                'total_minor' => $total,
                'balance_minor' => max(0, $total - $paid),
            ])->save();

            activity(BookingDeskService::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties([
                    'before' => $before,
                    'after' => [
                        'zone' => $booking->delivery_zone_id,
                        'fee_minor' => $booking->delivery_fee_minor,
                        'total_minor' => $booking->total_minor,
                        'balance_minor' => $booking->balance_minor,
                    ],
                ])
                ->event('delivery-updated')
                ->log($zone === null ? 'delivery fee set' : 'delivery zone changed');

            return $booking;
        });

        $this->notifier->deliveryUpdated($booking);

        return $booking;
    }

    private function ensureDelivery(Booking $booking): void
    {
        if (! $booking->needsDeliveryZone()) {
            throw BookingException::noDelivery();
        }

        // Until handover: after it the balance has been taken.
        if (! in_array($booking->status, [...BookingBucket::Unpaid->statuses(), BookingStatus::Reserved], true)) {
            throw BookingException::deliveryLocked();
        }
    }
}
