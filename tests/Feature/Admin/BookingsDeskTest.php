<?php

use App\Enums\BookingStatus;
use App\Enums\HandoverMode;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Models\DeliveryZone;
use App\Models\Media;
use App\Models\User;
use App\Notifications\Booking\BookingConfirmed;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    Notification::fake();
    $this->booking = Booking::factory()->reserved()->create();
    BookingDriver::factory()->for($this->booking)->create(['first_name' => 'Amina', 'id_number' => '12345678']);
});

function desk(Permission ...$extra): User
{
    return staffWith([Permission::BookingsView, ...$extra]);
}

it('sends guests to the login page and refuses staff without bookings.view', function () {
    $this->get(route('admin.bookings.index'))->assertRedirect(route('login'));
    $this->actingAs(staffWith([Permission::PaymentsView]))->get(route('admin.bookings.index'))->assertForbidden();
    $this->actingAs(staffWith([Permission::PaymentsView]))->get(route('admin.bookings.show', $this->booking->reference))->assertForbidden();
});

it('lists upcoming bookings by default with tab counts', function () {
    Booking::factory()->awaitingPayment()->create();

    $this->actingAs(desk())
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/bookings/index')
            ->where('filters.bucket', 'upcoming')
            ->has('bookings.data', 1)
            ->where('bookings.data.0.reference', $this->booking->reference)
            ->where('bookings.data.0.renter', fn ($name) => str_starts_with($name, 'Amina'))
            ->where('counts.unpaid', 1)
            ->where('counts.upcoming', 1));
});

it('validates the list filters', function () {
    $this->actingAs(desk())
        ->get(route('admin.bookings.index', ['bucket' => 'nope', 'from' => '2026-13-40']))
        ->assertSessionHasErrors(['bucket', 'from']);
});

it('shows a booking with masked identity numbers and no documents without the documents permission', function () {
    Media::factory()->private()->attachedTo($this->booking, 'booking.licence')->create();

    $this->actingAs(desk())
        ->get(route('admin.bookings.show', $this->booking->reference))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/bookings/show')
            ->where('booking.driver.id_number', '•••••678')
            ->where('booking.driver.masked', true)
            ->where('booking.documents', null)
            ->where('booking.quote.deposit_band', null)
            ->has('refund.cancel')
            ->has('timeline'));

    $this->actingAs(desk(Permission::BookingsViewDocuments))
        ->get(route('admin.bookings.show', $this->booking->reference))
        ->assertInertia(fn (Assert $page) => $page
            ->where('booking.driver.id_number', '12345678')
            ->has('booking.documents', 1)
            ->where('booking.documents.0.label', 'Driving licence'));
});

it('records the handover and the return', function () {
    $this->actingAs(desk(Permission::BookingsRecordReturn))
        ->post(route('admin.bookings.handover', $this->booking->reference), [])
        ->assertForbidden();

    $staff = desk(Permission::BookingsRecordHandover, Permission::BookingsRecordReturn);

    $this->actingAs($staff)
        ->post(route('admin.bookings.handover', $this->booking->reference), ['odometer_km' => 'x', 'fuel_level' => 'brim'])
        ->assertSessionHasErrors(['odometer_km', 'fuel_level', 'balance_received', 'deposit_received']);

    $this->actingAs($staff)
        ->post(route('admin.bookings.handover', $this->booking->reference), [
            'odometer_km' => 12000, 'fuel_level' => 'full', 'balance_received' => '49762.00', 'deposit_received' => '160000',
        ])
        ->assertSessionHasNoErrors();
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Active);

    $this->actingAs($staff)
        ->post(route('admin.bookings.return', $this->booking->reference), [
            'odometer_km' => 12500, 'fuel_level' => 'full', 'deposit_refunded' => '160000', 'extra_charges' => '0',
        ])
        ->assertSessionHasNoErrors();
    expect($this->booking->fresh()->status)->toBe(BookingStatus::Completed);
});

it('shows a refused action as an error toast and changes nothing', function () {
    $this->actingAs(desk(Permission::BookingsRecordHandover))
        ->post(route('admin.bookings.handover', $this->booking->reference), [
            'odometer_km' => 12000, 'fuel_level' => 'full', 'balance_received' => '1000', 'deposit_received' => '0',
        ])
        ->assertRedirect();

    expect(session('inertia.flash_data')['toast'] ?? null)->toMatchArray(['type' => 'error'])
        ->and(session('inertia.flash_data')['toast']['message'])->toContain('Add a note')
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Reserved);
});

it('cancels with a refund and records it paid back', function () {
    $this->actingAs(desk())->post(route('admin.bookings.cancel', $this->booking->reference), [])->assertForbidden();

    $staff = desk(Permission::BookingsCancel);

    $this->actingAs($staff)
        ->post(route('admin.bookings.cancel', $this->booking->reference), ['reason' => '', 'refund' => ''])
        ->assertSessionHasErrors(['reason', 'refund']);

    $this->actingAs($staff)
        ->post(route('admin.bookings.cancel', $this->booking->reference), ['reason' => 'Called off', 'refund' => '5000', 'refund_override_reason' => 'Goodwill'])
        ->assertSessionHasNoErrors();

    $this->actingAs($staff)
        ->post(route('admin.bookings.refunded', $this->booking->reference), ['reference' => 'MPESA1'])
        ->assertSessionHasNoErrors();

    $booking = $this->booking->fresh();
    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->refund_minor)->toBe(500_000)
        ->and($booking->refund_reference)->toBe('MPESA1');
});

it('marks a no-show', function () {
    $this->actingAs(desk(Permission::BookingsCancel))
        ->post(route('admin.bookings.no-show', $this->booking->reference), ['reason' => 'Never came', 'refund' => '0'])
        ->assertSessionHasNoErrors();

    expect($this->booking->fresh()->status)->toBe(BookingStatus::NoShow);
});

it('sets the delivery fee, moves the zone and marks the address checked', function () {
    $quoted = DeliveryZone::factory()->create(['fee_minor' => null]);
    $priced = DeliveryZone::factory()->create(['fee_minor' => 200_000]);
    $this->booking->forceFill(['pickup_mode' => HandoverMode::Address, 'delivery_zone_id' => $quoted->id])->save();

    $this->actingAs(desk())->put(route('admin.bookings.delivery.update', $this->booking->reference), ['fee' => '1'])->assertForbidden();

    $staff = desk(Permission::BookingsUpdateDelivery);

    $this->actingAs($staff)
        ->put(route('admin.bookings.delivery.update', $this->booking->reference), [])
        ->assertSessionHasErrors('fee');

    $this->actingAs($staff)
        ->put(route('admin.bookings.delivery.update', $this->booking->reference), ['fee' => '3000'])
        ->assertSessionHasNoErrors();
    expect($this->booking->fresh()->delivery_fee_minor)->toBe(300_000);

    $this->actingAs($staff)
        ->put(route('admin.bookings.delivery.update', $this->booking->reference), ['delivery_zone_id' => $priced->id])
        ->assertSessionHasNoErrors();
    expect($this->booking->fresh()->delivery_zone_id)->toBe($priced->id)
        ->and($this->booking->fresh()->delivery_fee_minor)->toBe(200_000);

    $this->actingAs($staff)
        ->post(route('admin.bookings.delivery.checked', $this->booking->reference))
        ->assertSessionHasNoErrors();
    expect($this->booking->fresh()->delivery_checked_at)->not->toBeNull();
});

it('resends the voucher and replaces the renter link', function () {
    $this->actingAs(desk())->post(route('admin.bookings.voucher.resend', $this->booking->reference))->assertForbidden();

    $staff = desk(Permission::BookingsResendVoucher);
    $before = $this->booking->access_token_hash;

    $this->actingAs($staff)
        ->post(route('admin.bookings.voucher.resend', $this->booking->reference))
        ->assertRedirect();
    Notification::assertSentOnDemand(BookingConfirmed::class);

    $this->actingAs($staff)
        ->post(route('admin.bookings.link.rotate', $this->booking->reference))
        ->assertRedirect();

    expect($this->booking->fresh()->access_token_hash)->not->toBe($before);
});

it('sets and lifts a legal hold', function () {
    $this->actingAs(desk(Permission::BookingsCancel))
        ->put(route('admin.bookings.legal-hold', $this->booking->reference), ['legal_hold' => true, 'reason' => 'Claim'])
        ->assertForbidden();

    $staff = desk(Permission::BookingsLegalHold);

    $this->actingAs($staff)
        ->put(route('admin.bookings.legal-hold', $this->booking->reference), ['legal_hold' => true])
        ->assertSessionHasErrors('reason');

    $this->actingAs($staff)
        ->put(route('admin.bookings.legal-hold', $this->booking->reference), ['legal_hold' => true, 'reason' => 'Insurance claim'])
        ->assertSessionHasNoErrors();
    expect($this->booking->fresh()->legal_hold)->toBeTrue();

    $this->actingAs($staff)
        ->put(route('admin.bookings.legal-hold', $this->booking->reference), ['legal_hold' => false])
        ->assertSessionHasNoErrors();
    expect($this->booking->fresh()->legal_hold)->toBeFalse();
});

it('shows what is still owing after a short handover', function () {
    $staff = desk(Permission::BookingsRecordHandover);
    $show = fn () => $this->actingAs($staff)->get(route('admin.bookings.show', $this->booking->reference));

    $show()->assertInertia(fn (Assert $page) => $page->where('booking.balance_outstanding', null));

    $this->actingAs($staff)
        ->post(route('admin.bookings.handover', $this->booking->reference), [
            'odometer_km' => 12000, 'fuel_level' => 'full', 'balance_received' => '40000', 'deposit_received' => '160000',
            'notes' => 'Rest by M-Pesa tomorrow',
        ])
        ->assertSessionHasNoErrors();

    // 49,762 due, 40,000 taken.
    $show()->assertInertia(fn (Assert $page) => $page->where('booking.balance_outstanding.minor', 976_200));
});

it('owes nothing after a handover paid in full', function () {
    $staff = desk(Permission::BookingsRecordHandover);

    $this->actingAs($staff)
        ->post(route('admin.bookings.handover', $this->booking->reference), [
            'odometer_km' => 12000, 'fuel_level' => 'full', 'balance_received' => '49762', 'deposit_received' => '160000',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($staff)
        ->get(route('admin.bookings.show', $this->booking->reference))
        ->assertInertia(fn (Assert $page) => $page->where('booking.balance_outstanding', null));
});
