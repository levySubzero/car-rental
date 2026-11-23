<?php

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Exceptions\Domain\BookingException;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\Car;
use App\Services\Booking\BookingChangeService;
use App\Services\Booking\BookingService;
use App\Services\Notifications\VoucherService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Database\Seeders\PricingSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BookingFixtures;
use Tests\Support\PricingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    Notification::fake();
    $this->seed(PricingSeeder::class);
    PricingFixtures::rates();

    $this->service = app(BookingChangeService::class);
    $this->staff = staffWith([Permission::BookingsView, Permission::BookingsExtend, Permission::BookingsEdit]);
    $this->car = Car::factory()->create(['daily_rate_minor' => 420_000, 'car_value_minor' => 200_000_000]);

    // 5 Nov 12:30 → 13 Nov 11:30, as BE-2026-000002: KES 4,200 a day.
    $this->booking = Booking::factory()->reserved()->for($this->car)->create([
        'pickup_at' => CarbonImmutable::parse('2026-12-05 12:30', 'Africa/Nairobi')->utc(),
        'return_at' => CarbonImmutable::parse('2026-12-13 11:30', 'Africa/Nairobi')->utc(),
        'security_deposit_minor' => 4_400_000,
    ]);
    $this->booking->availabilityBlocks()->update(['starts_at' => $this->booking->pickup_at, 'ends_at' => $this->booking->return_at]);
    BookingDriver::factory()->for($this->booking)->create(['dob' => '1990-01-01']);

    $quote = app(BookingService::class)->quote($this->booking->fresh());
    $this->booking->forceFill([
        'quote' => $quote->toArray(),
        'total_minor' => $quote->total->minor,
        'reservation_minor' => $quote->reservation->minor,
        'reservation_paid_minor' => $quote->reservation->minor,
        'balance_minor' => $quote->balance->minor,
    ])->save();
    $this->days = $quote->days;
});

function newReturn(string $local): CarbonImmutable
{
    return CarbonImmutable::parse($local, 'Africa/Nairobi')->utc();
}

it('previews an edit without saving it', function () {
    $before = $this->booking->only(['return_at', 'total_minor', 'balance_minor']);

    $preview = $this->service->preview($this->booking, ['return_at' => newReturn('2026-12-15 11:30')]);

    expect($preview['after']['days'])->toBe($this->days + 2)
        ->and($preview['difference']->minor)->toBeGreaterThan(0)
        ->and($preview['available'])->toBeTrue()
        ->and($this->booking->fresh()->only(array_keys($before)))->toEqual($before);
});

it('takes a total staff typed in, keeping what was paid', function () {
    $this->service->apply($this->booking, ['return_at' => newReturn('2026-12-15 11:30')], Money::fromMajor('50000'), $this->staff);

    $booking = $this->booking->fresh();
    expect($booking->total_minor)->toBe(5_000_000)
        ->and($booking->balance_minor)->toBe(5_000_000 - $booking->reservation_paid_minor);
});

it('refuses dates another booking holds', function () {
    AvailabilityBlock::factory()->create([
        'car_id' => $this->car->id,
        'starts_at' => newReturn('2026-12-14 08:00'),
        'ends_at' => newReturn('2026-12-16 08:00'),
    ]);

    expect($this->service->preview($this->booking, ['return_at' => newReturn('2026-12-15 11:30')])['available'])->toBeFalse();

    $this->service->apply($this->booking, ['return_at' => newReturn('2026-12-15 11:30')], null, $this->staff);
})->throws(BookingException::class);

it('only edits before handover', function () {
    $this->booking->forceFill(['status' => BookingStatus::Active])->save();

    expect(fn () => $this->service->preview($this->booking, ['return_at' => newReturn('2026-12-15 11:30')]))->toThrow(BookingException::class);
});

it('no longer edits a booking once it has been extended', function () {
    Booking::factory()->status(BookingStatus::Requested)->for($this->car)->create(['root_booking_id' => $this->booking->id]);

    expect(fn () => $this->service->preview($this->booking, ['return_at' => newReturn('2026-12-15 11:30')]))
        ->toThrow(BookingException::class, 'This booking has extensions');
});

it('edits the car and dates, moving the block to the new car', function () {
    $other = Car::factory()->create(['daily_rate_minor' => 600_000, 'car_value_minor' => 300_000_000]);

    $this->service->apply($this->booking, [
        'car_id' => $other->id,
        'pickup_at' => newReturn('2026-12-06 09:00'),
        'return_at' => newReturn('2026-12-08 09:00'),
    ], null, $this->staff);

    $booking = $this->booking->fresh();
    expect($booking->car_id)->toBe($other->id)
        ->and($booking->availabilityBlocks()->sole()->car_id)->toBe($other->id)
        ->and($booking->security_deposit_minor)->not->toBe(4_400_000);
});

it('lets an unpaid booking\'s reservation follow the new total', function () {
    $this->booking->forceFill(['status' => BookingStatus::AwaitingPayment, 'reservation_paid_minor' => null, 'paid_at' => null])->save();
    $old = $this->booking->fresh();

    $this->service->apply($this->booking, ['return_at' => newReturn('2026-12-15 11:30'), 'pickup_at' => $old->pickup_at], null, $this->staff);

    $booking = $this->booking->fresh();
    expect($booking->reservation_minor)->toBeGreaterThan($old->reservation_minor)
        ->and($booking->balance_minor)->toBe($booking->total_minor - $booking->reservation_minor);
});

it('makes the voucher again with the new figures', function () {
    app(VoucherService::class)->generate($this->booking);

    $this->service->apply($this->booking, ['return_at' => newReturn('2026-12-15 11:30'), 'pickup_at' => $this->booking->pickup_at], null, $this->staff);

    expect(app(VoucherService::class)->html($this->booking->fresh()))->toContain('Tuesday 15 December 2026');
});

describe('over HTTP', function () {
    it('needs the edit permission', function () {
        $this->actingAs(staffWith([Permission::BookingsView]))
            ->put(route('admin.bookings.edit', $this->booking->reference), ['return_at' => '2026-12-15T11:30:00+03:00'])
            ->assertForbidden();
    });

    it('edits through the form', function () {
        $this->actingAs($this->staff)
            ->put(route('admin.bookings.edit', $this->booking->reference), [
                'car_id' => $this->car->id,
                'pickup_at' => '2026-12-05T12:30:00+03:00',
                'return_at' => '2026-12-10T12:30:00+03:00',
                'pickup_mode' => 'office',
                'return_mode' => 'office',
                'extras' => [],
            ])
            ->assertSessionHasNoErrors();

        expect($this->booking->fresh()->return_at->equalTo(newReturn('2026-12-10 12:30')))->toBeTrue();
    });
});
