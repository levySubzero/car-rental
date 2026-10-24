<?php

use App\Enums\Permission;
use App\Models\AvailabilityBlock;
use App\Models\Booking;
use App\Models\Car;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-01 09:00', 'Africa/Nairobi'));
});

it('is open to the desk and to whoever manages blocks, and no one else', function () {
    $this->get(route('admin.availability.index'))->assertRedirect(route('login'));
    $this->actingAs(staffWith([Permission::PaymentsView]))->get(route('admin.availability.index'))->assertForbidden();
    $this->actingAs(staffWith([Permission::BookingsView]))->get(route('admin.availability.index'))->assertOk();
    $this->actingAs(staffWith([Permission::AvailabilityManage]))->get(route('admin.availability.index'))->assertOk();
});

it('shows the blocks in the window from local midnight, with the booking reference', function () {
    $booking = Booking::factory()->reserved()->create(['pickup_at' => now()->addDays(2), 'return_at' => now()->addDays(4)]);
    $car = $booking->car;
    AvailabilityBlock::factory()->create(['car_id' => $car->id, 'starts_at' => now()->addDays(6), 'ends_at' => now()->addDays(7)]);
    // Outside a two-week window.
    AvailabilityBlock::factory()->create(['car_id' => $car->id, 'starts_at' => now()->addDays(20), 'ends_at' => now()->addDays(21)]);
    $other = Car::factory()->create();

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.availability.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/availability/index')
            ->where('start', '2026-12-01T00:00:00+03:00')
            ->where('days', 14)
            ->has('blocks', 2)
            ->where('blocks.0.type', 'booked')
            ->where('blocks.0.booking.reference', $booking->reference)
            ->has('cars', 2));

    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.availability.index', ['car' => $other->id, 'days' => 28]))
        ->assertInertia(fn (Assert $page) => $page->has('cars', 1)->has('blocks', 0)->where('days', 28));
});

it('validates the window', function () {
    $this->actingAs(staffWith([Permission::BookingsView]))
        ->get(route('admin.availability.index', ['days' => 9, 'start' => 'soon']))
        ->assertSessionHasErrors(['days', 'start']);
});
