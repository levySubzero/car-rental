<?php

use App\Enums\BookingStatus;
use App\Enums\Permission;
use App\Models\Booking;
use App\Models\BookingDriver;
use App\Notifications\Booking\BookingConfirmed;
use App\Services\Notifications\VoucherService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BookingFixtures;

beforeEach(function () {
    BookingFixtures::setUp();
    $this->booking = Booking::factory()->reserved()->create(['pickup_at' => now()->addDays(10), 'return_at' => now()->addDays(13)]);
    BookingDriver::factory()->for($this->booking)->create(['first_name' => 'Amina', 'email' => 'amina@example.test']);
    $this->vouchers = app(VoucherService::class);
    $this->editor = staffWith([Permission::BookingsView, Permission::BookingsResendVoucher]);
});

function voucherEdits(array $edits): array
{
    return [...app(VoucherService::class)->editable(test()->booking), ...$edits];
}

describe('viewing', function () {
    it('is open to staff who see bookings or payments, with a PDF to download and print', function (Permission $permission) {
        $this->actingAs(staffWith([$permission]))
            ->get(route('admin.bookings.voucher.show', $this->booking->reference))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/bookings/voucher')
                ->has('voucher.urls.view')
                ->has('voucher.urls.download')
                ->where('fields.car', $this->booking->car->name));

        $voucher = $this->vouchers->current($this->booking);

        $this->actingAs(staffWith([$permission]))->get(route('media.download', $voucher->uuid))->assertOk();
    })->with([[Permission::BookingsView], [Permission::PaymentsView]]);

    it('is closed to staff who see neither', function () {
        $this->actingAs(staffWith([Permission::CarsView]))
            ->get(route('admin.bookings.voucher.show', $this->booking->reference))
            ->assertForbidden();
    });

    it('stays available after the hire, and does not exist before payment', function () {
        $this->booking->forceFill(['status' => BookingStatus::Completed])->save();
        $this->actingAs($this->editor)->get(route('admin.bookings.voucher.show', $this->booking->reference))->assertOk();

        $unpaid = Booking::factory()->awaitingPayment()->create();
        $this->actingAs($this->editor)->get(route('admin.bookings.voucher.show', $unpaid->reference))->assertNotFound();
    });

    it('can be managed while the booking is reserved or on hire', function (BookingStatus $status) {
        $this->booking->forceFill(['status' => $status])->save();

        $this->actingAs($this->editor)->get(route('admin.bookings.voucher.show', $this->booking->reference))
            ->assertInertia(fn (Assert $page) => $page->where('manageable', true)->where('void', false));
    })->with([BookingStatus::Reserved, BookingStatus::Active]);

    it('is read-only once the hire is over', function () {
        $this->booking->forceFill(['status' => BookingStatus::Completed])->save();

        $this->actingAs($this->editor)->get(route('admin.bookings.voucher.show', $this->booking->reference))
            ->assertInertia(fn (Assert $page) => $page->where('manageable', false)->where('void', false)->has('voucher.urls.view'));
    });

    it('stays on record as void after a paid booking is cancelled or a no-show, without making a new PDF', function (BookingStatus $status) {
        $this->booking->forceFill(['status' => $status])->save();

        $this->actingAs($this->editor)->get(route('admin.bookings.voucher.show', $this->booking->reference))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('manageable', false)->where('void', true)->where('voucher', null));

        expect($this->vouchers->current($this->booking))->toBeNull();

        $this->vouchers->generate($this->booking);
        $this->actingAs($this->editor)->get(route('admin.bookings.voucher.show', $this->booking->reference))
            ->assertInertia(fn (Assert $page) => $page->has('voucher.urls.download'));
    })->with([BookingStatus::Cancelled, BookingStatus::NoShow]);

    it('does not exist for a booking cancelled before it was paid', function () {
        $this->booking->forceFill(['status' => BookingStatus::Cancelled, 'paid_at' => null])->save();

        $this->actingAs($this->editor)->get(route('admin.bookings.voucher.show', $this->booking->reference))->assertNotFound();
    });
});

it('refuses to edit or send a voucher once the hire is over or the booking is void', function (BookingStatus $status) {
    Notification::fake();
    $this->booking->forceFill(['status' => $status])->save();

    $this->actingAs($this->editor)
        ->put(route('admin.bookings.voucher.update', $this->booking->reference), voucherEdits(['car' => 'Reworded']))
        ->assertRedirect();
    $this->actingAs($this->editor)
        ->post(route('admin.bookings.voucher.resend', $this->booking->reference))
        ->assertRedirect();

    expect($this->booking->fresh()->voucher_overrides)->toBeNull()
        ->and($this->booking->fresh()->voucher_sent_at)->toBeNull();
    Notification::assertNothingSent();
})->with([BookingStatus::Completed, BookingStatus::Cancelled, BookingStatus::NoShow]);

describe('editing', function () {
    it('needs the voucher permission', function () {
        $this->actingAs(staffWith([Permission::BookingsView]))
            ->put(route('admin.bookings.voucher.update', $this->booking->reference), ['car' => 'Other'])
            ->assertForbidden();
    });

    it('changes the voucher only: not the booking, the hold or the ledger', function () {
        $before = $this->booking->only(['total_minor', 'balance_minor', 'reservation_paid_minor', 'pickup_at', 'return_at', 'status']);
        $block = $this->booking->availabilityBlocks()->sole()->only(['type', 'starts_at', 'ends_at']);

        $this->actingAs($this->editor)
            ->put(route('admin.bookings.voucher.update', $this->booking->reference), voucherEdits(['total' => 'KES 1.00', 'pickup_time' => '06:00', 'notes' => 'Driver meets you at <b>Arrivals</b>.']))
            ->assertSessionHasNoErrors();

        $booking = $this->booking->fresh();
        $html = $this->vouchers->html($booking);

        expect($booking->only(array_keys($before)))->toEqual($before)
            ->and($booking->availabilityBlocks()->sole()->only(array_keys($block)))->toEqual($block)
            ->and($booking->voucher_sent_at)->toBeNull()
            ->and($booking->voucher_overrides)->toBe(['pickup_time' => '06:00', 'total' => 'KES 1.00', 'notes' => 'Driver meets you at <b>Arrivals</b>.'])
            ->and($html)->toContain('KES 1.00')
            ->and($html)->toContain('at 06:00')
            ->and($html)->toContain('Driver meets you at &lt;b&gt;Arrivals&lt;/b&gt;.');
    });

    it('goes back to the booking\'s own value when a field is emptied', function () {
        $this->vouchers->update($this->booking, voucherEdits(['car' => 'Reworded']));
        expect($this->vouchers->fields($this->booking->fresh())['car'])->toBe('Reworded');

        $this->vouchers->update($this->booking->fresh(), ['car' => '']);

        expect($this->booking->fresh()->voucher_overrides)->toBeNull()
            ->and($this->vouchers->fields($this->booking->fresh())['car'])->toBe($this->booking->car->name);
    });

    it('keeps one PDF after edits', function () {
        $this->vouchers->generate($this->booking);
        $this->vouchers->update($this->booking, voucherEdits(['car' => 'Reworded']));

        expect($this->booking->mediaIn('booking.voucher')->count())->toBe(1);
    });
});

describe('sending', function () {
    beforeEach(fn () => Notification::fake());

    it('emails what the voucher says, once, and records when', function () {
        $this->vouchers->update($this->booking, voucherEdits(['car' => 'Reworded car', 'balance' => 'KES 5.00']));

        $this->actingAs($this->editor)->post(route('admin.bookings.voucher.resend', $this->booking->reference))->assertRedirect();

        Notification::assertSentOnDemandTimes(BookingConfirmed::class, 1);
        Notification::assertSentOnDemand(BookingConfirmed::class, function (BookingConfirmed $notification) {
            $lines = collect($notification->toMail(new AnonymousNotifiable)->introLines);

            return $lines->contains('**Car:** Reworded car') && $lines->contains('**Balance due at handover:** KES 5.00');
        });
        expect($this->booking->fresh()->voucher_sent_at)->not->toBeNull();
    });

    it('refuses a client without an email and leaves it unsent', function () {
        $this->booking->driver->forceFill(['email' => null])->saveQuietly();

        $this->actingAs($this->editor)->post(route('admin.bookings.voucher.resend', $this->booking->reference))->assertRedirect();

        Notification::assertNothingSent();
        expect($this->booking->fresh()->voucher_sent_at)->toBeNull();
    });
});

it('marks a confirmed booking as waiting for its voucher until it is sent', function () {
    expect($this->booking->voucherUnsent())->toBeTrue();

    $this->booking->forceFill(['voucher_sent_at' => now()])->save();

    expect($this->booking->voucherUnsent())->toBeFalse();
});
