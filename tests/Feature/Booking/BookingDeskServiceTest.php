<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Services\Booking\BookingDeskService;
use App\Services\Booking\BookingTokenService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->desk = app(BookingDeskService::class);
});

it('groups bookings into the desk tabs and never shows drafts', function () {
    Booking::factory()->create();
    Booking::factory()->awaitingPayment()->create();
    Booking::factory()->reserved()->count(2)->create();
    Booking::factory()->active()->create();
    Booking::factory()->reserved()->create(['status' => BookingStatus::Cancelled]);

    expect($this->desk->counts())->toBe(['unpaid' => 1, 'upcoming' => 2, 'on_hire' => 1, 'closed' => 1, 'all' => 5])
        ->and($this->desk->paginate(['bucket' => 'upcoming'])->total())->toBe(2)
        ->and($this->desk->paginate(['bucket' => 'all'])->total())->toBe(5);
});

it('searches by reference, name or phone and filters by local pickup date and car', function () {
    $amina = Booking::factory()->reserved()->create(['pickup_at' => CarbonImmutable::parse('2026-12-01 23:30', 'Africa/Nairobi')->utc()]);
    BookingDriver::factory()->for($amina)->create(['first_name' => 'Amina', 'phone' => '0712345678']);
    $other = Booking::factory()->reserved()->create(['pickup_at' => CarbonImmutable::parse('2026-12-02 08:00', 'Africa/Nairobi')->utc()]);

    $ids = fn (array $filters) => $this->desk->paginate(['bucket' => 'upcoming', ...$filters])->pluck('id')->all();

    expect($ids(['search' => 'amina']))->toBe([$amina->id])
        ->and($ids(['search' => '0712345']))->toBe([$amina->id])
        ->and($ids(['search' => $other->reference]))->toBe([$other->id])
        // 23:30 in Nairobi on the 1st is the 1st, even though it is 20:30 UTC.
        ->and($ids(['from' => '2026-12-01', 'to' => '2026-12-01']))->toBe([$amina->id])
        ->and($ids(['car' => $other->car_id]))->toBe([$other->id]);
});

it('lists today: pickups and returns due, late pickups and overdue returns', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Africa/Nairobi'));

    $pickup = Booking::factory()->reserved()->create(['pickup_at' => now()->addHours(3), 'return_at' => now()->addDays(2)]);
    $late = Booking::factory()->reserved()->create(['pickup_at' => now()->subHour(), 'return_at' => now()->addDays(2)]);
    $returning = Booking::factory()->active()->create(['pickup_at' => now()->subDays(2), 'return_at' => now()->addHours(5)]);
    $overdue = Booking::factory()->active()->create(['pickup_at' => now()->subDays(3), 'return_at' => now()->subHours(2)]);
    Booking::factory()->reserved()->create(['pickup_at' => now()->addDay(), 'return_at' => now()->addDays(3)]);

    $today = $this->desk->today();

    expect($today['pickups']->pluck('id')->all())->toBe([$pickup->id])
        ->and($today['late_pickups']->pluck('id')->all())->toBe([$late->id])
        ->and($today['returns']->pluck('id')->all())->toBe([$returning->id])
        ->and($today['overdue_returns']->pluck('id')->all())->toBe([$overdue->id]);
});

it('rotates the renter link', function () {
    $booking = Booking::factory()->withToken('old-token')->reserved()->create();

    $token = $this->desk->rotateLink($booking, staffWith());

    $tokens = app(BookingTokenService::class);
    expect($tokens->find('old-token'))->toBeNull()
        ->and($tokens->find($token)?->is($booking))->toBeTrue();
});
