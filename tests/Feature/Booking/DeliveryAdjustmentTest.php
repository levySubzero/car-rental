<?php

use App\Enums\HandoverMode;
use App\Exceptions\Domain\BookingException;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\DeliveryZone;
use App\Notifications\Booking\DeliveryUpdated;
use App\Services\Booking\DeliveryAdjustmentService;
use App\Support\Money;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    $this->service = app(DeliveryAdjustmentService::class);
    $this->staff = staffWith();
    $this->quoted = DeliveryZone::factory()->create(['name' => 'Outside Nairobi', 'fee_minor' => null]);

    // Reserved with a staff-quoted delivery: the quote counted no delivery (D33).
    $this->booking = Booking::factory()->reserved()->create([
        'pickup_mode' => HandoverMode::Address,
        'delivery_zone_id' => $this->quoted->id,
        'delivery_address' => 'Plot 4, Ngong Road, Karen',
    ]);
    $this->booking->forceFill(['quote' => [...$this->booking->quote, 'requires_staff_quote' => true, 'lines' => [
        ['kind' => 'delivery', 'code' => 'delivery', 'amount' => null, 'counted' => false],
    ]]])->save();
    BookingDriver::factory()->for($this->booking)->create();
});

it('sets the quoted fee: the total and balance grow, the reservation and snapshot stay', function () {
    $snapshot = $this->booking->quote;

    $this->service->setFee($this->booking, Money::fromMajor(3000), $this->staff);

    $booking = $this->booking->fresh();
    expect($booking->delivery_fee_minor)->toBe(300_000)
        ->and($booking->total_minor)->toBe(6_635_000 + 300_000)
        ->and($booking->balance_minor)->toBe(6_935_000 - 1_658_800)
        ->and($booking->reservation_minor)->toBe(1_658_800)
        ->and($booking->quote)->toBe($snapshot)
        ->and($booking->deliveryPending())->toBeFalse();

    Notification::assertSentTo(new AnonymousNotifiable, DeliveryUpdated::class);
});

it('moves the booking to a priced zone and replaces the earlier fee, not adds to it', function () {
    $nairobi = DeliveryZone::factory()->create(['name' => 'Nairobi', 'fee_minor' => 200_000]);

    $this->service->setFee($this->booking, Money::fromMajor(3000), $this->staff);
    $this->service->changeZone($this->booking, $nairobi, null, $this->staff);

    $booking = $this->booking->fresh();
    expect($booking->delivery_zone_id)->toBe($nairobi->id)
        ->and($booking->delivery_fee_minor)->toBe(200_000)
        ->and($booking->total_minor)->toBe(6_635_000 + 200_000);
});

it('replaces a priced delivery already in the quote', function () {
    $this->booking->forceFill(['quote' => [...$this->booking->quote, 'total' => ['minor' => 6_835_000], 'lines' => [
        ['kind' => 'delivery', 'code' => 'delivery', 'amount' => ['minor' => 200_000], 'counted' => true],
    ]]])->save();

    $this->service->setFee($this->booking, Money::fromMajor(5000), $this->staff);

    expect($this->booking->fresh()->total_minor)->toBe(6_835_000 - 200_000 + 500_000);
});

it('needs a fee when moving to a staff-quoted zone', function () {
    $other = DeliveryZone::factory()->create(['fee_minor' => null]);

    expect(fn () => $this->service->changeZone($this->booking, $other, null, $this->staff))
        ->toThrow(BookingException::class, 'Enter the fee');
});

it('refuses once the car is on hire, and for office bookings', function () {
    $this->booking->forceFill(['status' => 'active'])->save();
    expect(fn () => $this->service->setFee($this->booking, Money::fromMajor(1), $this->staff))->toThrow(BookingException::class, 'already been handed over');

    $office = Booking::factory()->reserved()->create();
    expect(fn () => $this->service->setFee($office, Money::fromMajor(1), $this->staff))->toThrow(BookingException::class, 'no delivery');
});

it('records that staff checked the address', function () {
    $this->service->markChecked($this->booking, $this->staff);

    expect($this->booking->fresh()->delivery_checked_at)->not->toBeNull()
        ->and($this->booking->fresh()->delivery_checked_by)->toBe($this->staff->id);
});
