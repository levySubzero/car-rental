<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\BookingException;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\CancellationTier;
use App\Models\PaymentSubmission;
use App\Services\Booking\CancellationService;
use App\Support\Money;

beforeEach(function () {
    $this->service = app(CancellationService::class);
    $this->staff = staffWith();
});

function tieredBooking(int $hoursAhead): Booking
{
    $pickup = now()->addHours($hoursAhead)->startOfMinute();

    return Booking::factory()->reserved()->create([
        'pickup_at' => $pickup,
        'return_at' => $pickup->addDays(3),
        'cancellation_tiers' => [
            ['label' => 'Two days or more', 'hours_before_pickup' => 48, 'refund_percent_bp' => 10_000],
            ['label' => 'A day or more', 'hours_before_pickup' => 24, 'refund_percent_bp' => 5_000],
        ],
    ]);
}

it('cancels with the suggested refund from the booking own tiers and frees the car', function () {
    $booking = tieredBooking(30);

    $this->service->cancel($booking, 'Flight cancelled', new Money(829_400), null, $this->staff);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancellation_reason)->toBe('Flight cancelled')
        ->and($booking->refund_tier['label'])->toBe('A day or more')
        ->and($booking->refund_suggested_minor)->toBe(829_400)
        ->and($booking->refund_minor)->toBe(829_400)
        ->and($booking->refund_override_reason)->toBeNull()
        ->and($booking->closed_at)->not->toBeNull()
        ->and(AvailabilityBlock::where('booking_id', $booking->id)->count())->toBe(0);
});

it('needs a reason to override the suggestion, and keeps both amounts', function () {
    $booking = tieredBooking(30);

    expect(fn () => $this->service->cancel($booking, 'Changed plans', new Money(1_658_800), null, $this->staff))
        ->toThrow(BookingException::class, 'Say why');

    $this->service->cancel($booking, 'Changed plans', new Money(1_658_800), 'Loyal customer', $this->staff);

    $booking->refresh();
    expect($booking->refund_suggested_minor)->toBe(829_400)
        ->and($booking->refund_minor)->toBe(1_658_800)
        ->and($booking->refund_override_reason)->toBe('Loyal customer');
});

it('refuses a refund above the reservation paid', function () {
    expect(fn () => $this->service->cancel(tieredBooking(72), 'x', new Money(1_658_801), 'too much', $this->staff))
        ->toThrow(BookingException::class, 'more than the reservation paid');
});

it('suggests nothing for a no-show', function () {
    $booking = tieredBooking(72);

    expect($this->service->preview($booking, noShow: true)->amount->minor)->toBe(0);

    $this->service->noShow($booking, 'Did not arrive', Money::zero(), null, $this->staff);

    expect($booking->fresh()->status)->toBe(BookingStatus::NoShow)
        ->and($booking->fresh()->refund_minor)->toBe(0);
});

it('uses the tiers frozen at submit, not edited ones', function () {
    $booking = tieredBooking(30);
    CancellationTier::factory()->create(['hours_before_pickup' => 12, 'refund_percent_bp' => 10_000]);

    expect($this->service->preview($booking)->percentBp)->toBe(5_000);
});

it('cancels an unpaid booking and closes its pending payment', function () {
    $booking = Booking::factory()->awaitingPayment()->create(['status' => BookingStatus::PaymentSubmitted]);
    $payment = PaymentSubmission::factory()->for($booking)->create();

    $this->service->cancel($booking, 'Renter called it off', Money::zero(), null, $this->staff);

    expect($booking->fresh()->status)->toBe(BookingStatus::Cancelled)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->fresh()->rejection_reason)->toBe('Booking cancelled');
});

it('marks the refund paid back once', function () {
    $booking = tieredBooking(72);
    $this->service->cancel($booking, 'Plans changed', new Money(1_658_800), null, $this->staff);

    $this->service->markRefunded($booking, 'MPESA QWE123', $this->staff);

    expect($booking->fresh()->refund_reference)->toBe('MPESA QWE123')
        ->and($booking->fresh()->refunded_at)->not->toBeNull();

    expect(fn () => $this->service->markRefunded($booking->fresh(), 'again', $this->staff))
        ->toThrow(BookingException::class, 'no refund to record');
});

it('refuses cancelling a booking on hire', function () {
    expect(fn () => $this->service->cancel(Booking::factory()->active()->create(), 'x', Money::zero(), null, $this->staff))
        ->toThrow(BookingException::class, 'from On hire to Cancelled');
});
