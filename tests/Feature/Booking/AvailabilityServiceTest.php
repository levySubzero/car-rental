<?php

use App\Enums\AvailabilityBlockType;
use App\Exceptions\Domain\BookingException;
use App\Exceptions\Domain\CatalogException;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Car;
use App\Services\Booking\AvailabilityService;
use App\Services\Catalog\CarBlackoutService;
use App\Settings\PricingSettings;
use App\Support\DateRange;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-01 08:00'));
    $this->car = Car::factory()->published()->create();
    $this->service = app(AvailabilityService::class);
    $this->range = new DateRange(CarbonImmutable::parse('2026-11-10 10:00'), CarbonImmutable::parse('2026-11-13 10:00'));
});

function blockFor(Car $car, string $start, string $end, string $type = 'blocked', array $extra = []): AvailabilityBlock
{
    $factory = AvailabilityBlock::factory();
    $factory = match ($type) {
        'hold' => $factory->hold(),
        'booked' => $factory->booked(),
        default => $factory,
    };

    return $factory->create(['car_id' => $car->id, 'starts_at' => $start, 'ends_at' => $end, ...$extra]);
}

it('is blocked by staff blocks, live holds and bookings that overlap', function (string $type) {
    blockFor($this->car, '2026-11-12 09:00', '2026-11-14 09:00', $type);

    expect($this->service->isAvailable($this->car, $this->range))->toBeFalse()
        ->and($this->service->conflicts($this->car, $this->range))->toHaveCount(1);
})->with(['blocked', 'hold', 'booked']);

it('ignores expired holds, other cars and back-to-back periods', function () {
    blockFor($this->car, '2026-11-10 10:00', '2026-11-13 10:00', 'hold', ['expires_at' => now()->subMinute()]);
    blockFor(Car::factory()->create(), '2026-11-10 10:00', '2026-11-13 10:00');
    blockFor($this->car, '2026-11-08 10:00', '2026-11-10 10:00');
    blockFor($this->car, '2026-11-13 10:00', '2026-11-15 10:00', 'booked');

    expect($this->service->isAvailable($this->car, $this->range))->toBeTrue();
});

it('keeps the turnaround buffer between blocks', function () {
    $pricing = app(PricingSettings::class);
    $pricing->turnaround_minutes = 120;
    $pricing->save();

    blockFor($this->car, '2026-11-13 11:00', '2026-11-15 10:00', 'booked');

    expect(app(AvailabilityService::class)->isAvailable($this->car, $this->range))->toBeFalse();
});

it('holds the car until the hold expires, and a second booking is refused', function () {
    $first = Booking::factory()->for($this->car)->create(['pickup_at' => $this->range->start, 'return_at' => $this->range->end]);
    $second = Booking::factory()->for($this->car)->create(['pickup_at' => '2026-11-12 10:00', 'return_at' => '2026-11-14 10:00']);

    $hold = $this->service->lockAndHold($first);

    expect($hold->type)->toBe(AvailabilityBlockType::Hold)
        ->and($hold->expires_at->toIso8601String())->toBe(now()->addMinutes(120)->toIso8601String())
        ->and(fn () => $this->service->lockAndHold($second))->toThrow(BookingException::class);

    // Holding again (a resubmit) replaces the booking's own hold instead of clashing with it.
    $this->service->lockAndHold($first);
    expect($first->availabilityBlocks()->count())->toBe(1);

    $this->travel(121)->minutes();
    expect($this->service->lockAndHold($second)->booking_id)->toBe($second->id);
});

it('turns a hold into a booking and releases it', function () {
    $booking = Booking::factory()->for($this->car)->create(['pickup_at' => $this->range->start, 'return_at' => $this->range->end]);
    $this->service->lockAndHold($booking);

    $this->service->confirm($booking);
    $block = $booking->availabilityBlocks()->sole();
    expect($block->type)->toBe(AvailabilityBlockType::Booked)->and($block->expires_at)->toBeNull();

    $this->travel(1)->days();
    expect($this->service->isAvailable($this->car, $this->range))->toBeFalse();

    $this->service->release($booking);
    expect($this->service->isAvailable($this->car, $this->range))->toBeTrue();
});

it('refuses a staff block over a booking, naming it', function () {
    $booking = Booking::factory()->for($this->car)->awaitingPayment()->create(['reference' => 'BE-2026-000042']);
    blockFor($this->car, '2026-11-10 10:00', '2026-11-13 10:00', 'hold', ['booking_id' => $booking->id]);

    $block = fn () => app(CarBlackoutService::class)->create($this->car, [
        'starts_at' => '2026-11-11 00:00',
        'ends_at' => '2026-11-12 00:00',
        'reason' => 'maintenance',
    ], staffWith());

    expect($block)->toThrow(CatalogException::class, 'overlaps booking BE-2026-000042')
        ->and($this->car->blackouts()->count())->toBe(0);
});

it('lists only staff blocks as the car\'s blackouts', function () {
    blockFor($this->car, '2026-11-10 10:00', '2026-11-13 10:00', 'hold');
    blockFor($this->car, '2026-11-20 10:00', '2026-11-21 10:00');

    expect($this->car->blackouts()->count())->toBe(1)
        ->and($this->car->availabilityBlocks()->count())->toBe(2);
});
