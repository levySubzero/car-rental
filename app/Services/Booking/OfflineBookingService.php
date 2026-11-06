<?php

namespace App\Services\Booking;

use App\Enums\BookingSource;
use App\Enums\BookingStep;
use App\Enums\PaymentMethodCode;
use App\Models\Booking;
use App\Models\Car;
use App\Models\User;
use App\Services\Payments\PaymentVerificationService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * A booking staff enter for a client who did not book on the website: confirmed straight away,
 * so it blocks the car and counts in Bookings and Finance like any other. The client accepted
 * no terms online, and none of the online driver checks apply.
 */
class OfflineBookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly BookingService $bookings,
        private readonly BookingQuoteAdjuster $adjuster,
        private readonly BookingReferenceGenerator $references,
        private readonly BookingTokenService $tokens,
        private readonly PaymentVerificationService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $trip  ValidatesTrip::tripData()
     * @param  array{first_name: string, surname: string, email: string|null, phone: string|null}  $client
     */
    public function create(Car $car, array $trip, array $client, ?Money $total, ?Money $deposit, Money $paid, ?PaymentMethodCode $method, ?string $reference, User $actor): Booking
    {
        return DB::transaction(function () use ($car, $trip, $client, $total, $deposit, $paid, $method, $reference, $actor) {
            $booking = new Booking([...$trip, 'car_id' => $car->id, 'current_step' => BookingStep::Review]);
            $booking->access_token_hash = $this->tokens->issue()['hash'];
            $booking->source = BookingSource::Admin;
            $booking->setRelation('car', $car);
            $booking->save();

            $booking->driver()->create([...$client, 'is_kenyan_resident' => false]);
            $booking->load('driver');

            $quote = $this->bookings->quote($booking);
            $this->availability->lockAndHold($booking);

            $booking->forceFill([
                'reference' => $this->references->next(),
                'quote' => $quote->toArray(),
                'cancellation_tiers' => $this->bookings->cancellationTiers(),
                'total_minor' => $quote->total->minor,
                'reservation_minor' => $quote->reservation->minor,
                'balance_minor' => $quote->balance->minor,
                'security_deposit_minor' => $quote->deposit?->minor,
                'submitted_at' => now(),
            ])->save();

            // The array cast turns the snapshot's Money objects into plain data here.
            $booking->refresh();
            $this->adjuster->adjust($booking, $total, $deposit);
            $booking->save();

            $this->payments->recordOffline($booking, $paid, $method, $reference, $actor);

            activity(BookingDeskService::LOG_NAME)->performedOn($booking)->causedBy($actor)
                ->withProperties(['paid_minor' => $paid->minor, 'total_minor' => $booking->total_minor])
                ->event('entered')
                ->log('booking entered by staff');

            return $booking;
        });
    }
}
