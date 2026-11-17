<?php

namespace App\Services\Booking;

use App\Enums\BookingStatus;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\Car;
use App\Models\User;
use App\Services\Notifications\VoucherService;
use App\Services\Pricing\Quote\Quote;
use App\Services\Pricing\Quote\QuoteLine;
use App\Services\Pricing\QuoteService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Staff changing a booking's trip (edit, before handover) or moving its return (extend, a
 * confirmed or running hire). The whole trip is quoted again at today's rates; what was
 * already paid stays paid and the difference lands on the balance.
 */
class BookingChangeService
{
    public const EDITABLE = [
        BookingStatus::Requested, BookingStatus::AwaitingPayment, BookingStatus::PaymentSubmitted,
        BookingStatus::AwaitingCash, BookingStatus::PaymentRejected, BookingStatus::Reserved,
    ];

    public const EXTENDABLE = [BookingStatus::Reserved, BookingStatus::Active];

    private const TRIP_FIELDS = [
        'car_id', 'pickup_at', 'return_at', 'pickup_mode', 'return_mode', 'delivery_zone_id',
        'delivery_address', 'delivery_landmark', 'delivery_lat', 'delivery_lng', 'delivery_contact_phone',
        'return_address', 'return_landmark', 'return_lat', 'return_lng', 'return_contact_phone',
        'extras', 'flight_number',
    ];

    public function __construct(
        private readonly QuoteService $quotes,
        private readonly AvailabilityService $availability,
        private readonly BookingQuoteAdjuster $adjuster,
        private readonly VoucherService $vouchers,
    ) {}

    /**
     * What the change would do, for the dialog: nothing is saved.
     *
     * @param  array<string, mixed>  $trip
     * @return array{before: array<string, mixed>, after: array<string, mixed>, difference: Money, available: bool}
     */
    public function preview(Booking $booking, array $trip, bool $extend): array
    {
        $this->ensureAllowed($booking, $extend);
        $before = $this->figures($booking);

        $changed = $booking->replicate();
        $changed->id = $booking->id;
        $changed->setRelation('driver', $booking->driver);
        $changed->fill($this->only($trip, $extend));
        $this->reprice($changed, $booking, $this->quote($changed));

        return [
            'before' => $before,
            'after' => $this->figures($changed),
            'difference' => new Money((int) $changed->total_minor - (int) $booking->total_minor),
            'available' => $this->availability->isAvailable($changed->car()->withTrashed()->firstOrFail(), $changed->range(), $booking),
        ];
    }

    /**
     * @param  array<string, mixed>  $trip
     */
    public function apply(Booking $booking, array $trip, ?Money $total, User $actor, bool $extend): Booking
    {
        $booking = DB::transaction(function () use ($booking, $trip, $total, $actor, $extend) {
            $booking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->ensureAllowed($booking, $extend);

            $original = clone $booking;
            $before = $this->figures($booking);
            $booking->fill($this->only($trip, $extend));

            // Lock the car (the new one, if it changed) so two staff cannot take the same dates.
            $car = Car::withTrashed()->whereKey($booking->car_id)->lockForUpdate()->firstOrFail();
            $booking->setRelation('car', $car);

            // A request holds no car yet: accepting it checks again.
            if ($booking->status !== BookingStatus::Requested && ! $this->availability->isAvailable($car, $booking->range(), $booking)) {
                throw BookingException::carUnavailable();
            }

            $this->reprice($booking, $original, $this->quote($booking));

            if ($total !== null && $total->minor !== (int) $booking->total_minor) {
                $this->adjuster->adjust($booking, $total, null);
                $this->settle($booking, $original);
            }

            $booking->save();
            $this->availability->resize($booking);

            activity(BookingDeskService::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties(['before' => $before, 'after' => $this->figures($booking), 'override' => $total?->minor])
                ->event($extend ? 'extended' : 'edited')
                ->log($extend ? 'booking extended' : 'booking edited');

            return $booking;
        });

        // The voucher shows the new dates and figures; staff send it again when ready.
        if ($this->vouchers->current($booking) !== null) {
            $this->vouchers->generate($booking, $actor);
        }

        return $booking;
    }

    public function ensureAllowed(Booking $booking, bool $extend): void
    {
        if (! in_array($booking->status, $extend ? self::EXTENDABLE : self::EDITABLE, true)) {
            throw BookingException::cannotChange($booking->status, $extend ? 'extended' : 'edited');
        }
    }

    /**
     * An extension only moves the return.
     *
     * @param  array<string, mixed>  $trip
     * @return array<string, mixed>
     */
    private function only(array $trip, bool $extend): array
    {
        return array_intersect_key($trip, array_flip($extend ? ['return_at'] : self::TRIP_FIELDS));
    }

    private function quote(Booking $booking): Quote
    {
        $car = $booking->car()->withTrashed()->firstOrFail();

        return $this->quotes->quote(
            $car,
            $booking->pickup_at,
            $booking->return_at,
            $booking->extras ?? [],
            $booking->needsDeliveryZone() ? $booking->delivery_zone_id : null,
            $booking->driver?->dob,
        );
    }

    /**
     * Put the new quote on the booking. A delivery fee staff set stays while the zone does; the
     * deposit staff agreed stays unless the car changed.
     */
    private function reprice(Booking $booking, Booking $original, Quote $quote): void
    {
        $snapshot = $quote->toArray();
        $total = $quote->total->minor;

        $keepsFee = $original->delivery_fee_minor !== null && $booking->needsDeliveryZone()
            && $booking->delivery_zone_id === $original->delivery_zone_id;

        if ($keepsFee) {
            $quoted = 0;

            foreach ($quote->lines as $line) {
                if ($line->kind === QuoteLine::KIND_DELIVERY) {
                    $quoted = (int) $line->amount?->minor;
                }
            }

            $total = $total - $quoted + (int) $original->delivery_fee_minor;
        }

        $deposit = $booking->car_id !== $original->car_id
            ? ($quote->deposit->minor ?? $original->security_deposit_minor)
            : $original->security_deposit_minor;
        $snapshot['deposit'] = $deposit === null ? null : new Money($deposit);

        $booking->forceFill([
            'quote' => $snapshot,
            'total_minor' => $total,
            'security_deposit_minor' => $deposit,
            'delivery_fee_minor' => $keepsFee ? $original->delivery_fee_minor : null,
            'reservation_minor' => (new Money($total))->percentBp($quote->settings->reservationPercentBp, $quote->settings->reservationRounding)->minor,
        ]);

        $this->settle($booking, $original);
    }

    /**
     * Once paid, the reservation is what it was and every change goes onto the balance; before
     * that, the reservation follows the new total.
     */
    private function settle(Booking $booking, Booking $original): void
    {
        $paid = $original->reservation_paid_minor;

        if ($paid !== null) {
            $booking->reservation_minor = $original->reservation_minor;
        }

        $booking->balance_minor = max(0, (int) $booking->total_minor - (int) ($paid ?? $booking->reservation_minor));
    }

    /**
     * @return array{car: string|null, pickup_at: string, return_at: string, days: int, total: Money, reservation: Money, balance: Money, deposit: Money|null}
     */
    private function figures(Booking $booking): array
    {
        return [
            'car' => $booking->car()->withTrashed()->value('name'),
            'pickup_at' => $booking->pickup_at->toIso8601String(),
            'return_at' => $booking->return_at->toIso8601String(),
            'days' => (int) ($booking->quote['days'] ?? 0),
            'total' => new Money((int) $booking->total_minor),
            'reservation' => new Money((int) $booking->reservation_minor),
            'balance' => new Money((int) $booking->balance_minor),
            'deposit' => $booking->security_deposit_minor === null ? null : new Money($booking->security_deposit_minor),
        ];
    }
}
