<?php

use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Enums\VoucherBucket;
use App\Models\Booking;
use App\Models\BookingDriver;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
});

/**
 * A confirmed booking with a driver, in the given state.
 *
 * @param  array<string, mixed>  $attributes
 */
function voucherBooking(array $attributes = [], ?string $email = 'renter@example.test'): Booking
{
    $booking = Booking::factory()->reserved()->create($attributes);
    BookingDriver::factory()->for($booking)->create(['email' => $email]);

    return $booking->fresh();
}

/**
 * @return list<string>
 */
function inBucket(VoucherBucket $bucket): array
{
    return $bucket->apply(Booking::query())->orderBy('id')->pluck('reference')->all();
}

it('sends guests to the login page and refuses staff who see neither bookings nor payments', function () {
    $this->get(route('admin.vouchers.index'))->assertRedirect(route('login'));
    $this->actingAs(staffWith([Permission::CarsView]))->get(route('admin.vouchers.index'))->assertForbidden();
});

it('is open to staff who see bookings or payments', function (Permission $permission) {
    $this->actingAs(staffWith([$permission]))->get(route('admin.vouchers.index'))->assertOk();
})->with([[Permission::BookingsView], [Permission::PaymentsView]]);

it('puts each voucher in the tab for where it stands', function () {
    $unsent = voucherBooking();
    $onHire = voucherBooking(['status' => BookingStatus::Active]);
    $sent = voucherBooking(['voucher_sent_at' => now()]);
    $byHand = voucherBooking(['source' => BookingSource::Admin], email: null);
    $byHandBlank = voucherBooking(['source' => BookingSource::Admin], email: '');
    $staffEmailed = voucherBooking(['source' => BookingSource::Admin]);
    $used = voucherBooking(['status' => BookingStatus::Completed]);
    $usedByHand = voucherBooking(['status' => BookingStatus::Completed, 'source' => BookingSource::Admin], email: null);
    $noShow = voucherBooking(['status' => BookingStatus::NoShow]);
    $cancelled = voucherBooking(['status' => BookingStatus::Cancelled]);

    // Never confirmed: no voucher anywhere.
    $declined = voucherBooking(['status' => BookingStatus::Cancelled, 'paid_at' => null]);
    Booking::factory()->awaitingPayment()->create();

    expect(inBucket(VoucherBucket::NotSent))->toBe([$unsent->reference, $onHire->reference, $staffEmailed->reference])
        ->and(inBucket(VoucherBucket::Sent))->toBe([$sent->reference, $byHand->reference, $byHandBlank->reference])
        ->and(inBucket(VoucherBucket::Used))->toBe([$used->reference, $usedByHand->reference])
        ->and(inBucket(VoucherBucket::Void))->toBe([$noShow->reference, $cancelled->reference])
        ->and(inBucket(VoucherBucket::All))->toHaveCount(10)
        ->and(inBucket(VoucherBucket::All))->not->toContain($declined->reference);
});

it('agrees with the booking on which vouchers are waiting to be sent', function () {
    voucherBooking();
    voucherBooking(['status' => BookingStatus::Active]);
    voucherBooking(['voucher_sent_at' => now()]);
    voucherBooking(['source' => BookingSource::Admin], email: null);
    voucherBooking(['source' => BookingSource::Admin], email: '');
    voucherBooking(['source' => BookingSource::Admin]);
    voucherBooking(['status' => BookingStatus::Completed]);

    $live = Booking::query()->whereIn('status', [BookingStatus::Reserved, BookingStatus::Active])->with('driver')->get();
    $waiting = $live->filter(fn (Booking $booking) => $booking->voucherUnsent())->pluck('reference')->sort()->values()->all();

    expect(collect(inBucket(VoucherBucket::NotSent))->sort()->values()->all())->toBe($waiting)
        ->and(collect([...inBucket(VoucherBucket::NotSent), ...inBucket(VoucherBucket::Sent)])->sort()->values()->all())
        ->toBe($live->pluck('reference')->sort()->values()->all());
});

it('opens on Not sent with tab counts, and flags vouchers staff hand over themselves', function () {
    $unsent = voucherBooking();
    $byHand = voucherBooking(['source' => BookingSource::Admin], email: null);
    voucherBooking(['status' => BookingStatus::NoShow]);

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.vouchers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/vouchers/index')
            ->where('filters.bucket', 'not_sent')
            ->has('vouchers.data', 1)
            ->where('vouchers.data.0.reference', $unsent->reference)
            ->where('vouchers.data.0.by_hand', false)
            ->where('counts.not_sent', 1)
            ->where('counts.sent', 1)
            ->where('counts.void', 1)
            ->where('counts.all', 3));

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.vouchers.index', ['bucket' => 'sent']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('vouchers.data.0.reference', $byHand->reference)
            ->where('vouchers.data.0.by_hand', true)
            ->where('vouchers.data.0.sent_at', null));
});

it('searches and filters by car and pickup date', function () {
    $match = voucherBooking(['pickup_at' => now()->addDays(5)->setTime(10, 0), 'return_at' => now()->addDays(8)]);
    voucherBooking(['pickup_at' => now()->addDays(20)->setTime(10, 0), 'return_at' => now()->addDays(23)]);

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.vouchers.index', ['search' => $match->reference]))
        ->assertInertia(fn (Assert $page) => $page->has('vouchers.data', 1)->where('vouchers.data.0.reference', $match->reference));

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.vouchers.index', ['car' => $match->car_id]))
        ->assertInertia(fn (Assert $page) => $page->has('vouchers.data', 1)->where('vouchers.data.0.reference', $match->reference));

    $day = $match->pickup_at->setTimezone((string) config('baines.timezone'))->toDateString();
    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.vouchers.index', ['from' => $day, 'to' => $day]))
        ->assertInertia(fn (Assert $page) => $page->has('vouchers.data', 1)->where('vouchers.data.0.reference', $match->reference));
});

it('validates the filters', function () {
    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.vouchers.index', ['bucket' => 'nope', 'from' => '2026-13-40']))
        ->assertSessionHasErrors(['bucket', 'from']);
});
