<?php

use App\Enums\BookingStatus;
use App\Enums\FuelLevel;
use App\Enums\HandoverMode;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Services\Booking\HandoverService;
use App\Support\Money;

beforeEach(function () {
    $this->service = app(HandoverService::class);
    $this->staff = staffWith();
});

function handOver(Booking $booking, int $balanceMinor = 4_976_200, ?string $notes = null): void
{
    test()->service->recordHandover($booking, 12_000, FuelLevel::Full, new Money($balanceMinor), new Money(16_000_000), $notes, test()->staff);
}

it('records the handover and puts the booking on hire', function () {
    $booking = Booking::factory()->reserved()->create();

    handOver($booking);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Active)
        ->and($booking->handover->odometer_km)->toBe(12_000)
        ->and($booking->handover->fuel_level)->toBe(FuelLevel::Full)
        ->and($booking->handover->balance_received_minor)->toBe(4_976_200)
        ->and($booking->handover->deposit_received_minor)->toBe(16_000_000)
        ->and($booking->handover->recorded_by)->toBe($this->staff->id);
});

it('needs a note when the balance taken differs from what is due', function () {
    $booking = Booking::factory()->reserved()->create();

    expect(fn () => handOver($booking, 4_000_000))->toThrow(BookingException::class, 'Add a note');
    expect($booking->fresh()->status)->toBe(BookingStatus::Reserved);

    handOver($booking, 4_000_000, 'Rest by bank transfer tomorrow');
    expect($booking->fresh()->status)->toBe(BookingStatus::Active);
});

it('refuses the handover while a staff-quoted delivery has no fee', function () {
    $booking = Booking::factory()->reserved()->create(['pickup_mode' => HandoverMode::Address]);

    expect(fn () => handOver($booking))->toThrow(BookingException::class, 'Set the delivery fee');

    $booking->forceFill(['delivery_fee_minor' => 300_000])->save();
    handOver($booking, notes: 'Balance as agreed');
    expect($booking->fresh()->status)->toBe(BookingStatus::Active);
});

it('refuses a booking that is not reserved, and a second handover', function () {
    $booking = Booking::factory()->awaitingPayment()->create();
    expect(fn () => handOver($booking))->toThrow(BookingException::class, 'from Awaiting payment to On hire');

    $reserved = Booking::factory()->reserved()->create();
    handOver($reserved);
    expect(fn () => handOver($reserved))->toThrow(BookingException::class);
});

it('records the return with the excess over the allowance and completes the booking', function () {
    $booking = Booking::factory()->reserved()->create();
    handOver($booking);

    // 3 days × 300 km = 900 km allowed; 1,000 driven.
    $this->service->recordReturn($booking, 13_000, FuelLevel::Half, new Money(15_000_000), new Money(1_000_000), '100 km over, fuel', $this->staff);

    $booking->refresh();
    expect($booking->status)->toBe(BookingStatus::Completed)
        ->and($booking->closed_at)->not->toBeNull()
        ->and($booking->returnRecord->excess_km)->toBe(100)
        ->and($booking->returnRecord->deposit_refunded_minor)->toBe(15_000_000)
        ->and($booking->returnRecord->extra_charges_minor)->toBe(1_000_000);
});

it('counts no excess with unlimited mileage', function () {
    $booking = Booking::factory()->reserved()->create();
    $booking->forceFill(['quote' => [...$booking->quote, 'mileage_km_per_day' => null]])->save();
    handOver($booking);

    $this->service->recordReturn($booking, 20_000, FuelLevel::Full, new Money(16_000_000), Money::zero(), null, $this->staff);

    expect($booking->fresh()->returnRecord->excess_km)->toBe(0);
});

it('refuses a reading below the handover, and keeping deposit without a note', function () {
    $booking = Booking::factory()->reserved()->create();
    handOver($booking);

    expect(fn () => $this->service->recordReturn($booking, 11_999, FuelLevel::Full, new Money(16_000_000), Money::zero(), null, $this->staff))
        ->toThrow(BookingException::class, 'lower than at handover (12000 km)');

    expect(fn () => $this->service->recordReturn($booking, 12_500, FuelLevel::Full, new Money(15_000_000), Money::zero(), null, $this->staff))
        ->toThrow(BookingException::class, 'Add a note');

    expect($booking->fresh()->status)->toBe(BookingStatus::Active);
});
